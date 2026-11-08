import asyncio
import base64
import os
import shutil
import subprocess
import time
from pathlib import Path
from typing import Protocol

from triage_agent.config import Settings
from triage_agent.harness import git
from triage_agent.harness.git import Change
from triage_agent.harness.junit import TestReport, parse_junit

TOOL_ROOT = Path(__file__).resolve().parents[3]
IMAGE = "triage-test-runner:php8.4"
POSTGRES_IMAGE = "pgvector/pgvector:pg17"
DB_ENV = {"DB_HOST": "db", "DB_PORT": "5432", "DB_DATABASE": "tmdb_test", "DB_USERNAME": "user", "DB_PASSWORD": "password"}
MAX_OUTPUT = 20_000


class HarnessError(RuntimeError):
    pass


class Workspace(Protocol):
    def prepare(self, fingerprint: str) -> Path: ...

    def read_base_file(self, path: str) -> str | None: ...

    async def run_tests(self, worktree: Path, test_path: str | None = None) -> TestReport: ...

    async def pint(self, worktree: Path, paths: list[str]) -> tuple[bool, str]: ...

    async def larastan(self, worktree: Path) -> tuple[bool, str] | None: ...

    def changes(self, worktree: Path) -> list[Change]: ...

    def diff_lines(self, worktree: Path) -> int: ...

    def file_hash(self, worktree: Path, path: str) -> str | None: ...

    def commit(self, worktree: Path, branch: str, message: str) -> str: ...

    def close(self) -> None: ...


class DockerWorkspace:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._app_key = "base64:" + base64.b64encode(os.urandom(32)).decode()
        self._user = f"{os.getuid()}:{os.getgid()}"
        self._network: str | None = None
        self._database: str | None = None
        self._runs = 0

    def prepare(self, fingerprint: str) -> Path:
        work_dir = self._settings.work_dir
        worktree = git.create_worktree(self._settings.repo_path, work_dir / "worktrees" / fingerprint, self._settings.base_ref)
        shutil.copyfile(worktree / ".env.example", worktree / ".env")
        self._build_image()
        cache = work_dir / "composer-cache"
        cache.mkdir(parents=True, exist_ok=True)
        self._docker(
            "run", "--rm", "--user", self._user,
            "-v", f"{worktree}:/app", "-v", f"{cache}:/composer",
            "-e", "COMPOSER_HOME=/composer", "-e", "HOME=/tmp",
            IMAGE, "composer", "install", "--no-interaction", "--no-progress", "--prefer-dist",
            timeout=900,
        )
        self._start_database(fingerprint)
        return worktree

    def read_base_file(self, path: str) -> str | None:
        return git.show_file(self._settings.repo_path, self._settings.base_ref, path)

    async def run_tests(self, worktree: Path, test_path: str | None = None) -> TestReport:
        return await asyncio.to_thread(self._run_tests, worktree, test_path)

    async def pint(self, worktree: Path, paths: list[str]) -> tuple[bool, str]:
        if not paths:
            return True, "no PHP files changed"
        return await asyncio.to_thread(self._isolated, worktree, ["vendor/bin/pint", "--test", *paths])

    async def larastan(self, worktree: Path) -> tuple[bool, str] | None:
        if not (worktree / "phpstan.neon").exists():
            return None
        command = ["vendor/bin/phpstan", "analyse", "--no-progress", "--memory-limit=1G", "--error-format=raw"]
        return await asyncio.to_thread(self._isolated, worktree, command)

    def changes(self, worktree: Path) -> list[Change]:
        return git.changes(worktree)

    def diff_lines(self, worktree: Path) -> int:
        return git.diff_line_count(worktree)

    def file_hash(self, worktree: Path, path: str) -> str | None:
        return git.file_hash(worktree / path)

    def commit(self, worktree: Path, branch: str, message: str) -> str:
        return git.commit_to_branch(worktree, branch, message)

    def close(self) -> None:
        if self._database:
            self._docker("rm", "--force", self._database, check=False)
        if self._network:
            self._docker("network", "rm", self._network, check=False)

    def _build_image(self) -> None:
        self._docker("build", "--quiet", "--tag", IMAGE, "--file", str(TOOL_ROOT / "docker" / "test-runner.Dockerfile"), str(TOOL_ROOT / "docker"), timeout=1200)

    def _start_database(self, fingerprint: str) -> None:
        self._network = f"triage-net-{fingerprint}"
        self._database = f"triage-db-{fingerprint}"
        self.close()
        self._docker("network", "create", "--internal", self._network)
        self._docker(
            "run", "--detach", "--name", self._database, "--network", self._network, "--network-alias", "db",
            "--tmpfs", "/var/lib/postgresql/data",
            "-e", "POSTGRES_DB=tmdb_test", "-e", "POSTGRES_USER=user", "-e", "POSTGRES_PASSWORD=password",
            POSTGRES_IMAGE,
        )
        deadline = time.monotonic() + 90
        while time.monotonic() < deadline:
            ready = self._docker("exec", self._database, "pg_isready", "-h", "127.0.0.1", "-U", "user", "-d", "tmdb_test", check=False)
            if ready.returncode == 0:
                return
            time.sleep(1)
        raise HarnessError("Postgres did not become ready within 90s")

    def _run_tests(self, worktree: Path, test_path: str | None) -> TestReport:
        self._runs += 1
        out_dir = self._settings.work_dir / "reports" / f"{worktree.name}-{self._runs}"
        out_dir.mkdir(parents=True, exist_ok=True)
        junit = out_dir / "junit.xml"
        command = ["php", "-d", "memory_limit=1G", "vendor/bin/phpunit", "--log-junit", "/out/junit.xml", *([test_path] if test_path else [])]
        completed = self._isolated_run(worktree, command, extra_mounts=["-v", f"{out_dir}:/out"])
        output = (completed.stdout + completed.stderr)[-MAX_OUTPUT:]
        if not junit.exists():
            return TestReport(exit_code=completed.returncode or 1, output=output)
        return parse_junit(junit.read_text(), exit_code=completed.returncode, output=output)

    def _isolated(self, worktree: Path, command: list[str]) -> tuple[bool, str]:
        completed = self._isolated_run(worktree, command)
        return completed.returncode == 0, (completed.stdout + completed.stderr)[-MAX_OUTPUT:]

    def _isolated_run(self, worktree: Path, command: list[str], extra_mounts: list[str] | None = None) -> subprocess.CompletedProcess[str]:
        if not self._network:
            raise HarnessError("prepare() must run before tests")
        name = f"triage-run-{worktree.name}-{self._runs}-{int(time.time())}"
        env = {**DB_ENV, "APP_KEY": self._app_key, "HOME": "/tmp"}
        env_args = [arg for key, value in env.items() for arg in ("-e", f"{key}={value}")]
        args = [
            "run", "--rm", "--name", name, "--user", self._user, "--network", self._network,
            "--cap-drop", "ALL", "--security-opt", "no-new-privileges", "--pids-limit", "1024", "--memory", "3g", "--cpus", "2",
            "-v", f"{worktree}:/app", *(extra_mounts or []), *env_args, IMAGE, *command,
        ]
        try:
            return self._docker(*args, check=False, timeout=self._settings.test_timeout_seconds)
        except subprocess.TimeoutExpired:
            self._docker("kill", name, check=False)
            return subprocess.CompletedProcess(args, 124, "", f"timed out after {self._settings.test_timeout_seconds:.0f}s")

    def _docker(self, *args: str, check: bool = True, timeout: float = 120) -> subprocess.CompletedProcess[str]:
        completed = subprocess.run(["docker", *args], capture_output=True, text=True, timeout=timeout)
        if check and completed.returncode != 0:
            raise HarnessError(f"docker {args[0]} failed: {completed.stderr.strip()[-2000:]}")
        return completed
