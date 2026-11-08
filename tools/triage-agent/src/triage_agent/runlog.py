import json
from datetime import UTC, datetime
from pathlib import Path
from typing import Any

from triage_agent.fingerprint import ALGORITHM_VERSION
from triage_agent.models import Outcome, Usage
from triage_agent.sanitize import single_line


def run_record(
    *,
    fingerprint: str,
    final_node: str,
    outcome: Outcome,
    reason: str,
    usage: Usage,
    duration_seconds: float,
    pr_url: str | None,
    issue_url: str | None,
    dry_run: bool,
) -> dict[str, Any]:
    return {
        "timestamp": datetime.now(UTC).isoformat(),
        "fingerprint": fingerprint,
        "fingerprint_algorithm": ALGORITHM_VERSION,
        "final_node": final_node,
        "outcome": outcome.value,
        "reason": single_line(reason, 500),
        "tokens": {
            "input": usage.input_tokens,
            "output": usage.output_tokens,
            "cache_read": usage.cache_read_tokens,
            "cache_write": usage.cache_write_tokens,
        },
        "turns": usage.turns,
        "api_equivalent_usd": usage.api_equivalent_usd,
        "duration_seconds": round(duration_seconds, 2),
        "pr_url": pr_url,
        "issue_url": issue_url,
        "dry_run": dry_run,
    }


def append_run(path: Path, record: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(record, sort_keys=True) + "\n")

