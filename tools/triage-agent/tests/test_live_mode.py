import asyncio
from dataclasses import replace
from pathlib import Path
from types import SimpleNamespace
from typing import Any

import pytest
from fakes import BOT, REPO, FakeFixer, FakeReproducer, FakeTriager, FakeWorkspace, passing_case, raw_event, report, settings
from langgraph.types import RetryPolicy

from triage_agent import cli, graph as graph_module
from triage_agent.agents.runner import stop_details
from triage_agent.cli import build_github, execute
from triage_agent.config import ConfigError, Settings
from triage_agent.github.base import GitHubTransientError, Issue
from triage_agent.github.memory import InMemoryGitHub
from triage_agent.graph import Deps
from triage_agent.models import Outcome

FINGERPRINT = "d96aebf547693359"


class FlakyGitHub(InMemoryGitHub):
    def __init__(self) -> None:
        super().__init__(REPO, BOT)
        self.lost_create_responses = 1
        self.failed_comments = 1

    def create_issue(self, title: str, body: str, labels: list[str]) -> Issue:
        issue = super().create_issue(title, body, labels)
        if self.lost_create_responses:
            self.lost_create_responses -= 1
            raise GitHubTransientError("created, but the response was lost")
        return issue

    def add_comment(self, number: int, body: str) -> None:
        if self.failed_comments:
            self.failed_comments -= 1
            raise GitHubTransientError("GitHub returned 502")
        super().add_comment(number, body)


def run_with(tmp_path: Path, github: InMemoryGitHub, **overrides: Any) -> tuple[Any, FakeWorkspace, FakeReproducer]:
    workspace = FakeWorkspace()
    reproducer = FakeReproducer(workspace)
    deps = Deps(settings(tmp_path, **overrides), github, FakeTriager(), reproducer, FakeFixer(workspace), workspace)
    return asyncio.run(execute(deps, raw_event())), workspace, reproducer


@pytest.fixture(autouse=True)
def instant_retries(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(graph_module, "GITHUB_RETRY", RetryPolicy(max_attempts=3, initial_interval=0, jitter=False, retry_on=GitHubTransientError))


def test_lost_create_response_does_not_create_a_second_issue(tmp_path: Path):
    github = FlakyGitHub()
    github.failed_comments = 0

    state, _, _ = run_with(tmp_path, github)

    assert state["outcome"] is Outcome.PR_OPENED
    assert len(github.issues) == 1


def test_transient_comment_failure_retries_only_the_reporting_step(tmp_path: Path):
    github = FlakyGitHub()
    github.lost_create_responses = 0
    workspace = FakeWorkspace()
    workspace.repro_report = report(passing_case())
    reproducer = FakeReproducer(workspace)
    deps = Deps(settings(tmp_path), github, FakeTriager(), reproducer, FakeFixer(workspace), workspace)

    state = asyncio.run(execute(deps, raw_event()))

    assert state["outcome"] is Outcome.NOT_REPRODUCED
    assert reproducer.calls == 1
    [issue] = github.issues.values()
    assert len(github.comments(issue.number)) == 1


def test_live_run_pushes_the_branch_before_opening_the_pr(tmp_path: Path):
    github = InMemoryGitHub(REPO, BOT)

    state, workspace, _ = run_with(tmp_path, github, dry_run=False)

    assert state["outcome"] is Outcome.PR_OPENED
    assert workspace.pushes == [f"agent/fix-{FINGERPRINT}"]


def test_dry_run_never_pushes(tmp_path: Path):
    _, workspace, _ = run_with(tmp_path, InMemoryGitHub(REPO, BOT))

    assert workspace.pushes == []


def test_build_github_rejects_a_token_for_a_different_account(monkeypatch: pytest.MonkeyPatch, tmp_path: Path):
    monkeypatch.setattr(cli, "RestGitHub", lambda repo, token: SimpleNamespace(authenticated_login=lambda: "someone-else"))
    live = replace(settings(tmp_path, dry_run=False), github_token="secret")

    with pytest.raises(ConfigError, match="someone-else"):
        build_github(live, None)


def test_build_github_stays_in_memory_for_dry_runs(tmp_path: Path):
    assert isinstance(build_github(settings(tmp_path), None), InMemoryGitHub)


def test_stop_details_name_the_stop_reason_and_denied_tools():
    result = SimpleNamespace(stop_reason="end_turn", permission_denials=[{"tool_name": "Write"}, {"tool_name": "Bash"}, {"tool_name": "Write"}])

    assert stop_details(result) == "stop_reason=end_turn, denied_tools=Bash,Write"


def test_stop_details_handle_no_denials():
    assert stop_details(SimpleNamespace(stop_reason=None, permission_denials=None)) == "stop_reason=None, denied_tools=none"


def test_settings_repr_hides_the_github_token(tmp_path: Path):
    live = Settings(repo_path=tmp_path, github_repo=REPO, github_token="secret-token", bot_login=BOT)

    assert "secret-token" not in repr(live)
