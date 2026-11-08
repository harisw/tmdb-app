import json
import logging
import re
import time
from collections.abc import Callable, Sequence
from dataclasses import dataclass
from datetime import UTC, datetime
from typing import Any, Protocol

from triage_agent.fingerprint import fingerprint_record, parse_frames, primary_exception
from triage_agent.models import REQUEST_ID_PATTERN, ErrorEvent, StackFrame

logger = logging.getLogger(__name__)

MAX_RESULTS = 10_000
MAX_REQUEST_IDS = 5
VALID_REQUEST_ID = re.compile(REQUEST_ID_PATTERN)
PENDING_STATUSES = frozenset({"Scheduled", "Running"})
QUERY = f"""fields @timestamp, @message
| filter level_name in ["ERROR", "CRITICAL", "ALERT", "EMERGENCY"]
| sort @timestamp desc
| limit {MAX_RESULTS}"""


class CloudWatchQueryFailed(RuntimeError):
    pass


class CloudWatchResultsTruncated(RuntimeError):
    pass


class LogsClient(Protocol):
    def start_query(self, **kwargs: Any) -> dict[str, Any]: ...

    def get_query_results(self, queryId: str) -> dict[str, Any]: ...

    def stop_query(self, queryId: str) -> dict[str, Any]: ...


@dataclass(frozen=True)
class LogEntry:
    fingerprint: str
    error_class: str
    message: str
    stack_trace: tuple[StackFrame, ...]
    timestamp: datetime
    request_id: str | None


class CloudWatchErrorSource:
    def __init__(
        self,
        client: LogsClient,
        log_groups: Sequence[str],
        *,
        poll_interval: float = 2.0,
        timeout: float = 120.0,
        sleep: Callable[[float], None] = time.sleep,
        monotonic: Callable[[], float] = time.monotonic,
    ) -> None:
        self._client = client
        self._log_groups = list(log_groups)
        self._poll_interval = poll_interval
        self._timeout = timeout
        self._sleep = sleep
        self._monotonic = monotonic

    def fetch(self, start: datetime, end: datetime) -> list[ErrorEvent]:
        rows = self._run_query(start, end)
        if len(rows) >= MAX_RESULTS:
            raise CloudWatchResultsTruncated(f"Query returned {len(rows)} rows; use a shorter window.")

        entries = [entry for row in rows if (entry := _parse_row(row)) is not None]
        if skipped := len(rows) - len(entries):
            logger.warning("Skipped %d log lines that were not JSON objects", skipped)
        return _group(entries)

    def _run_query(self, start: datetime, end: datetime) -> list[list[dict[str, str]]]:
        query_id = self._client.start_query(
            logGroupNames=self._log_groups,
            startTime=int(start.timestamp()),
            endTime=int(end.timestamp()),
            queryString=QUERY,
            limit=MAX_RESULTS,
        )["queryId"]

        deadline = self._monotonic() + self._timeout
        while True:
            response = self._client.get_query_results(queryId=query_id)
            status = response["status"]
            if status == "Complete":
                return response.get("results", [])
            if status not in PENDING_STATUSES:
                raise CloudWatchQueryFailed(f"Query {query_id} ended with status {status}")
            if self._monotonic() >= deadline:
                self._client.stop_query(queryId=query_id)
                raise CloudWatchQueryFailed(f"Query {query_id} timed out after {self._timeout}s")
            self._sleep(self._poll_interval)


def _parse_row(row: list[dict[str, str]]) -> LogEntry | None:
    fields = {field["field"]: field["value"] for field in row}
    try:
        record = json.loads(fields.get("@message", ""))
    except json.JSONDecodeError:
        return None
    if not isinstance(record, dict):
        return None

    exception = primary_exception(record)
    return LogEntry(
        fingerprint=fingerprint_record(record),
        error_class=str(exception.get("class", "Unknown")) if exception else "LogMessage",
        message=str((exception or record).get("message", "")),
        stack_trace=parse_frames(exception) if exception else (),
        timestamp=_parse_timestamp(fields["@timestamp"]),
        request_id=_request_id(record),
    )


def _request_id(record: dict[str, Any]) -> str | None:
    extra = record.get("extra")
    value = extra.get("request_id") if isinstance(extra, dict) else None
    return value if isinstance(value, str) and VALID_REQUEST_ID.match(value) else None


def _parse_timestamp(value: str) -> datetime:
    return datetime.strptime(value, "%Y-%m-%d %H:%M:%S.%f").replace(tzinfo=UTC)


def _group(entries: list[LogEntry]) -> list[ErrorEvent]:
    groups: dict[str, list[LogEntry]] = {}
    for entry in entries:
        groups.setdefault(entry.fingerprint, []).append(entry)

    events = [_to_event(group) for group in groups.values()]
    return sorted(events, key=lambda event: event.occurrences, reverse=True)


def _to_event(group: list[LogEntry]) -> ErrorEvent:
    newest_first = sorted(group, key=lambda entry: entry.timestamp, reverse=True)
    latest = newest_first[0]
    request_ids = list(dict.fromkeys(entry.request_id for entry in newest_first if entry.request_id))
    return ErrorEvent(
        fingerprint=latest.fingerprint,
        error_class=latest.error_class,
        message=latest.message,
        stack_trace=latest.stack_trace,
        occurrences=len(group),
        first_seen=min(entry.timestamp for entry in group),
        last_seen=latest.timestamp,
        request_ids=tuple(request_ids[:MAX_REQUEST_IDS]),
    )
