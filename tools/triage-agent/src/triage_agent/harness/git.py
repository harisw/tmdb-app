import base64
import os
import hashlib
import subprocess
from collections.abc import Mapping
from dataclasses import dataclass
from pathlib import Path
from triage_agent.github.base import AGENT_BRANCH_PREFIX

AGENT_IDENTITY = ("-c", "user.name=triage-agent", "-c", "user.email=triage-agent@users.noreply.github.com")


class GitError(RuntimeError):
    pass


@dataclass(frozen=True)
class Change:
    path: str
    status: str


def git(cwd: Path, *args: str, check: bool = True, env: Mapping[str, str] | None = None) -> str:
    completed = subprocess.run(["git", *args], cwd=cwd, capture_output=True, text=True, env={**os.environ, **env} if env else None)
    if check and completed.returncode != 0:
        raise GitError(f"git {' '.join(args)} failed: {completed.stderr.strip()}")
    return completed.stdout


def create_worktree(repo: Path, path: Path, ref: str) -> Path:
    if path.exists():
        git(repo, "worktree", "remove", "--force", str(path), check=False)
    git(repo, "worktree", "prune")
    path.parent.mkdir(parents=True, exist_ok=True)
    git(repo, "worktree", "add", "--detach", str(path), ref)
    return path


def remove_worktree(repo: Path, path: Path) -> None:
    git(repo, "worktree", "remove", "--force", str(path), check=False)


def show_file(repo: Path, ref: str, path: str) -> str | None:
    completed = subprocess.run(["git", "show", f"{ref}:{path}"], cwd=repo, capture_output=True, text=True)
    return completed.stdout if completed.returncode == 0 else None


def changes(worktree: Path) -> list[Change]:
    output = git(worktree, "status", "--porcelain=v1", "-z", "--untracked-files=all")
    entries = [entry for entry in output.split("\0") if entry]
    result: list[Change] = []
    skip_next = False
    for entry in entries:
        if skip_next:
            skip_next = False
            continue
        status, path = entry[:2], entry[3:]
        if "R" in status or "C" in status:
            skip_next = True
        result.append(Change(path=path, status=status.strip() or status))
    return result


def diff_line_count(worktree: Path) -> int:
    git(worktree, "add", "--all", "--intent-to-add")
    total = 0
    for line in git(worktree, "diff", "--numstat", "HEAD").splitlines():
        added, removed, _ = line.split("\t", 2)
        total += (int(added) if added.isdigit() else 0) + (int(removed) if removed.isdigit() else 0)
    return total


def file_hash(path: Path) -> str | None:
    return hashlib.sha256(path.read_bytes()).hexdigest() if path.is_file() else None


def commit_to_branch(worktree: Path, branch: str, message: str) -> str:
    git(worktree, "switch", "--force-create", branch)
    git(worktree, "add", "--all")
    if git(worktree, "status", "--porcelain").strip():
        git(worktree, *AGENT_IDENTITY, "commit", "--no-verify", "--message", message)
    return git(worktree, "rev-parse", "HEAD").strip()


def push_branch(worktree: Path, repo: str, branch: str, token: str) -> None:
    if not branch.startswith(AGENT_BRANCH_PREFIX):
        raise GitError(f"refusing to push {branch}: only {AGENT_BRANCH_PREFIX}* branches can be pushed")
    credentials = base64.b64encode(f"x-access-token:{token}".encode()).decode()
    auth = {
        "GIT_CONFIG_COUNT": "1",
        "GIT_CONFIG_KEY_0": "http.https://github.com/.extraheader",
        "GIT_CONFIG_VALUE_0": f"AUTHORIZATION: basic {credentials}",
        "GIT_TERMINAL_PROMPT": "0",
    }
    git(worktree, "push", "--force", "--no-verify", f"https://github.com/{repo}.git", f"HEAD:refs/heads/{branch}", env=auth)