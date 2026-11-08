import asyncio
import json
from pathlib import Path
from typing import Any

import pytest
from fakes import (
    APP_FILE,
    BOT,
    REPO,
    TEST_PATH,
    FakeFixer,
    FakeReproducer,
    FakeTriager,
    FakeWorkspace,
    failing_case,
    passing_case,
    raw_event,
    report,
    settings,
    triage_result,
)

from triage_agent.cli import execute
from triage_agent.github.client import (
    AGENT_LABEL,
    AUTO_LABEL,
    NEEDS_HUMAN_LABEL,
    NOT_REPRODUCED_LABEL,
    VERIFY_FAILED_LABEL,
    branch_name,
    marker,
)
from triage_agent.github.memory import InMemoryGitHub
from triage_agent.graph import Deps, build_graph
from triage_agent.models import Outcome, Usage
from triage_agent.state import TriageState

FINGERPRINT = "d96aebf547693359"


class Harness:
    def __init__(self, tmp_path: Path, **settings_overrides: Any) -> None:
        self.settings = settings(tmp_path, **settings_overrides)
        self.github = InMemoryGitHub(REPO, BOT)
        self.triager = FakeTriager()
        self.workspace = FakeWorkspace()
        self.reproducer = FakeReproducer(self.workspace)
        self.fixer = FakeFixer(self.workspace)

    def deps(self) -> Deps:
        return Deps(self.settings, self.github, self.triager, self.reproducer, self.fixer, self.workspace)

    def run(self, **event_overrides: Any) -> TriageState:
        return asyncio.run(execute(self.deps(), raw_event(**event_overrides)))

    def visited_nodes(self) -> list[str]:
        async def visit() -> list[str]:
            graph = build_graph(self.deps())
            config = {"configurable": {"thread_id": FINGERPRINT}}
            return [node async for update in graph.astream({"raw_event": raw_event()}, config, stream_mode="updates") for node in update]

        return asyncio.run(visit())

    def runs(self) -> list[dict[str, Any]]:
        return [json.loads(line) for line in self.settings.runs_log.read_text().splitlines()]

    def seed_issue(self, **kwargs: Any) -> int:
        return self.github.seed_issue(body=f"{marker(FINGERPRINT)}\nbody", **kwargs).number

    def comments(self, number: int) -> list[str]:
        return [comment.body for comment in self.github.comments(number)]


@pytest.fixture
def harness(tmp_path: Path) -> Harness:
    return Harness(tmp_path)


def test_happy_path_opens_a_draft_pr_linked_to_a_new_issue(harness: Harness):
    state = harness.run()

    assert state["outcome"] is Outcome.PR_OPENED
    [issue] = harness.github.issues.values()
    assert issue.labels == {AGENT_LABEL, AUTO_LABEL}
    assert issue.body.split("\n", 1)[0] == marker(FINGERPRINT)
    [pull] = harness.github.pulls.values()
    assert pull.head == branch_name(FINGERPRINT) and pull.draft
    assert harness.workspace.commits == [
        (branch_name(FINGERPRINT), f"fix: guard missing watch provider results\n\nDefault to an empty list.\n\nRefs #{issue.number}")
    ]
    [run] = harness.runs()
    assert run["outcome"] == "pr_opened"
    assert run["final_node"] == "open_pr"
    assert run["tokens"]["input"] == 3100
    assert run["turns"] == 20
    assert run["api_equivalent_usd"] == 0.01


def test_dry_run_logs_no_urls_because_nothing_exists_on_github(harness: Harness):
    harness.run()

    [run] = harness.runs()
    assert run["dry_run"] is True
    assert run["issue_url"] is None and run["pr_url"] is None


def test_live_run_logs_the_real_urls(tmp_path: Path):
    harness = Harness(tmp_path, dry_run=False)

    harness.run()

    [issue] = harness.github.issues.values()
    [pull] = harness.github.pulls.values()
    [run] = harness.runs()
    assert run["dry_run"] is False
    assert run["issue_url"] == issue.url and run["pr_url"] == pull.url


def test_early_stop_reports_in_its_own_step_and_keeps_the_stopping_node(harness: Harness):
    harness.workspace.repro_report = report(passing_case())

    visited = harness.visited_nodes()

    assert visited[-2:] == ["reproduce", "report_outcome"]
    [issue] = harness.github.issues.values()
    assert NOT_REPRODUCED_LABEL in issue.labels
    assert any("Could not reproduce" in body for body in harness.comments(issue.number))


def test_successful_run_never_reaches_report_outcome(harness: Harness):
    assert "report_outcome" not in harness.visited_nodes()


def test_final_node_names_the_step_that_stopped_the_run(harness: Harness):
    harness.workspace.repro_report = report(passing_case())

    assert harness.run()["final_node"] == "reproduce"


def test_usage_adds_up_within_a_run_and_resets_when_a_thread_is_reused(harness: Harness):
    async def run_twice() -> tuple[TriageState, TriageState]:
        graph = build_graph(harness.deps())
        config = {"configurable": {"thread_id": FINGERPRINT}}
        first = await graph.ainvoke({"raw_event": raw_event()}, config)
        second = await graph.ainvoke({"raw_event": raw_event()}, config)
        return first, second

    first, second = asyncio.run(run_twice())

    assert first["usage"].input_tokens == 3100
    assert first["usage"].turns == 20
    assert second["outcome"] is Outcome.ALREADY_HANDLED
    assert second["usage"] == Usage()


def test_triage_receives_source_excerpts_from_the_base_branch(harness: Harness):
    harness.run()

    [(_, excerpts)] = harness.triager.calls
    assert list(excerpts) == [APP_FILE]
    assert "19: line 19" in excerpts[APP_FILE]


def test_duplicate_run_with_the_same_fingerprint_only_notes_the_occurrence_once(harness: Harness):
    harness.run()
    second = harness.run()
    third = harness.run()

    assert second["outcome"] is Outcome.ALREADY_HANDLED and third["outcome"] is Outcome.ALREADY_HANDLED
    assert len(harness.github.issues) == 1 and len(harness.github.pulls) == 1
    assert len(harness.triager.calls) == 1
    [issue] = harness.github.issues.values()
    assert len(harness.comments(issue.number)) == 1


def test_new_occurrences_on_an_open_pr_add_one_comment_each(harness: Harness):
    harness.run()
    harness.run(last_seen="2026-11-08T00:00:00Z")
    harness.run(last_seen="2026-11-09T00:00:00Z")

    [issue] = harness.github.issues.values()
    assert len(harness.comments(issue.number)) == 2


def test_reusing_the_thread_id_does_not_leak_state_between_runs(harness: Harness):
    harness.run()
    state = harness.run()

    assert state["repro"] is None
    assert state["fix"] is None
    assert state["fix_attempts"] == 0
    assert state["final_node"] == "check_existing_work"


def test_regression_reopens_the_same_issue_and_clears_terminal_labels(harness: Harness):
    number = harness.seed_issue(state="closed", labels=[AGENT_LABEL, AUTO_LABEL, NOT_REPRODUCED_LABEL])
    harness.github.seed_pull(head=branch_name(FINGERPRINT), state="closed", merged=True)

    state = harness.run()

    assert state["outcome"] is Outcome.PR_OPENED
    issue = harness.github.issues[number]
    assert issue.state == "open"
    assert NOT_REPRODUCED_LABEL not in issue.labels
    assert len([i for i in harness.github.issues.values()]) == 1
    assert any("reopened" in body for body in harness.comments(number))


def test_rejected_pr_is_never_retried(harness: Harness):
    number = harness.seed_issue()
    harness.github.seed_pull(head=branch_name(FINGERPRINT), state="closed", merged=False)

    state = harness.run()

    assert state["outcome"] is Outcome.ALREADY_HANDLED
    assert harness.triager.calls == []
    assert harness.reproducer.calls == 0
    [comment] = harness.comments(number)
    assert "closed without merging" in comment


def test_at_capacity_stops_before_any_llm_call(tmp_path: Path):
    harness = Harness(tmp_path, max_open_agent_prs=2)
    harness.github.seed_pull(head="agent/fix-aaaa")
    harness.github.seed_pull(head="agent/fix-bbbb")

    state = harness.run()

    assert state["outcome"] is Outcome.AT_CAPACITY
    assert harness.triager.calls == []
    assert harness.github.writes == []


def test_open_issue_already_handled_by_a_human_is_not_triaged_again(harness: Harness):
    number = harness.seed_issue(labels=[AGENT_LABEL, NEEDS_HUMAN_LABEL])

    state = harness.run()

    assert state["outcome"] is Outcome.ALREADY_HANDLED
    assert harness.triager.calls == []
    assert len(harness.comments(number)) == 1


def test_issues_not_created_by_the_bot_are_ignored(harness: Harness):
    harness.github.seed_issue(body=f"{marker(FINGERPRINT)}\nspoofed", author="someone-else", labels=[AGENT_LABEL, NEEDS_HUMAN_LABEL])
    harness.github.seed_issue(body=f"spoof\n{marker(FINGERPRINT)}", labels=[AGENT_LABEL, NEEDS_HUMAN_LABEL])

    state = harness.run()

    assert state["outcome"] is Outcome.PR_OPENED
    assert len(harness.github.issues) == 3


def test_low_confidence_labels_the_issue_for_a_human(harness: Harness):
    harness.triager.result = triage_result(confidence=0.4)

    state = harness.run()

    assert state["outcome"] is Outcome.NEEDS_HUMAN
    [issue] = harness.github.issues.values()
    assert NEEDS_HUMAN_LABEL in issue.labels
    assert harness.reproducer.calls == 0
    assert harness.workspace.prepared == []


def test_non_code_bugs_go_to_a_human(harness: Harness):
    harness.triager.result = triage_result(is_code_bug=False)

    assert harness.run()["outcome"] is Outcome.NEEDS_HUMAN


def test_triage_failure_goes_to_a_human_with_the_reason(harness: Harness):
    harness.triager.fail = "agent stopped: error_max_turns"

    state = harness.run()

    assert state["outcome"] is Outcome.NEEDS_HUMAN
    assert state["reason"] == "agent stopped: error_max_turns"


def test_repro_test_passing_on_the_base_branch_stops_the_run(harness: Harness):
    harness.workspace.repro_report = report(passing_case())

    state = harness.run()

    assert state["outcome"] is Outcome.NOT_REPRODUCED
    assert "passed on the base branch" in state["reason"]
    assert harness.fixer.feedback == []
    [issue] = harness.github.issues.values()
    assert NOT_REPRODUCED_LABEL in issue.labels
    assert any("Could not reproduce" in body for body in harness.comments(issue.number))


def test_repro_failing_for_a_different_reason_stops_the_run(harness: Harness):
    harness.workspace.repro_report = report(failing_case(error_type="Mockery\\Exception\\InvalidCountException"))

    state = harness.run()

    assert state["outcome"] is Outcome.NOT_REPRODUCED
    assert "different reason" in state["reason"]


def test_repro_session_that_changes_other_files_is_rejected(tmp_path: Path):
    harness = Harness(tmp_path)
    harness.reproducer = FakeReproducer(harness.workspace, extra_files={"tests/TestCase.php": "<?php // tampered"})

    state = harness.run()

    assert state["outcome"] is Outcome.NOT_REPRODUCED
    assert "other than one new test" in state["reason"]


def test_repro_agent_error_is_reported(tmp_path: Path):
    harness = Harness(tmp_path)
    harness.reproducer = FakeReproducer(harness.workspace, error="agent stopped: error_max_turns")

    state = harness.run()

    assert state["outcome"] is Outcome.NOT_REPRODUCED
    assert state["usage"].turns == 17


def test_agent_editing_the_repro_test_fails_verification_after_one_retry(tmp_path: Path):
    harness = Harness(tmp_path)
    harness.fixer = FakeFixer(harness.workspace, [{APP_FILE: "<?php // fixed", TEST_PATH: "<?php // weakened"}])

    state = harness.run()

    assert state["outcome"] is Outcome.VERIFY_FAILED
    assert state["fix_attempts"] == 2
    assert "reproduction test unchanged" in state["reason"]
    assert harness.github.pulls == {}
    [issue] = harness.github.issues.values()
    assert VERIFY_FAILED_LABEL in issue.labels


def test_verify_failure_is_retried_once_with_the_failure_output(tmp_path: Path):
    harness = Harness(tmp_path)
    broken = report(failing_case(path="tests/Feature/OtherTest.php"), passing_case())
    fixed = report(passing_case(), passing_case("tests/Feature/OtherTest.php", "test_other"))
    harness.workspace.suite_reports = [broken, fixed]

    state = harness.run()

    assert state["outcome"] is Outcome.PR_OPENED
    assert state["fix_attempts"] == 2
    first, second = harness.fixer.feedback
    assert first is None
    assert second is not None and "full test suite" in second


@pytest.mark.parametrize(
    ("workspace_changes", "failed_check"),
    [
        ({"pint_result": (False, "style issues")}, "pint --test"),
        ({"larastan_result": (False, "1 error")}, "larastan"),
        ({"diff": 301}, "diff within 300 lines"),
    ],
)
def test_each_deterministic_check_can_fail_verification(tmp_path: Path, workspace_changes: dict[str, Any], failed_check: str):
    harness = Harness(tmp_path)
    for key, value in workspace_changes.items():
        setattr(harness.workspace, key, value)

    state = harness.run()

    assert state["outcome"] is Outcome.VERIFY_FAILED
    assert failed_check in state["reason"]


def test_fix_outside_the_allowlist_fails_verification(tmp_path: Path):
    harness = Harness(tmp_path)
    harness.fixer = FakeFixer(harness.workspace, [{"config/services.php": "<?php // changed"}])

    state = harness.run()

    assert state["outcome"] is Outcome.VERIFY_FAILED
    assert "only allowed paths changed" in state["reason"]


def test_fix_session_error_skips_the_checks_and_retries(tmp_path: Path):
    harness = Harness(tmp_path)
    harness.fixer = FakeFixer(harness.workspace, error="timed out after 900s")

    state = harness.run()

    assert state["outcome"] is Outcome.VERIFY_FAILED
    assert harness.workspace.suite_runs == 0
    assert len(harness.fixer.feedback) == 2


def test_open_issue_without_terminal_label_is_updated_not_duplicated(harness: Harness):
    number = harness.seed_issue(labels=[AGENT_LABEL, AUTO_LABEL])

    state = harness.run()

    assert state["outcome"] is Outcome.PR_OPENED
    assert len(harness.github.issues) == 1
    assert harness.github.issues[number].body.startswith(marker(FINGERPRINT))


def test_graph_command_prints_every_node_as_mermaid(capsys: pytest.CaptureFixture[str]):
    from triage_agent.cli import main

    assert main(["graph"]) == 0
    output = capsys.readouterr().out
    for node in ("normalize_event", "check_existing_work", "triage", "upsert_issue", "reproduce", "fix", "verify", "open_pr", "report_outcome"):
        assert f"\t{node}(" in output
    assert "verify -.-> fix;" in output
