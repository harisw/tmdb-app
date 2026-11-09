import pytest

from triage_agent.github.base import AGENT_LABEL, NEEDS_HUMAN_LABEL, Issue, Pull
from triage_agent.routing import REPORT_OUTCOME, Action, after_verify, decide_existing_work


def issue(state: str = "open", labels: frozenset[str] = frozenset({AGENT_LABEL})) -> Issue:
    return Issue(1, "url", "t", "b", state, labels, "bot")  # type: ignore[arg-type]


def pull(number: int, state: str, merged: bool = False) -> Pull:
    return Pull(number, f"pull/{number}", "agent/fix-x", state, merged, True, "master")  # type: ignore[arg-type]


@pytest.mark.parametrize(
    ("current_issue", "pulls", "open_count", "expected"),
    [
        (None, [], 0, Action.CONTINUE),
        (issue(), [], 0, Action.CONTINUE),
        (issue(), [pull(5, "open")], 1, Action.NOTE_OCCURRENCE),
        (issue(), [pull(5, "closed")], 0, Action.NOTE_REJECTED),
        (issue("closed"), [pull(5, "closed")], 0, Action.NOTE_REJECTED),
        (issue("closed"), [pull(5, "closed", merged=True)], 0, Action.REOPEN_AND_CONTINUE),
        (issue("closed"), [pull(5, "closed"), pull(9, "closed", merged=True)], 0, Action.REOPEN_AND_CONTINUE),
        (issue(), [pull(5, "closed", merged=True), pull(9, "closed")], 0, Action.NOTE_REJECTED),
        (issue(labels=frozenset({AGENT_LABEL, NEEDS_HUMAN_LABEL})), [], 0, Action.NOTE_OCCURRENCE),
        (issue(labels=frozenset({AGENT_LABEL, NEEDS_HUMAN_LABEL})), [], 5, Action.NOTE_OCCURRENCE),
        (None, [], 2, Action.AT_CAPACITY),
        (issue("closed"), [], 2, Action.AT_CAPACITY),
        (None, [], 1, Action.CONTINUE),
    ],
)
def test_existing_work_decisions(current_issue: Issue | None, pulls: list[Pull], open_count: int, expected: Action):
    assert decide_existing_work(current_issue, pulls, open_count, 2).action is expected


@pytest.mark.parametrize(
    ("passed", "attempts", "expected"),
    [(True, 1, "open_pr"), (False, 1, "fix"), (False, 2, REPORT_OUTCOME), (True, 2, "open_pr")],
)
def test_after_verify(passed: bool, attempts: int, expected: str):
    assert after_verify(passed, attempts, 2) == expected
