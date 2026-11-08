import re
from dataclasses import dataclass

from triage_agent.harness.junit import TestReport
from triage_agent.models import ErrorEvent

CONTAINER_ROOT = "/app/"
TRACE_LINE = re.compile(r"(?P<file>[^\s:]+\.php):\d+")


@dataclass(frozen=True)
class FailureCheck:
    expected: bool
    reason: str


def check_expected_failure(report: TestReport, test_path: str, event: ErrorEvent) -> FailureCheck:
    cases = report.cases_in(test_path)
    if not cases:
        return FailureCheck(False, f"no test cases from {test_path} ran")

    failed = [case for case in cases if case.problem is not None]
    if not failed:
        return FailureCheck(False, "the reproduction test passed on the base branch")

    event_files = {frame.file for frame in event.app_frames}
    for case in failed:
        problem = case.problem
        assert problem is not None
        if _short_class(problem.type) != _short_class(event.error_class):
            continue
        trace_files = {_relative(match["file"]) for match in TRACE_LINE.finditer(problem.text)}
        if (trace_files - {test_path}) & event_files:
            return FailureCheck(True, f"{case.name} failed with {event.error_class} in a frame from the event")

    seen = ", ".join(sorted({_short_class(case.problem.type) for case in failed if case.problem})) or "unknown"
    return FailureCheck(False, f"the test failed for a different reason (saw {seen}, expected {event.error_class} in an event frame)")


def _short_class(name: str) -> str:
    return name.strip().lstrip("\\").rsplit("\\", 1)[-1]


def _relative(path: str) -> str:
    return path.split(CONTAINER_ROOT, 1)[1] if CONTAINER_ROOT in path else path.lstrip("/")
