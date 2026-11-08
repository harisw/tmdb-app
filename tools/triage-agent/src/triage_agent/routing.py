from dataclasses import dataclass
from enum import StrEnum

from triage_agent.github.client import TERMINAL_LABELS, Issue, Pull

REPORT_OUTCOME = "report_outcome"


class Action(StrEnum):
    CONTINUE = "continue"
    REOPEN_AND_CONTINUE = "reopen_and_continue"
    NOTE_OCCURRENCE = "note_occurrence"
    NOTE_REJECTED = "note_rejected"
    AT_CAPACITY = "at_capacity"


@dataclass(frozen=True)
class ExistingWorkDecision:
    action: Action
    reason: str
    issue_number: int | None = None
    pull: Pull | None = None

    @property
    def proceeds(self) -> bool:
        return self.action in (Action.CONTINUE, Action.REOPEN_AND_CONTINUE)


def decide_existing_work(issue: Issue | None, pulls: list[Pull], open_agent_pulls: int, max_open_agent_pulls: int) -> ExistingWorkDecision:
    number = issue.number if issue else None
    latest_pull = max(pulls, key=lambda pull: pull.number) if pulls else None

    if latest_pull and latest_pull.state == "closed" and not latest_pull.merged:
        return ExistingWorkDecision(Action.NOTE_REJECTED, "agent PR was closed without merging; never retried", number, latest_pull)
    if latest_pull and latest_pull.state == "open":
        return ExistingWorkDecision(Action.NOTE_OCCURRENCE, "agent PR already open", number, latest_pull)
    if issue and issue.state == "open" and issue.labels & TERMINAL_LABELS:
        labels = ", ".join(sorted(issue.labels & TERMINAL_LABELS))
        return ExistingWorkDecision(Action.NOTE_OCCURRENCE, f"issue already handled ({labels})", number)
    if open_agent_pulls >= max_open_agent_pulls:
        return ExistingWorkDecision(Action.AT_CAPACITY, f"{open_agent_pulls} agent PRs open (max {max_open_agent_pulls})", number)
    if issue and issue.state == "closed":
        return ExistingWorkDecision(Action.REOPEN_AND_CONTINUE, "closed issue recurred", number)
    return ExistingWorkDecision(Action.CONTINUE, "no open work for this fingerprint", number)


def needs_human(is_code_bug: bool, confidence: float, threshold: float) -> bool:
    return not is_code_bug or confidence < threshold


def after_verify(passed: bool, fix_attempts: int, max_fix_attempts: int) -> str:
    if passed:
        return "open_pr"
    return "fix" if fix_attempts < max_fix_attempts else REPORT_OUTCOME
