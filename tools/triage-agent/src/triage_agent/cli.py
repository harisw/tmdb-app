import argparse
import asyncio
import json
import sys
import time
from pathlib import Path
from typing import Any

from triage_agent.agents.sessions import AgentSdkFixer, AgentSdkReproducer
from triage_agent.config import ConfigError, Settings, load_settings
from triage_agent.github.base import GitHub
from triage_agent.github.memory import InMemoryGitHub
from triage_agent.github.rest import RestGitHub
from triage_agent.graph import Deps, build_graph
from triage_agent.harness.workspace import DockerWorkspace
from triage_agent.llm.triage import AgentSdkTriager
from triage_agent.models import Outcome, Usage
from triage_agent.runlog import append_run, run_record
from triage_agent.sanitize import clean_event
from triage_agent.state import TriageState


async def execute(deps: Deps, raw_event: dict[str, Any]) -> TriageState:
    fingerprint = clean_event(raw_event).fingerprint
    started = time.monotonic()
    state: TriageState = TriageState(final_node="normalize_event", outcome=Outcome.ERROR, reason="")
    graph = build_graph(deps)
    config = {"configurable": {"thread_id": fingerprint}}
    try:
        state = await graph.ainvoke({"raw_event": raw_event}, config)
    except Exception as error:
        snapshot = graph.get_state(config).values
        state = TriageState(**{**snapshot, "outcome": Outcome.ERROR, "reason": f"{type(error).__name__}: {error}"})
        raise
    finally:
        _log(deps.settings, fingerprint, state, time.monotonic() - started)
    return state


def _log(settings: Settings, fingerprint: str, state: TriageState, duration: float) -> None:
    issue, pr = state.get("issue"), state.get("pr")
    record = run_record(
        fingerprint=fingerprint,
        final_node=state.get("final_node", "normalize_event"),
        outcome=state.get("outcome") or Outcome.ERROR,
        reason=state.get("reason", ""),
        usage=state.get("usage") or Usage(),
        duration_seconds=duration,
        pr_url=pr.url if pr and not settings.dry_run else None,
        issue_url=issue.url if issue and not settings.dry_run else None,
        dry_run=settings.dry_run,
    )
    append_run(settings.runs_log, record)


def build_github(settings: Settings, state_file: Path | None) -> GitHub:
    if settings.dry_run:
        if state_file:
            return InMemoryGitHub.from_state_file(state_file, settings.github_repo, settings.bot_login, echo=print)
        return InMemoryGitHub(settings.github_repo, settings.bot_login, echo=print)

    assert settings.github_token is not None
    github = RestGitHub(settings.github_repo, settings.github_token)
    login = github.authenticated_login()
    if login != settings.bot_login:
        raise ConfigError(f"TRIAGE_BOT_LOGIN is {settings.bot_login!r} but the token belongs to {login!r}; later runs would not find their own issues.")
    return github


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="triage-agent", description="Turn a production error into an issue and a verified draft fix.")
    subcommands = parser.add_subparsers(dest="command", required=True)
    run = subcommands.add_parser("run", help="Run the workflow for one error event")
    run.add_argument("--fixture", type=Path, required=True, help="Error event JSON file")
    run.add_argument("--github-state", type=Path, help="Seed the dry-run GitHub with issues and PRs from a JSON file")
    run.add_argument("--live", action="store_true", help="Write to real GitHub instead of printing what would happen")
    subcommands.add_parser("graph", help="Print the workflow graph as a Mermaid diagram")
    args = parser.parse_args(argv)

    if args.command == "graph":
        print(build_graph(Deps(None, None, None, None, None, None)).get_graph().draw_mermaid())  # type: ignore[arg-type]
        return 0

    if args.live and args.github_state:
        parser.error("--live and --github-state cannot be combined")

    try:
        settings = load_settings(dry_run=not args.live)
        github = build_github(settings, args.github_state)
    except ConfigError as error:
        print(f"error: {error}", file=sys.stderr)
        return 2

    raw_event = json.loads(args.fixture.read_text())
    workspace = DockerWorkspace(settings)
    deps = Deps(
        settings=settings,
        github=github,
        triager=AgentSdkTriager(settings),
        reproducer=AgentSdkReproducer(settings, workspace.run_tests),
        fixer=AgentSdkFixer(settings, workspace.run_tests),
        workspace=workspace,
    )
    try:
        state = asyncio.run(execute(deps, raw_event))
    finally:
        workspace.close()

    worktree = state.get("worktree")
    print(f"outcome: {state.get('outcome')} at {state.get('final_node')}" + (f" ({state.get('reason')})" if state.get("reason") else ""))
    if worktree:
        print(f"worktree: {worktree}")
    return 0
