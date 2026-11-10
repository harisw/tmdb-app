from datetime import UTC, datetime, timedelta

import pytest

from triage_agent.github.base import AGENT_LABEL, NEEDS_HUMAN_LABEL, Issue, Pull
from triage_agent.routing import REPORT_OUTCOME, Action, after_verify, decide_existing_work


def issue(state: str = "open", labels: frozenset[str] = frozenset({AGENT_LABEL})) -> Issue:
    return Issue(1, "url", "t", "b", state, labels, "bot")  # type: ignore[arg-type]


def pull(number: int, state: str, merged: bool = False) -> Pull:
    return Pull(number, f"pull/{number}", "agent/fix-x", state, merged, True)  # type: ignore[arg-type]


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


CLOSED_AT = datetime(2026, 11, 9, 6, 19, tzinfo=UTC)


@pytest.mark.parametrize(
    ("hours_after_close", "expected"),
    [(9, Action.NOTE_OCCURRENCE), (24, Action.NOTE_OCCURRENCE), (25, Action.REOPEN_AND_CONTINUE)],
)
def test_a_closed_issue_only_reopens_after_the_fix_had_time_to_ship(hours_after_close: int, expected: Action):
    closed = Issue(1, "url", "t", "b", "closed", frozenset({AGENT_LABEL}), "bot", CLOSED_AT)

    decision = decide_existing_work(closed, [pull(5, "closed", merged=True)], 0, 2, CLOSED_AT + timedelta(hours=hours_after_close))

    assert decision.action is expected


@pytest.mark.parametrize(
    ("passed", "attempts", "expected"),
    [(True, 1, "open_pr"), (False, 1, "fix"), (False, 2, REPORT_OUTCOME), (True, 2, "open_pr")],
)
def test_after_verify(passed: bool, attempts: int, expected: str):
    assert after_verify(passed, attempts, 2) == expected
