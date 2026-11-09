import hashlib
import re
from collections.abc import Iterator
from typing import Any

from triage_agent.models import StackFrame

ALGORITHM_VERSION = "v1"
MAX_HASHED_FRAMES = 5
MAX_TEMPLATE_LENGTH = 200
DEPLOY_ROOTS = ("/var/www/html/", "/var/www/")
IGNORED_PREFIXES = ("vendor/", "storage/framework/")
BOILERPLATE_FILES = frozenset({"artisan", "public/index.php", "app/Http/Middleware/AssignRequestId.php"})

FILE_FRAME = re.compile(r"^(?P<file>.+):(?P<line>\d+)$")
INTERNAL_FRAME = re.compile(r"^internal\[(?P<function>.+)\](?::\d+)?$")
SQLSTATE = re.compile(r"SQLSTATE\[(?P<state>\w+)\]")
DATA_PATTERNS = (
    (re.compile(r"\(Connection: .*", re.DOTALL), ""),
    (re.compile(r"\"[^\"]*\"|'[^']*'"), "<str>"),
    (re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+"), "<email>"),
    (re.compile(r"\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b"), "<uuid>"),
    (re.compile(r"\b\d{1,3}(?:\.\d{1,3}){3}\b"), "<ip>"),
    (re.compile(r"\b0x[0-9a-fA-F]+\b|\b[0-9a-fA-F]{8,}\b"), "<hex>"),
    (re.compile(r"\b\d+(?:\.\d+)?\b"), "<num>"),
)


def fingerprint_record(record: dict[str, Any]) -> str:
    exception = primary_exception(record)
    parts = [ALGORITHM_VERSION, *_exception_parts(record, exception)] if exception else [
        ALGORITHM_VERSION,
        "message",
        normalize_message(str(record.get("message", ""))),
    ]
    return hashlib.sha256("\x00".join(parts).encode()).hexdigest()[:16]


def primary_exception(record: dict[str, Any]) -> dict[str, Any] | None:
    context = record.get("context")
    exception = context.get("exception") if isinstance(context, dict) else None
    return exception if isinstance(exception, dict) else None


def parse_frames(exception: dict[str, Any]) -> tuple[StackFrame, ...]:
    raw_frames = [exception.get("file"), *exception.get("trace", [])]
    return tuple(_parse_frame(raw) for raw in raw_frames if isinstance(raw, str) and raw)


def normalize_message(message: str) -> str:
    for pattern, replacement in DATA_PATTERNS:
        message = pattern.sub(replacement, message)
    return " ".join(message.split())[:MAX_TEMPLATE_LENGTH]


def _exception_parts(record: dict[str, Any], exception: dict[str, Any]) -> list[str]:
    chain = list(_exception_chain(exception))
    classes = [str(link.get("class", "")) for link in chain]
    states = [match["state"] for link in chain if (match := SQLSTATE.search(str(link.get("message", ""))))]
    return [*classes, "sqlstate", *states[:1], *_location_parts(record, chain)]


def _location_parts(record: dict[str, Any], chain: list[dict[str, Any]]) -> list[str]:
    for link in chain:
        if frames := _hashed_frames(link):
            return ["frames", *frames]
    if job_name := _job_name(record):
        return ["job", job_name]
    return ["message", normalize_message(str(chain[0].get("message", "")))]


def _exception_chain(exception: dict[str, Any]) -> Iterator[dict[str, Any]]:
    link: Any = exception
    while isinstance(link, dict):
        yield link
        link = link.get("previous")


def _hashed_frames(exception: dict[str, Any]) -> list[str]:
    frames: list[str] = []
    for frame in parse_frames(exception):
        label = frame.function if frame.file == "internal" else frame.file
        if label and _is_application_frame(frame) and (not frames or frames[-1] != label):
            frames.append(label)
    return frames[:MAX_HASHED_FRAMES]


def _is_application_frame(frame: StackFrame) -> bool:
    return not frame.file.startswith(IGNORED_PREFIXES) and frame.file not in BOILERPLATE_FILES


def _job_name(record: dict[str, Any]) -> str | None:
    extra = record.get("extra")
    job = extra.get("job") if isinstance(extra, dict) else None
    name = job.get("name") if isinstance(job, dict) else None
    return name if isinstance(name, str) else None


def _parse_frame(raw: str) -> StackFrame:
    if internal := INTERNAL_FRAME.match(raw):
        return StackFrame(file="internal", function=internal["function"])
    if located := FILE_FRAME.match(raw):
        return StackFrame(file=_relative_path(located["file"]), line=int(located["line"]))
    return StackFrame(file=_relative_path(raw))


def _relative_path(path: str) -> str:
    for root in DEPLOY_ROOTS:
        if path.startswith(root):
            return path.removeprefix(root)
    return path
