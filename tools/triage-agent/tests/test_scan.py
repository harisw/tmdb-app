import asyncio
from datetime import UTC, datetime
from pathlib import Path

import pytest
from fakes import APP_FILE, BOT, REPO, TEST_PATH, FakeFixer, raw_event, report
from test_graph import Harness

from triage_agent.agents.sessions import FixAttempt
from triage_agent.cli import MAX_SCAN_EVENTS, execute, scan_events
from triage_agent.config import load_settings
from triage_agent.github.base import AGENT_LABEL, NEEDS_HUMAN_LABEL, branch_name, marker
from triage_agent.github.memory import InMemoryGitHub
from triage_agent.harness.verify import VerifyInputs, verify
from triage_agent.models import ErrorEvent, Fix, Usage
from triage_agent.sanitize import clean_event, redact, sensitive_values

MESSAGE = 'Duplicate entry "jane.doe@example.com" for "users_email_unique" and "The Secret Title"'


def event(index: int) -> ErrorEvent:
    return clean_event(raw_event(fingerprint=f"fp{index:02d}"))


def seed_handled(harness: Harness, fingerprint: str) -> None:
    harness.github.seed_issue(body=f"{marker(fingerprint)}\nbody", labels=[AGENT_LABEL, NEEDS_HUMAN_LABEL])


def test_skips_handled_errors_and_stops_after_the_first_fix_attempt(tmp_path: Path):
    harness = Harness(tmp_path)
    seed_handled(harness, "fp00")

    asyncio.run(scan_events(harness.deps(), [event(0), event(1), event(2)]))

    assert harness.workspace.prepared == ["fp01"]


def test_keeps_going_past_errors_that_only_needed_triage(tmp_path: Path):
    harness = Harness(tmp_path)
    harness.triager.result = harness.triager.result.model_copy(update={"is_code_bug": False})

    asyncio.run(scan_events(harness.deps(), [event(0), event(1)]))

    assert len(harness.triager.calls) == 2
    assert harness.workspace.prepared == []


def test_stops_when_the_open_pr_limit_is_reached(tmp_path: Path):
    harness = Harness(tmp_path, max_open_agent_prs=1)
    harness.github.seed_pull(head=branch_name("other", "master"))

    asyncio.run(scan_events(harness.deps(), [event(0), event(1)]))

    assert len(harness.runs()) == 1


def test_never_looks_at_more_than_the_scan_limit(tmp_path: Path):
    harness = Harness(tmp_path)
    harness.triager.result = harness.triager.result.model_copy(update={"is_code_bug": False})

    asyncio.run(scan_events(harness.deps(), [event(index) for index in range(MAX_SCAN_EVENTS + 3)]))

    assert len(harness.triager.calls) == MAX_SCAN_EVENTS


def test_a_crash_records_only_the_exception_type(tmp_path: Path):
    harness = Harness(tmp_path)

    def explode(*args: object) -> list[object]:
        raise ValueError("jane.doe@example.com")

    harness.github.agent_issues = explode  # type: ignore[method-assign]

    with pytest.raises(ValueError):
        asyncio.run(execute(harness.deps(), raw_event()))

    assert harness.runs()[0]["reason"] == "ValueError"


def test_agents_cannot_inherit_the_oidc_request_token():
    env = {
        "CLAUDE_CODE_OAUTH_TOKEN": "x",
        "GITHUB_REPO": "o/r",
        "ACTIONS_ID_TOKEN_REQUEST_TOKEN": "secret",
        "ACTIONS_ID_TOKEN_REQUEST_URL": "https://example.test",
    }

    load_settings(env, dry_run=True)

    assert "ACTIONS_ID_TOKEN_REQUEST_TOKEN" not in env
    assert "ACTIONS_ID_TOKEN_REQUEST_URL" not in env


def test_sensitive_values_keep_identifiers_out_of_the_list():
    assert set(sensitive_values(MESSAGE)) == {"jane.doe@example.com", "The Secret Title"}


def test_redact_replaces_every_sensitive_value():
    assert redact("Row for jane.doe@example.com failed", MESSAGE) == "Row for <redacted> failed"


def test_pull_body_never_repeats_a_value_from_the_production_error(tmp_path: Path):
    harness = Harness(tmp_path)
    published: list[str] = []
    harness.github = InMemoryGitHub(REPO, BOT, echo=published.append)

    async def fix(*args: object) -> FixAttempt:
        harness.workspace.files[APP_FILE] = "<?php // fixed"
        return FixAttempt(Fix('"The Secret Title" crashed the stage', "guard the title", "Skip it."), Usage(turns=1))

    harness.fixer = FakeFixer(harness.workspace)
    harness.fixer.fix = fix  # type: ignore[method-assign]

    harness.run(message=MESSAGE)

    assert any("draft PR" in line for line in published)
    assert "The Secret Title" not in "\n".join(published)


def test_verification_fails_without_repeating_the_leaked_value():
    inputs = VerifyInputs(
        changes=[],
        repro_path=TEST_PATH,
        repro_hash_before="a",
        repro_hash_after="a",
        diff_lines=1,
        suite=report(),
        pint=(True, ""),
        larastan=None,
        changed_text="$email = 'jane.doe@example.com';",
        forbidden_values=sensitive_values(MESSAGE),
    )

    check = next(check for check in verify(inputs, allowed_prefixes=("app/",), max_diff_lines=300).checks if check.name.startswith("no production"))

    assert not check.passed
    assert "jane.doe" not in check.detail


def test_graph_passes_last_seen_to_the_reopen_guard(tmp_path: Path):
    harness = Harness(tmp_path)
    harness.github.seed_issue(body=f"{marker('d96aebf547693359')}\nbody", state="closed", closed_at="2026-11-09T06:19:44+00:00")

    state = harness.run(last_seen=datetime(2026, 11, 9, 15, 7, tzinfo=UTC).isoformat())

    assert state["final_node"] == "check_existing_work"
    assert harness.triager.calls == []
