import hashlib
import json
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

from triage_agent.agents.sessions import FixAttempt, ReproAttempt
from triage_agent.config import Settings
from triage_agent.harness.git import Change
from triage_agent.harness.junit import Problem, TestCase, TestReport
from triage_agent.llm.triage import TriageFailed
from triage_agent.models import ErrorEvent, Fix, Repro, TriageResult, Usage

FIXTURE = Path(__file__).resolve().parents[1] / "fixtures" / "process-watch-providers.json"
BOT = "triage-bot"
REPO = "harisw/tmdb-app"
TEST_PATH = "tests/Feature/Pipelines/ProcessWatchProvidersTest.php"
APP_FILE = "app/Pipelines/Stages/ProcessWatchProviders.php"
EXPECTED_TRACE = f"ErrorException: Undefined array key \"results\"\n\n/app/{APP_FILE}:19\n/app/{TEST_PATH}:30"


def raw_event(**overrides: Any) -> dict[str, Any]:
    return {**json.loads(FIXTURE.read_text()), **overrides}


def settings(tmp_path: Path, **overrides: Any) -> Settings:
    return Settings(
        repo_path=tmp_path,
        github_repo=REPO,
        github_token=None,
        bot_login=BOT,
        work_dir=tmp_path / "work",
        runs_log=tmp_path / "runs.jsonl",
        **overrides,
    )


def triage_result(**overrides: Any) -> TriageResult:
    values = {
        "is_code_bug": True,
        "likely_area": "TMDB watch provider enrichment",
        "suspected_files": [APP_FILE],
        "summary": "The stage reads ['results'] without checking the TMDB response.",
        "confidence": 0.9,
    }
    return TriageResult(**{**values, **overrides})


def report(*cases: TestCase, exit_code: int | None = None) -> TestReport:
    failed = any(case.problem for case in cases)
    return TestReport(cases=cases, exit_code=(1 if failed else 0) if exit_code is None else exit_code)


def failing_case(error_type: str = "ErrorException", trace: str = EXPECTED_TRACE, path: str = TEST_PATH) -> TestCase:
    return TestCase("test_skips_titles_without_provider_results", f"/app/{path}", Problem("error", error_type, trace))


def passing_case(path: str = TEST_PATH, name: str = "test_skips_titles_without_provider_results") -> TestCase:
    return TestCase(name, f"/app/{path}")


class FakeTriager:
    def __init__(self, result: TriageResult | None = None, *, fail: str | None = None) -> None:
        self.result = result or triage_result()
        self.fail = fail
        self.calls: list[tuple[ErrorEvent, dict[str, str]]] = []

    async def triage(self, event: ErrorEvent, source_excerpts: dict[str, str]) -> tuple[TriageResult, Usage]:
        self.calls.append((event, source_excerpts))
        if self.fail:
            raise TriageFailed(self.fail, Usage(input_tokens=5, turns=1))
        return self.result, Usage(input_tokens=100, output_tokens=20, api_equivalent_usd=0.01, turns=2)


@dataclass
class FakeWorkspace:
    repro_report: TestReport = field(default_factory=lambda: report(failing_case()))
    suite_reports: list[TestReport] = field(default_factory=lambda: [report(passing_case(), passing_case("tests/Feature/OtherTest.php", "test_other"))])
    pint_result: tuple[bool, str] = (True, "")
    larastan_result: tuple[bool, str] | None = (True, "")
    diff: int = 20
    base_files: dict[str, str] = field(default_factory=lambda: {APP_FILE: "\n".join(f"line {n}" for n in range(1, 40))})
    files: dict[str, str] = field(default_factory=dict)
    prepared: list[str] = field(default_factory=list)
    commits: list[tuple[str, str]] = field(default_factory=list)
    pushes: list[str] = field(default_factory=list)
    suite_runs: int = 0

    def prepare(self, fingerprint: str) -> Path:
        self.prepared.append(fingerprint)
        self.files = {}
        return Path(f"/work/{fingerprint}")

    def read_base_file(self, path: str) -> str | None:
        return self.base_files.get(path)

    async def run_tests(self, worktree: Path, test_path: str | None = None) -> TestReport:
        if test_path is not None:
            return self.repro_report
        self.suite_runs += 1
        return self.suite_reports[min(self.suite_runs, len(self.suite_reports)) - 1]

    async def pint(self, worktree: Path, paths: list[str]) -> tuple[bool, str]:
        return self.pint_result

    async def larastan(self, worktree: Path) -> tuple[bool, str] | None:
        return self.larastan_result

    def changes(self, worktree: Path) -> list[Change]:
        return [Change(path, "??") for path in sorted(self.files)]

    def diff_lines(self, worktree: Path) -> int:
        return self.diff

    def file_hash(self, worktree: Path, path: str) -> str | None:
        content = self.files.get(path)
        return hashlib.sha256(content.encode()).hexdigest() if content is not None else None

    def read_file(self, worktree: Path, path: str) -> str | None:
        return self.files.get(path)

    def commit(self, worktree: Path, branch: str, message: str) -> str:
        self.commits.append((branch, message))
        return "abc123"

    def push(self, worktree: Path, branch: str) -> None:
        self.pushes.append(branch)

    def close(self) -> None:
        pass


class FakeReproducer:
    def __init__(self, workspace: FakeWorkspace, *, error: str | None = None, extra_files: dict[str, str] | None = None) -> None:
        self.workspace = workspace
        self.error = error
        self.extra_files = extra_files or {}
        self.calls = 0

    async def reproduce(self, event: ErrorEvent, triage: TriageResult, worktree: Path) -> ReproAttempt:
        self.calls += 1
        if self.error:
            return ReproAttempt(None, "", Usage(turns=15), self.error)
        self.workspace.files[TEST_PATH] = "<?php // repro"
        self.workspace.files.update(self.extra_files)
        return ReproAttempt(TEST_PATH, "Mocks TMDB returning no results key.", Usage(input_tokens=1000, turns=8))


class FakeFixer:
    def __init__(self, workspace: FakeWorkspace, attempts: list[dict[str, str]] | None = None, *, error: str | None = None) -> None:
        self.workspace = workspace
        self.attempts = attempts if attempts is not None else [{APP_FILE: "<?php // fixed"}]
        self.error = error
        self.feedback: list[str | None] = []

    async def fix(self, event: ErrorEvent, triage: TriageResult, repro: Repro, worktree: Path, feedback: str | None) -> FixAttempt:
        self.feedback.append(feedback)
        if self.error:
            return FixAttempt(None, Usage(turns=20), self.error)
        edits = self.attempts[min(len(self.feedback), len(self.attempts)) - 1]
        self.workspace.files.update(edits)
        return FixAttempt(
            Fix("The stage assumes TMDB always returns results.", "guard missing watch provider results", "Default to an empty list."),
            Usage(input_tokens=2000, turns=10),
        )
