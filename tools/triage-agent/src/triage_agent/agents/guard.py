import os
import re
from collections.abc import Callable, Sequence
from pathlib import Path, PurePosixPath
from typing import Any

from triage_agent.harness.verify import FORBIDDEN_FILES, FORBIDDEN_PREFIXES

READ_TOOLS = frozenset({"Read", "Grep", "Glob"})
WRITE_TOOLS = frozenset({"Write", "Edit"})
HARNESS_TOOL_PREFIX = "mcp__harness__"
PATH_PATTERN_KEYS = {"Glob": ("pattern",), "Grep": ("glob",)}
RESULT_TOOL = "StructuredOutput"
TEST_FILE = re.compile(r"^tests/(Feature|Unit)/[A-Za-z0-9_/]+Test\.php$")

WritePolicy = Callable[[str, str], str | None]


def deny(reason: str) -> dict[str, Any]:
    return {
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "deny",
            "permissionDecisionReason": reason,
        }
    }


class PathGuard:
    def __init__(self, root: Path, write_policy: WritePolicy) -> None:
        self.root = Path(os.path.realpath(root))
        self._write_policy = write_policy

    async def __call__(self, input_data: dict[str, Any], tool_use_id: str | None, context: Any) -> dict[str, Any]:
        tool = input_data.get("tool_name", "")
        args = input_data.get("tool_input") or {}
        if tool == RESULT_TOOL or tool.startswith(HARNESS_TOOL_PREFIX):
            return {}
        if tool in READ_TOOLS:
            reason = self._check_read(tool, args)
        elif tool in WRITE_TOOLS:
            reason = self._check_write(tool, args)
        else:
            reason = f"{tool} is not available in this session"
        return deny(reason) if reason else {}

    def relative(self, raw: str) -> str | None:
        candidate = Path(raw) if os.path.isabs(raw) else self.root / raw
        resolved = Path(os.path.realpath(candidate))
        if resolved != self.root and self.root not in resolved.parents:
            return None
        return resolved.relative_to(self.root).as_posix()

    def _check_read(self, tool: str, args: dict[str, Any]) -> str | None:
        raw_paths = [args.get(key) for key in ("file_path", "path") if args.get(key)]
        for raw in raw_paths:
            relative = self.relative(str(raw))
            if relative is None:
                return f"{raw} is outside the repository"
            if _is_secret_or_git(relative):
                return f"{relative} is not readable"
        for key in PATH_PATTERN_KEYS.get(tool, ()):
            value = args.get(key)
            if isinstance(value, str) and (os.path.isabs(value) or ".." in PurePosixPath(value).parts):
                return f"{key} must be a relative pattern inside the repository"
        return None

    def _check_write(self, tool: str, args: dict[str, Any]) -> str | None:
        raw = args.get("file_path")
        if not isinstance(raw, str) or not raw:
            return "file_path is required"
        relative = self.relative(raw)
        if relative is None:
            return f"{raw} is outside the repository"
        return self._write_policy(tool, relative)


class ReproWritePolicy:
    def __init__(self, root: Path) -> None:
        self._root = root
        self.test_path: str | None = None

    def __call__(self, tool: str, path: str) -> str | None:
        if self.test_path is not None:
            return None if path == self.test_path else f"only {self.test_path} may be written in this session"
        if tool != "Write":
            return "create the reproduction test with Write before editing it"
        if not TEST_FILE.match(path):
            return "the reproduction test must be a new file under tests/Feature or tests/Unit ending in Test.php"
        if (self._root / path).exists():
            return f"{path} already exists; write a new test file instead"
        self.test_path = path
        return None


class FixWritePolicy:
    def __init__(self, allowed_prefixes: Sequence[str], repro_path: str) -> None:
        self._allowed = tuple(allowed_prefixes)
        self._repro_path = repro_path

    def __call__(self, tool: str, path: str) -> str | None:
        if path == self._repro_path or path.startswith("tests/"):
            return "tests cannot be changed in the fix session"
        if path in FORBIDDEN_FILES or path.startswith(FORBIDDEN_PREFIXES) or not path.startswith(self._allowed):
            return f"only files under {', '.join(self._allowed)} may be changed"
        return None


def _is_secret_or_git(relative: str) -> bool:
    parts = PurePosixPath(relative).parts
    name = parts[-1] if parts else ""
    return ".git" in parts or (name.startswith(".env") and name != ".env.example") or name == "auth.json"
