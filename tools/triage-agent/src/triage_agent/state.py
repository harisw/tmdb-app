import operator
from typing import Annotated, Any, TypedDict

from langgraph.types import Overwrite

from triage_agent.models import ErrorEvent, Fix, IssueRef, Outcome, PullRef, Repro, TriageResult, Usage, VerifyResult
from triage_agent.routing import ExistingWorkDecision


class TriageState(TypedDict, total=False):
    raw_event: dict[str, Any]
    event: ErrorEvent
    decision: ExistingWorkDecision | None
    triage: TriageResult | None
    issue: IssueRef | None
    worktree: str | None
    repro: Repro | None
    fix: Fix | None
    fix_attempts: int
    verify: VerifyResult | None
    pr: PullRef | None
    final_node: str
    outcome: Outcome | None
    reason: str
    usage: Annotated[Usage, operator.add]


def fresh_run(event: ErrorEvent) -> TriageState:
    return TriageState(
        event=event,
        decision=None,
        triage=None,
        issue=None,
        worktree=None,
        repro=None,
        fix=None,
        fix_attempts=0,
        verify=None,
        pr=None,
        final_node="normalize_event",
        outcome=None,
        reason="",
        usage=Overwrite(Usage()),  # type: ignore[typeddict-item]
    )
