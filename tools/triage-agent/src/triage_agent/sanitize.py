import json
import re
from typing import Any

from triage_agent.models import ErrorEvent, StackFrame

MAX_MESSAGE_LENGTH = 2000
MAX_PUBLIC_MESSAGE_LENGTH = 500
MAX_FRAMES = 50
MAX_PATH_LENGTH = 300
MAX_FUNCTION_LENGTH = 200

LARAVEL_SQL_SUFFIX = re.compile(r"\s*\(Connection: .*", re.DOTALL)
POSTGRES_KEY_DETAIL = re.compile(r"Key \(([^)]*)\)=\(.*?\)(?= already exists| is not present|\.|\s|$)", re.DOTALL)
POSTGRES_ROW_DETAIL = re.compile(r"Failing row contains \(.*\)", re.DOTALL)
URL = re.compile(r"\b(https?://[^\s?#\"'<>]+)[^\s\"'<>]*")
HIGH_RISK_PATTERNS = (
    (re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+"), "<email>"),
    (re.compile(r"\beyJ[\w-]+\.[\w-]+\.[\w-]+"), "<token>"),
    (re.compile(r"\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b"), "<uuid>"),
    (re.compile(r"\b\d{1,3}(?:\.\d{1,3}){3}\b"), "<ip>"),
    (re.compile(r"\b(?=[A-Za-z0-9+/_=-]*\d)(?=[A-Za-z0-9+/_=-]*[A-Za-z])[A-Za-z0-9+/_=-]{32,}"), "<token>"),
)
QUOTED = re.compile(r"\"([^\"]*)\"|'([^']*)'")
IDENTIFIER = re.compile(r"^[A-Za-z_][A-Za-z0-9_.:\\-]{0,63}$")
MIN_SENSITIVE_LENGTH = 4
NUMBER = re.compile(r"(?<!SQLSTATE\[)\b\d+(?:\.\d+)?\b")


def clean_event(raw: dict[str, Any]) -> ErrorEvent:
    frames = [frame for frame in raw.get("stack_trace", [])[:MAX_FRAMES] if isinstance(frame, dict)]
    return ErrorEvent.model_validate(
        {
            **raw,
            "message": _clip(str(raw.get("message", "")), MAX_MESSAGE_LENGTH),
            "stack_trace": [_clean_frame(frame) for frame in frames],
        }
    )


def scrub(text: str) -> str:
    text = LARAVEL_SQL_SUFFIX.sub("", text)
    text = POSTGRES_KEY_DETAIL.sub(r"Key (\1)=(<redacted>)", text)
    text = POSTGRES_ROW_DETAIL.sub("Failing row contains (<redacted>)", text)
    text = URL.sub(r"\1", text)
    for pattern, replacement in HIGH_RISK_PATTERNS:
        text = pattern.sub(replacement, text)
    return text


def public_message(message: str) -> str:
    text = QUOTED.sub(_keep_identifier, scrub(message))
    text = NUMBER.sub("<num>", text)
    return _clip(" ".join(text.split()), MAX_PUBLIC_MESSAGE_LENGTH)


def sensitive_values(message: str) -> tuple[str, ...]:
    quoted = [value for match in QUOTED.finditer(message) if not IDENTIFIER.match(value := match.group(1) or match.group(2) or "")]
    high_risk = [match.group(0) for pattern, _ in HIGH_RISK_PATTERNS for match in pattern.finditer(message)]
    values = {value for value in [*quoted, *high_risk] if len(value) >= MIN_SENSITIVE_LENGTH}
    return tuple(sorted(values, key=len, reverse=True))


def redact(text: str, message: str) -> str:
    for value in sensitive_values(message):
        text = text.replace(value, "<redacted>")
    return text


def data_block(tag: str, payload: Any) -> str:
    encoded = json.dumps(payload, indent=2, ensure_ascii=False, default=str)
    encoded = encoded.replace("&", "\\u0026").replace("<", "\\u003c").replace(">", "\\u003e")
    return f"<{tag}>\n{encoded}\n</{tag}>"


def fenced(text: str, info: str = "text") -> str:
    longest = max((len(run) for run in re.findall(r"`+", text)), default=0)
    fence = "`" * max(3, longest + 1)
    return f"{fence}{info}\n{text}\n{fence}"


def single_line(text: str, limit: int) -> str:
    return _clip(" ".join(scrub(text).split()), limit)


def _keep_identifier(match: re.Match[str]) -> str:
    value = match.group(1) if match.group(1) is not None else match.group(2)
    return match.group(0) if IDENTIFIER.match(value) else "<str>"


def _clean_frame(frame: dict[str, Any]) -> StackFrame:
    function = frame.get("function")
    return StackFrame(
        file=_clip(str(frame.get("file", "")), MAX_PATH_LENGTH),
        line=frame.get("line"),
        function=_clip(str(function), MAX_FUNCTION_LENGTH) if function else None,
    )


def _clip(text: str, limit: int) -> str:
    return text if len(text) <= limit else text[: limit - 1] + "…"
