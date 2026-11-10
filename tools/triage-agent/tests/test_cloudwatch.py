import json
from datetime import UTC, datetime
from typing import Any

import pytest
from conftest import make_exception, make_record

from triage_agent.sources.cloudwatch import (
    MAX_RESULTS,
    CloudWatchErrorSource,
    CloudWatchQueryFailed,
    CloudWatchResultsTruncated,
)

START = datetime(2026, 11, 1, tzinfo=UTC)
END = datetime(2026, 11, 2, tzinfo=UTC)


def row(record: Any, timestamp: str = "2026-11-01 10:00:00.000", occurrences: int = 1) -> list[dict[str, str]]:
    message = record if isinstance(record, str) else json.dumps(record)
    return [
        {"field": "occurrences", "value": str(occurrences)},
        {"field": "first_seen", "value": timestamp},
        {"field": "last_seen", "value": timestamp},
        {"field": "sample", "value": message},
    ]


class FakeLogs:
    def __init__(self, responses: list[dict[str, Any]]):
        self.responses = responses
        self.started: dict[str, Any] = {}
        self.stopped: list[str] = []

    def start_query(self, **kwargs: Any) -> dict[str, str]:
        self.started = kwargs
        return {"queryId": "q-1"}

    def get_query_results(self, queryId: str) -> dict[str, Any]:
        return self.responses.pop(0) if len(self.responses) > 1 else self.responses[0]

    def stop_query(self, queryId: str) -> dict[str, bool]:
        self.stopped.append(queryId)
        return {"success": True}


def source(client: FakeLogs, **kwargs: Any) -> CloudWatchErrorSource:
    return CloudWatchErrorSource(client, ["/tmdb/app", "/tmdb/horizon"], sleep=lambda _: None, **kwargs)


def complete(*rows: list[dict[str, str]]) -> dict[str, Any]:
    return {"status": "Complete", "results": list(rows)}


def test_queries_both_log_groups_for_the_window_in_epoch_seconds():
    client = FakeLogs([complete()])

    source(client).fetch(START, END)

    assert client.started["logGroupNames"] == ["/tmdb/app", "/tmdb/horizon"]
    assert client.started["startTime"] == int(START.timestamp())
    assert client.started["endTime"] == int(END.timestamp())
    assert "level_name" in client.started["queryString"]
    assert f"limit {MAX_RESULTS}" in client.started["queryString"]


def test_groups_events_and_tracks_first_and_last_seen():
    client = FakeLogs(
        [
            complete(
                row(make_record(make_exception(message="latest")), "2026-11-01 12:00:00.000"),
                row(make_record(make_exception(message="middle")), "2026-11-01 11:00:00.000"),
                row(make_record(make_exception(message="oldest")), "2026-11-01 10:00:00.000"),
            )
        ]
    )

    [event] = source(client).fetch(START, END)

    assert event.occurrences == 3
    assert event.first_seen == datetime(2026, 11, 1, 10, tzinfo=UTC)
    assert event.last_seen == datetime(2026, 11, 1, 12, tzinfo=UTC)
    assert event.message == "latest"
    assert event.error_class == "ErrorException"
    assert event.stack_trace[0].file == "app/Pipelines/Stages/ProcessWatchProviders.php"


def test_returns_groups_most_frequent_first():
    rare = make_record(make_exception(**{"class": "TypeError"}))
    client = FakeLogs([complete(row(rare), row(make_record()), row(make_record()))])

    events = source(client).fetch(START, END)

    assert [event.error_class for event in events] == ["ErrorException", "TypeError"]


def test_uses_the_log_message_when_there_is_no_exception():
    client = FakeLogs([complete(row({"message": "Sync failed", "context": {}, "extra": {}}))])

    [event] = source(client).fetch(START, END)

    assert event.error_class == "LogMessage"
    assert event.message == "Sync failed"
    assert event.stack_trace == ()


def test_skips_lines_that_are_not_json_objects_and_warns(caplog):
    client = FakeLogs([complete(row("PHP Fatal error: boom"), row("[1]"), row(make_record()))])

    events = source(client).fetch(START, END)

    assert sum(event.occurrences for event in events) == 1
    assert "Skipped 2" in caplog.text


def test_polls_until_the_query_completes():
    client = FakeLogs([{"status": "Scheduled"}, {"status": "Running"}, complete(row(make_record()))])

    assert len(source(client).fetch(START, END)) == 1


@pytest.mark.parametrize("status", ["Failed", "Cancelled", "Timeout", "Unknown"])
def test_raises_when_the_query_does_not_complete(status):
    client = FakeLogs([{"status": status}])

    with pytest.raises(CloudWatchQueryFailed, match=status):
        source(client).fetch(START, END)


def test_stops_the_query_and_raises_on_timeout():
    ticks = iter([0.0, 5.0, 11.0])
    client = FakeLogs([{"status": "Running"}])

    with pytest.raises(CloudWatchQueryFailed, match="timed out"):
        source(client, timeout=10.0, monotonic=lambda: next(ticks)).fetch(START, END)

    assert client.stopped == ["q-1"]


def test_refuses_to_undercount_when_results_hit_the_limit():
    client = FakeLogs([complete(*[row(make_record()) for _ in range(MAX_RESULTS)])])

    with pytest.raises(CloudWatchResultsTruncated):
        source(client).fetch(START, END)


def test_keeps_the_latest_distinct_valid_request_ids():
    def with_request_id(request_id: Any) -> dict[str, Any]:
        return make_record(extra={"request_id": request_id})

    client = FakeLogs(
        [
            complete(
                row(with_request_id("req-3"), "2026-11-01 12:00:00.000"),
                row(with_request_id("req-3"), "2026-11-01 11:30:00.000"),
                row(with_request_id('bad" | filter 1'), "2026-11-01 11:00:00.000"),
                row(with_request_id("req-1"), "2026-11-01 10:00:00.000"),
                row(make_record(), "2026-11-01 09:00:00.000"),
            )
        ]
    )

    [event] = source(client).fetch(START, END)

    assert event.request_ids == ("req-3", "req-1")


def test_groups_on_the_server_so_a_flood_cannot_hit_the_row_limit():
    client = FakeLogs([complete()])

    source(client).fetch(START, END)

    assert "stats count(*)" in client.started["queryString"]


def test_adds_up_occurrences_from_rows_with_the_same_fingerprint():
    record = make_record(make_exception())
    client = FakeLogs([complete(row(record, occurrences=40), row(record, "2026-11-01 12:00:00.000", occurrences=2))])

    [event] = source(client).fetch(START, END)

    assert event.occurrences == 42
    assert event.last_seen == datetime(2026, 11, 1, 12, tzinfo=UTC)
