from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime
from enum import StrEnum

from pydantic import BaseModel, ConfigDict, Field

FINGERPRINT_PATTERN = r"^[A-Za-z0-9_-]{1,64}$"
REQUEST_ID_PATTERN = r"^[A-Za-z0-9_-]{1,64}$"


class StackFrame(BaseModel):
    model_config = ConfigDict(frozen=True)

    file: str
    line: int | None = None
    function: str | None = None


class ErrorEvent(BaseModel):
    model_config = ConfigDict(frozen=True)

    fingerprint: str = Field(pattern=FINGERPRINT_PATTERN)
    error_class: str
    message: str
    stack_trace: tuple[StackFrame, ...]
    occurrences: int = Field(ge=1)
    first_seen: datetime
    last_seen: datetime
    request_ids: tuple[str, ...] = ()

    @property
    def app_frames(self) -> tuple[StackFrame, ...]:
        return tuple(frame for frame in self.stack_trace if frame.file.startswith(("app/", "routes/")))


class Outcome(StrEnum):
    ALREADY_HANDLED = "already_handled"
    AT_CAPACITY = "at_capacity"
    NEEDS_HUMAN = "needs_human"
    NOT_REPRODUCED = "not_reproduced"
    VERIFY_FAILED = "verify_failed"
    PR_OPENED = "pr_opened"
    ERROR = "error"


@dataclass(frozen=True)
class Usage:
    input_tokens: int = 0
    output_tokens: int = 0
    cache_read_tokens: int = 0
    cache_write_tokens: int = 0
    api_equivalent_usd: float = 0.0
    turns: int = 0

    def __add__(self, other: Usage) -> Usage:
        return Usage(
            input_tokens=self.input_tokens + other.input_tokens,
            output_tokens=self.output_tokens + other.output_tokens,
            cache_read_tokens=self.cache_read_tokens + other.cache_read_tokens,
            cache_write_tokens=self.cache_write_tokens + other.cache_write_tokens,
            api_equivalent_usd=round(self.api_equivalent_usd + other.api_equivalent_usd, 6),
            turns=self.turns + other.turns,
        )


class TriageResult(BaseModel):
    model_config = ConfigDict(frozen=True)

    is_code_bug: bool
    likely_area: str = Field(max_length=200)
    suspected_files: list[str] = Field(max_length=10)
    summary: str = Field(max_length=2000)
    confidence: float = Field(ge=0, le=1)


@dataclass(frozen=True)
class Repro:
    test_path: str
    test_hash: str
    explanation: str


@dataclass(frozen=True)
class Fix:
    root_cause: str
    commit_subject: str
    summary: str
    risks: tuple[str, ...] = ()


@dataclass(frozen=True)
class CheckResult:
    name: str
    passed: bool
    detail: str = ""


@dataclass(frozen=True)
class VerifyResult:
    passed: bool
    checks: tuple[CheckResult, ...] = field(default_factory=tuple)

    @property
    def failures(self) -> tuple[CheckResult, ...]:
        return tuple(check for check in self.checks if not check.passed)


@dataclass(frozen=True)
class IssueRef:
    number: int
    url: str


@dataclass(frozen=True)
class PullRef:
    number: int
    url: str
