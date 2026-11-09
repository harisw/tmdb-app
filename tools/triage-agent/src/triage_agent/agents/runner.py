import asyncio
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from claude_agent_sdk import ClaudeAgentOptions, HookMatcher, ResultMessage, query
from claude_agent_sdk.types import HookEvent

from triage_agent.config import Limits
from triage_agent.models import Usage


@dataclass(frozen=True)
class AgentRun:
    output: dict[str, Any] | None
    usage: Usage
    error: str | None = None

    @property
    def ok(self) -> bool:
        return self.error is None and self.output is not None


@dataclass(frozen=True)
class AgentSpec:
    system_prompt: str
    model: str
    limits: Limits
    schema: dict[str, Any]
    cwd: Path | None = None
    tools: tuple[str, ...] = ()
    hooks: dict[HookEvent, list[HookMatcher]] | None = None
    mcp_servers: dict[str, Any] | None = None
    extra_allowed_tools: tuple[str, ...] = ()
    config_dir: Path | None = None


async def run_agent(spec: AgentSpec, prompt: str) -> AgentRun:
    options = ClaudeAgentOptions(
        system_prompt=spec.system_prompt,
        model=spec.model,
        tools=list(spec.tools),
        allowed_tools=[*spec.tools, *spec.extra_allowed_tools],
        permission_mode="dontAsk",
        setting_sources=[],
        strict_mcp_config=True,
        mcp_servers=spec.mcp_servers or {},
        hooks=spec.hooks,
        cwd=spec.cwd,
        max_turns=spec.limits.max_turns,
        output_format={"type": "json_schema", "schema": spec.schema},
        env=agent_env(spec.config_dir),
    )

    result: ResultMessage | None = None
    try:
        async with asyncio.timeout(spec.limits.timeout_seconds):
            async for message in query(prompt=prompt, options=options):
                if isinstance(message, ResultMessage):
                    result = message
    except TimeoutError:
        return AgentRun(None, usage_of(result), f"timed out after {spec.limits.timeout_seconds:.0f}s")

    if result is None:
        return AgentRun(None, Usage(), "agent returned no result")
    usage = usage_of(result)
    if result.subtype != "success" or result.is_error:
        return AgentRun(None, usage, f"agent stopped: {result.subtype} ({stop_details(result)})")
    if not isinstance(result.structured_output, dict):
        return AgentRun(None, usage, f"agent returned no structured output ({stop_details(result)})")
    return AgentRun(result.structured_output, usage)


def stop_details(result: ResultMessage) -> str:
    denied = sorted({str(denial.get("tool_name", "unknown")) for denial in result.permission_denials or []})
    return f"stop_reason={result.stop_reason}, denied_tools={','.join(denied) or 'none'}"


def agent_env(config_dir: Path | None) -> dict[str, str]:
    env = {"CLAUDE_CODE_DISABLE_AUTO_MEMORY": "1"}
    if config_dir is not None:
        config_dir.mkdir(parents=True, exist_ok=True)
        env["CLAUDE_CONFIG_DIR"] = str(config_dir)
    return env


def usage_of(result: ResultMessage | None) -> Usage:
    if result is None:
        return Usage()
    usage = result.usage or {}
    return Usage(
        input_tokens=int(usage.get("input_tokens", 0)),
        output_tokens=int(usage.get("output_tokens", 0)),
        cache_read_tokens=int(usage.get("cache_read_input_tokens", 0)),
        cache_write_tokens=int(usage.get("cache_creation_input_tokens", 0)),
        api_equivalent_usd=float(result.total_cost_usd or 0.0),
        turns=result.num_turns,
    )
