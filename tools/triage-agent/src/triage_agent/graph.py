import asyncio
from dataclasses import dataclass, replace
from pathlib import Path
from typing import Any

from langgraph.checkpoint.memory import InMemorySaver
from langgraph.graph import END, START, StateGraph
from langgraph.types import RetryPolicy

from triage_agent.agents.sessions import Fixer, Reproducer
from triage_agent.config import Settings
from triage_agent.github import render
from triage_agent.github.base import (
    AGENT_LABEL,
    AUTO_LABEL,
    NEEDS_HUMAN_LABEL,
    NOT_REPRODUCED_LABEL,
    TERMINAL_LABELS,
    VERIFY_FAILED_LABEL,
    GitHub,
    GitHubTransientError,
    branch_name,
    comment_once,
    find_issue,
)
from triage_agent.harness.expected import check_expected_failure
from triage_agent.harness.verify import VerifyInputs, verify
from triage_agent.harness.workspace import Workspace
from triage_agent.llm.triage import TriageFailed, Triager
from triage_agent.models import CheckResult, Fix, IssueRef, Outcome, PullRef, Repro, VerifyResult
from triage_agent.routing import REPORT_OUTCOME, Action, after_verify, decide_existing_work, needs_human
from triage_agent.sanitize import clean_event, redact, sensitive_values
from triage_agent.state import TriageState, fresh_run

EXCERPT_RADIUS = 20
MAX_EXCERPTS = 3
GITHUB_RETRY = RetryPolicy(max_attempts=3, initial_interval=2.0, backoff_factor=2.0, retry_on=GitHubTransientError)
OUTCOME_LABELS = {
    Outcome.NEEDS_HUMAN: NEEDS_HUMAN_LABEL,
    Outcome.NOT_REPRODUCED: NOT_REPRODUCED_LABEL,
    Outcome.VERIFY_FAILED: VERIFY_FAILED_LABEL,
}


@dataclass(frozen=True)
class Deps:
    settings: Settings
    github: GitHub
    triager: Triager
    reproducer: Reproducer
    fixer: Fixer
    workspace: Workspace


def build_graph(deps: Deps) -> Any:
    nodes = _Nodes(deps)
    graph = StateGraph(TriageState)
    graph.add_node("normalize_event", nodes.normalize_event)
    graph.add_node("check_existing_work", nodes.check_existing_work, retry_policy=GITHUB_RETRY)
    graph.add_node("triage", nodes.triage)
    graph.add_node("upsert_issue", nodes.upsert_issue, retry_policy=GITHUB_RETRY)
    graph.add_node("reproduce", nodes.reproduce)
    graph.add_node("fix", nodes.fix)
    graph.add_node("verify", nodes.verify)
    graph.add_node("open_pr", nodes.open_pr, retry_policy=GITHUB_RETRY)
    graph.add_node(REPORT_OUTCOME, nodes.report_outcome, retry_policy=GITHUB_RETRY)

    graph.add_edge(START, "normalize_event")
    graph.add_edge("normalize_event", "check_existing_work")
    graph.add_conditional_edges("check_existing_work", route_after_check, ["triage", REPORT_OUTCOME])
    graph.add_edge("triage", "upsert_issue")
    graph.add_conditional_edges("upsert_issue", continue_or_report("reproduce"), ["reproduce", REPORT_OUTCOME])
    graph.add_conditional_edges("reproduce", continue_or_report("fix"), ["fix", REPORT_OUTCOME])
    graph.add_edge("fix", "verify")
    graph.add_conditional_edges("verify", nodes.route_after_verify, ["open_pr", "fix", REPORT_OUTCOME])
    graph.add_edge("open_pr", END)
    graph.add_edge(REPORT_OUTCOME, END)
    return graph.compile(checkpointer=InMemorySaver())


def route_after_check(state: TriageState) -> str:
    decision = state.get("decision")
    return "triage" if decision and decision.proceeds else REPORT_OUTCOME


def continue_or_report(next_node: str) -> Any:
    def route(state: TriageState) -> str:
        return REPORT_OUTCOME if state.get("outcome") else next_node

    return route


class _Nodes:
    def __init__(self, deps: Deps) -> None:
        self.settings = deps.settings
        self.github = deps.github
        self.triager = deps.triager
        self.reproducer = deps.reproducer
        self.fixer = deps.fixer
        self.workspace = deps.workspace

    def normalize_event(self, state: TriageState) -> TriageState:
        return fresh_run(clean_event(state["raw_event"]))

    def check_existing_work(self, state: TriageState) -> TriageState:
        event = state["event"]
        issue = find_issue(self.github, event.fingerprint, self.settings.bot_login)
        pulls = self.github.pulls_for_branch(branch_name(event.fingerprint, self.settings.base_ref))
        decision = decide_existing_work(issue, pulls, self.github.open_agent_pull_count(), self.settings.max_open_agent_prs, event.last_seen)
        update = TriageState(decision=decision, final_node="check_existing_work", reason=decision.reason)
        if issue:
            update["issue"] = IssueRef(issue.number, issue.url)

        if decision.action in (Action.NOTE_OCCURRENCE, Action.NOTE_REJECTED):
            update["outcome"] = Outcome.ALREADY_HANDLED
        elif decision.action is Action.AT_CAPACITY:
            update["outcome"] = Outcome.AT_CAPACITY
        return update

    async def triage(self, state: TriageState) -> TriageState:
        event = state["event"]
        try:
            result, usage = await self.triager.triage(event, self._source_excerpts(state))
        except TriageFailed as error:
            return TriageState(triage=None, final_node="triage", reason=str(error), usage=error.usage)
        public = result.model_copy(
            update={
                "summary": redact(result.summary, event.message),
                "likely_area": redact(result.likely_area, event.message),
                "suspected_files": [redact(path, event.message) for path in result.suspected_files],
            }
        )
        return TriageState(triage=public, final_node="triage", usage=usage)

    def upsert_issue(self, state: TriageState) -> TriageState:
        event, triage = state["event"], state.get("triage")
        title, body = render.issue_title(event), render.issue_body(event, triage)
        existing, decision = state.get("issue") or self._find_issue_ref(event.fingerprint), state.get("decision")

        if existing:
            reopening = decision is not None and decision.action is Action.REOPEN_AND_CONTINUE
            self.github.update_issue(existing.number, title=title, body=body, state="open" if reopening else None)
            if reopening:
                self.github.remove_labels(existing.number, sorted(TERMINAL_LABELS))
                comment_once(self.github, existing.number, f"reopened:{event.last_seen.isoformat()}", render.reopened_comment(event), self.settings.bot_login)
            issue = existing
        else:
            created = self.github.create_issue(title, body, [AGENT_LABEL, AUTO_LABEL])
            issue = IssueRef(created.number, created.url)

        update = TriageState(issue=issue, final_node="upsert_issue")
        if triage is None or needs_human(triage.is_code_bug, triage.confidence, self.settings.confidence_threshold):
            update["outcome"] = Outcome.NEEDS_HUMAN
            update["reason"] = state.get("reason") or (
                "not a code bug" if triage and not triage.is_code_bug else f"confidence {triage.confidence:.2f} below threshold" if triage else "triage failed"
            )
        return update

    async def reproduce(self, state: TriageState) -> TriageState:
        event, triage = state["event"], state["triage"]
        assert triage is not None
        worktree = await asyncio.to_thread(self.workspace.prepare, event.fingerprint)
        attempt = await self.reproducer.reproduce(event, triage, worktree)
        base = TriageState(final_node="reproduce", worktree=str(worktree), usage=attempt.usage)

        if attempt.error or not attempt.test_path:
            return _not_reproduced(base, attempt.error or "the session did not write a test")
        changed = sorted(change.path for change in self.workspace.changes(worktree))
        if changed != [attempt.test_path]:
            return _not_reproduced(base, f"the session changed files other than one new test: {', '.join(changed)}")

        report = await self.workspace.run_tests(worktree, attempt.test_path)
        check = check_expected_failure(report, attempt.test_path, event)
        if not check.expected:
            return _not_reproduced(base, check.reason)

        test_hash = self.workspace.file_hash(worktree, attempt.test_path)
        assert test_hash is not None
        return TriageState(**base, repro=Repro(attempt.test_path, test_hash, redact(attempt.explanation, event.message)), reason=check.reason)

    async def fix(self, state: TriageState) -> TriageState:
        repro, triage, previous = state["repro"], state["triage"], state.get("verify")
        assert repro is not None and triage is not None
        feedback = "\n".join(f"{check.name}: {check.detail}" for check in previous.failures) if previous else None
        attempt = await self.fixer.fix(state["event"], triage, repro, Path(state["worktree"] or ""), feedback)
        return TriageState(
            fix=_redact_fix(attempt.fix, state["event"].message),
            fix_attempts=state["fix_attempts"] + 1,
            final_node="fix",
            reason=attempt.error or "",
            usage=attempt.usage,
        )

    async def verify(self, state: TriageState) -> TriageState:
        repro, worktree = state["repro"], Path(state["worktree"] or "")
        assert repro is not None
        if state.get("fix") is None:
            result = VerifyResult(False, (CheckResult("fix session", False, state.get("reason") or "no fix produced"),))
        else:
            result = await self._run_checks(worktree, repro, state["event"].message)

        update = TriageState(verify=result, final_node="verify")
        if after_verify(result.passed, state["fix_attempts"], self.settings.max_fix_attempts) == REPORT_OUTCOME:
            update["outcome"] = Outcome.VERIFY_FAILED
            update["reason"] = "; ".join(check.name for check in result.failures)
        return update

    def route_after_verify(self, state: TriageState) -> str:
        result = state["verify"]
        assert result is not None
        return after_verify(result.passed, state["fix_attempts"], self.settings.max_fix_attempts)

    def open_pr(self, state: TriageState) -> TriageState:
        event, issue, repro, fix, result = state["event"], state["issue"], state["repro"], state["fix"], state["verify"]
        assert issue is not None and repro is not None and fix is not None and result is not None
        branch = branch_name(event.fingerprint, self.settings.base_ref)
        worktree = Path(state["worktree"] or "")
        self.workspace.commit(worktree, branch, render.commit_message(fix, issue.number))
        if not self.settings.dry_run:
            self.workspace.push(worktree, branch)

        title, body = render.pull_title(fix), render.pull_body(event, issue.number, repro, fix, result)
        open_pulls = [pull for pull in self.github.pulls_for_branch(branch) if pull.state == "open"]
        if open_pulls:
            pull = self.github.update_pull(open_pulls[0].number, title=title, body=body)
        else:
            pull = self.github.create_pull(head=branch, base=self.settings.base_ref, title=title, body=body)
        return TriageState(pr=PullRef(pull.number, pull.url), final_node="open_pr", outcome=Outcome.PR_OPENED, reason="")

    def report_outcome(self, state: TriageState) -> TriageState:
        issue, outcome = state.get("issue"), state["outcome"]
        if issue is None or outcome is None:
            return TriageState()
        if label := OUTCOME_LABELS.get(outcome):
            self.github.add_labels(issue.number, [label])
        if note := self._outcome_note(state, outcome):
            key, body = note
            comment_once(self.github, issue.number, key, body, self.settings.bot_login)
        return TriageState()

    def _find_issue_ref(self, fingerprint: str) -> IssueRef | None:
        issue = find_issue(self.github, fingerprint, self.settings.bot_login)
        return IssueRef(issue.number, issue.url) if issue else None

    def _outcome_note(self, state: TriageState, outcome: Outcome) -> tuple[str, str] | None:
        event, decision = state["event"], state.get("decision")
        last_seen = event.last_seen.isoformat()
        if outcome is Outcome.ALREADY_HANDLED and decision:
            if decision.action is Action.NOTE_REJECTED and decision.pull:
                return f"rejected:{decision.pull.number}:{last_seen}", render.rejected_comment(event, decision.pull.url)
            return f"occurrence:{last_seen}", render.occurrence_comment(event, decision.reason)
        if outcome is Outcome.NOT_REPRODUCED:
            return f"not-reproduced:{last_seen}", render.not_reproduced_comment(state["reason"])
        if outcome is Outcome.VERIFY_FAILED:
            result = state["verify"]
            assert result is not None
            return f"verify-failed:{last_seen}", render.verify_failed_comment(result, state["fix_attempts"])
        return None

    async def _run_checks(self, worktree: Path, repro: Repro, message: str) -> VerifyResult:
        changes = self.workspace.changes(worktree)
        php_paths = [change.path for change in changes if change.path.endswith(".php") and "D" not in change.status]
        suite = await self.workspace.run_tests(worktree)
        pint = await self.workspace.pint(worktree, php_paths)
        larastan = await self.workspace.larastan(worktree)
        inputs = VerifyInputs(
            changes=changes,
            repro_path=repro.test_path,
            repro_hash_before=repro.test_hash,
            repro_hash_after=self.workspace.file_hash(worktree, repro.test_path),
            diff_lines=self.workspace.diff_lines(worktree),
            suite=suite,
            pint=pint,
            larastan=larastan,
            changed_text="\n".join(self.workspace.read_file(worktree, change.path) or "" for change in changes),
            forbidden_values=sensitive_values(message),
        )
        return verify(inputs, allowed_prefixes=self.settings.fix_allowed_prefixes, max_diff_lines=self.settings.max_diff_lines)

    def _source_excerpts(self, state: TriageState) -> dict[str, str]:
        excerpts: dict[str, str] = {}
        for frame in state["event"].app_frames:
            if len(excerpts) >= MAX_EXCERPTS or frame.file in excerpts:
                continue
            source = self.workspace.read_base_file(frame.file)
            if source is None:
                continue
            lines = source.splitlines()
            center = (frame.line or 1) - 1
            start, end = max(0, center - EXCERPT_RADIUS), min(len(lines), center + EXCERPT_RADIUS + 1)
            excerpts[frame.file] = "\n".join(f"{number + 1}: {lines[number]}" for number in range(start, end))
        return excerpts


def _redact_fix(fix: Fix | None, message: str) -> Fix | None:
    if fix is None:
        return None
    return replace(
        fix,
        root_cause=redact(fix.root_cause, message),
        commit_subject=redact(fix.commit_subject, message),
        summary=redact(fix.summary, message),
        risks=tuple(redact(risk, message) for risk in fix.risks),
    )


def _not_reproduced(base: TriageState, reason: str) -> TriageState:
    return TriageState(**base, outcome=Outcome.NOT_REPRODUCED, reason=reason)
