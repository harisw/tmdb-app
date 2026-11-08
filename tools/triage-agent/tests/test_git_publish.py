import subprocess
from pathlib import Path
from typing import Any

import pytest

from triage_agent.harness import git


def run_git(cwd: Path, *args: str) -> str:
    return git.git(cwd, *args)


@pytest.fixture
def repo(tmp_path: Path) -> Path:
    path = tmp_path / "repo"
    path.mkdir()
    run_git(path, "init", "-q", "-b", "master")
    run_git(path, *git.AGENT_IDENTITY, "commit", "-q", "--allow-empty", "-m", "init")
    return path


def test_create_worktree_recovers_when_the_old_directory_was_deleted(repo: Path, tmp_path: Path):
    target = tmp_path / "worktrees" / "abc"
    git.create_worktree(repo, target, "master")
    subprocess.run(["rm", "-rf", str(target)], check=True)

    assert git.create_worktree(repo, target, "master") == target
    assert target.is_dir()


def test_commit_to_branch_can_run_twice(repo: Path, tmp_path: Path):
    target = git.create_worktree(repo, tmp_path / "worktrees" / "abc", "master")
    (target / "fix.txt").write_text("fixed")

    first = git.commit_to_branch(target, "agent/fix-abc", "fix: one")
    second = git.commit_to_branch(target, "agent/fix-abc", "fix: one")

    assert first == second


def captured_push(monkeypatch: pytest.MonkeyPatch) -> list[dict[str, Any]]:
    calls: list[dict[str, Any]] = []

    def fake_run(args: list[str], **kwargs: Any) -> subprocess.CompletedProcess[str]:
        calls.append({"args": args, "env": kwargs.get("env")})
        return subprocess.CompletedProcess(args, 0, "", "")

    monkeypatch.setattr(git.subprocess, "run", fake_run)
    return calls


def test_push_keeps_the_token_out_of_the_command_line(monkeypatch: pytest.MonkeyPatch, tmp_path: Path):
    calls = captured_push(monkeypatch)

    git.push_branch(tmp_path, "harisw/tmdb-app", "agent/fix-abc", "secret-token")

    [call] = calls
    assert call["args"] == ["git", "push", "--force", "--no-verify", "https://github.com/harisw/tmdb-app.git", "HEAD:refs/heads/agent/fix-abc"]
    assert "secret-token" not in " ".join(call["args"])
    assert call["env"]["GIT_CONFIG_KEY_0"] == "http.https://github.com/.extraheader"
    assert call["env"]["GIT_CONFIG_VALUE_0"].startswith("AUTHORIZATION: basic ")
    assert call["env"]["GIT_TERMINAL_PROMPT"] == "0"


def test_push_refuses_any_branch_outside_the_agent_namespace(monkeypatch: pytest.MonkeyPatch, tmp_path: Path):
    calls = captured_push(monkeypatch)

    with pytest.raises(git.GitError):
        git.push_branch(tmp_path, "harisw/tmdb-app", "master", "secret-token")

    assert calls == []