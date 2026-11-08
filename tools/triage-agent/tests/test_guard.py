import asyncio
from pathlib import Path
from typing import Any

import pytest

from triage_agent.agents.guard import FixWritePolicy, PathGuard, ReproWritePolicy


@pytest.fixture
def repo(tmp_path: Path) -> Path:
    (tmp_path / "app").mkdir()
    (tmp_path / "tests" / "Feature").mkdir(parents=True)
    (tmp_path / "tests" / "Feature" / "ExistingTest.php").write_text("<?php")
    (tmp_path / ".env").write_text("SECRET=1")
    (tmp_path / ".env.example").write_text("SECRET=")
    (tmp_path / "app" / "link.php").symlink_to("/etc/passwd")
    return tmp_path


def decide(guard: PathGuard, tool: str, **tool_input: Any) -> str | None:
    result = asyncio.run(guard({"tool_name": tool, "tool_input": tool_input}, None, None))
    return result["hookSpecificOutput"]["permissionDecisionReason"] if result else None


def repro_guard(repo: Path) -> tuple[PathGuard, ReproWritePolicy]:
    policy = ReproWritePolicy(repo)
    return PathGuard(repo, policy), policy


@pytest.mark.parametrize(
    ("tool", "tool_input"),
    [
        ("Read", {"file_path": "/etc/passwd"}),
        ("Read", {"file_path": "app/../../outside.php"}),
        ("Read", {"file_path": ".env"}),
        ("Read", {"file_path": ".git/config"}),
        ("Read", {"file_path": "app/link.php"}),
        ("Grep", {"pattern": "x", "path": "/home"}),
        ("Grep", {"pattern": "x", "glob": "../**/*.php"}),
        ("Glob", {"pattern": "/etc/*"}),
        ("Bash", {"command": "ls"}),
        ("WebFetch", {"url": "https://example.com"}),
    ],
)
def test_denies_reads_outside_the_repo_secrets_and_unlisted_tools(repo: Path, tool: str, tool_input: dict[str, Any]):
    guard, _ = repro_guard(repo)

    assert decide(guard, tool, **tool_input) is not None


@pytest.mark.parametrize(
    ("tool", "tool_input"),
    [
        ("Read", {"file_path": "app/Models/Title.php"}),
        ("Read", {"file_path": ".env.example"}),
        ("Grep", {"pattern": "\\.\\./", "path": "app"}),
        ("Glob", {"pattern": "tests/**/*Test.php"}),
        ("mcp__harness__run_repro_test", {}),
        ("StructuredOutput", {"test_path": "tests/Feature/NewTest.php", "explanation": "x"}),
    ],
)
def test_allows_reads_inside_the_repo(repo: Path, tool: str, tool_input: dict[str, Any]):
    guard, _ = repro_guard(repo)

    assert decide(guard, tool, **tool_input) is None


def test_repro_session_may_create_exactly_one_new_test(repo: Path):
    guard, policy = repro_guard(repo)

    assert decide(guard, "Edit", file_path="tests/Feature/NewTest.php") is not None
    assert decide(guard, "Write", file_path="tests/Feature/ExistingTest.php") is not None
    assert decide(guard, "Write", file_path="tests/Feature/helpers.php") is not None
    assert decide(guard, "Write", file_path="app/Fix.php") is not None
    assert decide(guard, "Write", file_path=str(repo / "tests/Feature/NewTest.php")) is None
    assert policy.test_path == "tests/Feature/NewTest.php"
    assert decide(guard, "Edit", file_path="tests/Feature/NewTest.php") is None
    assert decide(guard, "Write", file_path="tests/Feature/SecondTest.php") is not None


@pytest.mark.parametrize(
    ("path", "allowed"),
    [
        ("app/Pipelines/Stages/ProcessWatchProviders.php", True),
        ("routes/web.php", True),
        ("tests/Feature/NewTest.php", False),
        ("tests/Feature/OtherTest.php", False),
        ("config/services.php", False),
        ("resources/js/app.js", False),
        ("database/migrations/2026_01_01_000000_x.php", False),
        (".github/workflows/deploy.yml", False),
        ("composer.lock", False),
    ],
)
def test_fix_session_writes_only_inside_the_allowlist(repo: Path, path: str, allowed: bool):
    guard = PathGuard(repo, FixWritePolicy(("app/", "routes/"), "tests/Feature/NewTest.php"))

    assert (decide(guard, "Edit", file_path=path) is None) is allowed


def test_agents_run_with_an_isolated_claude_config_and_no_auto_memory(tmp_path: Path):
    from triage_agent.agents.runner import agent_env

    env = agent_env(tmp_path / "claude-config")

    assert env == {"CLAUDE_CODE_DISABLE_AUTO_MEMORY": "1", "CLAUDE_CONFIG_DIR": str(tmp_path / "claude-config")}
    assert (tmp_path / "claude-config").is_dir()
