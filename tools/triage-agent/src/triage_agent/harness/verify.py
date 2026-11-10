from collections.abc import Sequence
from dataclasses import dataclass

from triage_agent.harness.git import Change
from triage_agent.harness.junit import TestReport
from triage_agent.models import CheckResult, VerifyResult

FORBIDDEN_PREFIXES = ("database/migrations/", ".github/", "config/", "infra/", "docker/", "tools/")
FORBIDDEN_FILES = frozenset({"composer.json", "composer.lock", "package.json", "package-lock.json", "Dockerfile", "phpunit.xml", "phpstan.neon"})
MAX_DETAIL = 2000


@dataclass(frozen=True)
class VerifyInputs:
    changes: Sequence[Change]
    repro_path: str
    repro_hash_before: str
    repro_hash_after: str | None
    diff_lines: int
    suite: TestReport
    pint: tuple[bool, str]
    larastan: tuple[bool, str] | None
    changed_text: str = ""
    forbidden_values: tuple[str, ...] = ()


def verify(inputs: VerifyInputs, *, allowed_prefixes: Sequence[str], max_diff_lines: int) -> VerifyResult:
    checks = [
        _repro_unchanged(inputs),
        _paths_allowed(inputs, allowed_prefixes),
        _diff_size(inputs, max_diff_lines),
        _no_production_values(inputs),
        _repro_ran_and_passed(inputs),
        _suite(inputs.suite),
        CheckResult("pint --test", inputs.pint[0], _clip(inputs.pint[1])),
    ]
    if inputs.larastan is not None:
        checks.append(CheckResult("larastan", inputs.larastan[0], _clip(inputs.larastan[1])))
    return VerifyResult(passed=all(check.passed for check in checks), checks=tuple(checks))


def _repro_unchanged(inputs: VerifyInputs) -> CheckResult:
    unchanged = inputs.repro_hash_after == inputs.repro_hash_before
    return CheckResult("reproduction test unchanged", unchanged, "" if unchanged else f"{inputs.repro_path} was modified or deleted")


def _paths_allowed(inputs: VerifyInputs, allowed_prefixes: Sequence[str]) -> CheckResult:
    blocked = sorted(
        change.path
        for change in inputs.changes
        if change.path != inputs.repro_path and not _allowed(change.path, allowed_prefixes)
    )
    code_changes = [change for change in inputs.changes if change.path != inputs.repro_path]
    if blocked:
        return CheckResult("only allowed paths changed", False, "outside the allowlist: " + ", ".join(blocked))
    if not code_changes:
        return CheckResult("only allowed paths changed", False, "the fix changed no files")
    return CheckResult("only allowed paths changed", True)


def _allowed(path: str, allowed_prefixes: Sequence[str]) -> bool:
    if path in FORBIDDEN_FILES or path.startswith(FORBIDDEN_PREFIXES) or ".." in path.split("/"):
        return False
    return path.startswith(tuple(allowed_prefixes))


def _diff_size(inputs: VerifyInputs, max_diff_lines: int) -> CheckResult:
    within = inputs.diff_lines <= max_diff_lines
    return CheckResult(f"diff within {max_diff_lines} lines", within, f"{inputs.diff_lines} lines changed")


def _no_production_values(inputs: VerifyInputs) -> CheckResult:
    searched = "\n".join([*(change.path for change in inputs.changes), inputs.changed_text])
    leaked = sum(1 for value in inputs.forbidden_values if value in searched)
    return CheckResult("no production values in changed files", leaked == 0, f"{leaked} value(s) from the production error appear in the changed files" if leaked else "")


def _repro_ran_and_passed(inputs: VerifyInputs) -> CheckResult:
    cases = inputs.suite.cases_in(inputs.repro_path)
    if not cases:
        return CheckResult("reproduction test passes", False, "the reproduction test did not run in the full suite")
    failing = [case.name for case in cases if not case.passed]
    return CheckResult("reproduction test passes", not failing, "still failing or skipped: " + ", ".join(failing) if failing else "")


def _suite(report: TestReport) -> CheckResult:
    if report.passed:
        return CheckResult("full test suite", True, f"{len(report.cases)} tests passed")
    failing = [f"{case.file}::{case.name}" for case in report.failed][:20]
    detail = ", ".join(failing) if failing else report.output[-MAX_DETAIL:]
    return CheckResult("full test suite", False, detail)


def _clip(text: str) -> str:
    return text[-MAX_DETAIL:]
