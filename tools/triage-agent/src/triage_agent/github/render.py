from triage_agent.github.base import marker
from triage_agent.models import ErrorEvent, Fix, Repro, TriageResult, VerifyResult
from triage_agent.sanitize import fenced, public_message, single_line

LOG_GROUPS = ("/tmdb/app", "/tmdb/horizon")
MAX_PUBLIC_FRAMES = 15


def issue_title(event: ErrorEvent) -> str:
    location = event.app_frames[0].file if event.app_frames else "unknown location"
    return single_line(f"[triage] {event.error_class} in {location}", 120)


def issue_body(event: ErrorEvent, triage: TriageResult | None) -> str:
    sections = [
        marker(event.fingerprint),
        f"**Fingerprint:** `{event.fingerprint}`",
        _counts(event),
    ]
    if triage:
        sections.append(_triage_section(triage))
    sections.append(_error_section(event))
    if event.request_ids:
        sections.append(_lookup_section(event))
    return "\n\n".join(sections) + "\n"


def occurrence_comment(event: ErrorEvent, reason: str) -> str:
    lines = [f"Seen again ({reason}).", _counts(event)]
    if event.request_ids:
        lines.append(_lookup_section(event))
    return "\n\n".join(lines)


def rejected_comment(event: ErrorEvent, pull_url: str) -> str:
    return (
        f"Seen again, but the agent fix in {pull_url} was closed without merging, so no new fix will be attempted.\n\n"
        + _counts(event)
    )


def reopened_comment(event: ErrorEvent) -> str:
    return "This error recurred after the issue was closed, so the issue was reopened.\n\n" + _counts(event)


def not_reproduced_comment(reason: str) -> str:
    return f"Could not reproduce this error with a failing test, so no fix was attempted.\n\n**Reason:** {single_line(reason, 500)}"


def verify_failed_comment(verify: VerifyResult, attempts: int) -> str:
    failures = "\n".join(f"- **{check.name}:** {single_line(check.detail, 300)}" for check in verify.failures)
    return f"The fix did not pass verification after {attempts} attempts, so no PR was opened.\n\n{failures}"


def commit_message(fix: Fix, issue_number: int) -> str:
    return f"fix: {fix.commit_subject}\n\n{single_line(fix.summary, 600)}\n\nRefs #{issue_number}"


def pull_title(fix: Fix) -> str:
    return f"fix: {fix.commit_subject}"


def pull_body(event: ErrorEvent, issue_number: int, repro: Repro, fix: Fix, verify: VerifyResult) -> str:
    checks = "\n".join(f"- [{'x' if check.passed else ' '}] {check.name}" for check in verify.checks)
    return "\n\n".join(
        [
            f"Fixes #{issue_number}",
            "> Draft opened by the triage agent. Review it like any other PR; it is never merged automatically.",
            f"## Root cause\n{single_line(fix.root_cause, 1500)}",
            f"## Change\n{single_line(fix.summary, 1500)}",
            f"## Risks\n{_risks_section(fix)}",
            (f"## Reproduction test\n`{repro.test_path}` failed with `{event.error_class}` before the change and passes after it.\n\n"
            f"{single_line(repro.explanation, 800)}"),
            f"## Verification\n{checks}",
        ]
    ) + "\n"


def _risks_section(fix: Fix) -> str:
    return "\n".join(f"- {single_line(risk, 500)}" for risk in fix.risks) or "None identified by the agent."


def _counts(event: ErrorEvent) -> str:
    return (
        f"**Occurrences:** {event.occurrences} · **First seen:** {event.first_seen.isoformat()} · "
        f"**Last seen:** {event.last_seen.isoformat()}"
    )


def _triage_section(triage: TriageResult) -> str:
    files = ", ".join(f"`{single_line(path, 200)}`" for path in triage.suspected_files) or "none named"
    return (
        "### Triage\n"
        f"{single_line(triage.summary, 1500)}\n\n"
        f"**Likely area:** {single_line(triage.likely_area, 200)} · **Confidence:** {triage.confidence:.2f} · "
        f"**Suspected files:** {files}"
    )


def _error_section(event: ErrorEvent) -> str:
    frames = [f"  {frame.file}:{frame.line}" if frame.line else f"  {frame.file}" for frame in event.app_frames[:MAX_PUBLIC_FRAMES]]
    details = "\n".join(
        [f"class: {event.error_class}", f"message: {public_message(event.message)}", "app frames:", *(frames or ["  none"])]
    )
    return (
        "### Error details\n"
        "Untrusted data from production logs. Values that may be user data are replaced with placeholders.\n\n"
        + fenced(details)
    )


def _lookup_section(event: ErrorEvent) -> str:
    ids = ", ".join(f'"{request_id}"' for request_id in event.request_ids)
    query = f"fields @timestamp, @message\n| filter extra.request_id in [{ids}]\n| sort @timestamp desc"
    groups = " and ".join(f"`{group}`" for group in LOG_GROUPS)
    return f"### Full log lines\nRun in CloudWatch Logs Insights on {groups}:\n\n" + fenced(query, "sql")
