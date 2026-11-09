import os
from collections.abc import MutableMapping
from dataclasses import dataclass, field
from pathlib import Path

SECRETS_KEPT_FROM_AGENTS = (
    "ANTHROPIC_API_KEY",
    "GITHUB_TOKEN",
    "GH_TOKEN",
    "AWS_ACCESS_KEY_ID",
    "AWS_SECRET_ACCESS_KEY",
    "AWS_SESSION_TOKEN",
)


class ConfigError(RuntimeError):
    pass


@dataclass(frozen=True)
class Limits:
    max_turns: int
    timeout_seconds: float


@dataclass(frozen=True)
class Settings:
    repo_path: Path
    github_repo: str
    github_token: str | None = field(repr=False)
    bot_login: str
    base_ref: str = "master"
    work_dir: Path = Path("/tmp/triage-agent")
    runs_log: Path = Path("runs.jsonl")
    dry_run: bool = True
    max_open_agent_prs: int = 2
    max_diff_lines: int = 300
    max_fix_attempts: int = 2
    confidence_threshold: float = 0.6
    triage_model: str = "claude-sonnet-5-5"
    agent_model: str = "claude-opus-5-5"
    triage_limits: Limits = field(default_factory=lambda: Limits(max_turns=3, timeout_seconds=120))
    reproduce_limits: Limits = field(default_factory=lambda: Limits(max_turns=15, timeout_seconds=900))
    fix_limits: Limits = field(default_factory=lambda: Limits(max_turns=20, timeout_seconds=900))
    test_timeout_seconds: float = 600
    fix_allowed_prefixes: tuple[str, ...] = ("app/", "routes/")

    @property
    def agent_config_dir(self) -> Path:
        return self.work_dir / "claude-config"


def load_settings(env: MutableMapping[str, str] = os.environ, *, dry_run: bool) -> Settings:
    if env.get("ANTHROPIC_API_KEY"):
        raise ConfigError("Unset ANTHROPIC_API_KEY; the agents authenticate with CLAUDE_CODE_OAUTH_TOKEN.")
    if not env.get("CLAUDE_CODE_OAUTH_TOKEN"):
        raise ConfigError("CLAUDE_CODE_OAUTH_TOKEN is required. Create one with `claude setup-token`.")

    github_token = env.get("GITHUB_TOKEN") or None
    if not dry_run and not github_token:
        raise ConfigError("GITHUB_TOKEN is required with --live.")

    settings = Settings(
        repo_path=Path(env.get("TRIAGE_REPO_PATH", ".")).resolve(),
        github_repo=_required(env, "GITHUB_REPO"),
        github_token=github_token,
        bot_login=env.get("TRIAGE_BOT_LOGIN", "triage-agent[bot]"),
        base_ref=env.get("TRIAGE_BASE_REF", "master"),
        work_dir=Path(env.get("TRIAGE_WORK_DIR", "/tmp/triage-agent")),
        runs_log=Path(env.get("TRIAGE_RUNS_LOG", "runs.jsonl")),
        dry_run=dry_run,
        max_open_agent_prs=int(env.get("MAX_OPEN_AGENT_PRS", "2")),
        max_diff_lines=int(env.get("MAX_DIFF_LINES", "300")),
    )
    _keep_secrets_from_agents(env)
    return settings


def _keep_secrets_from_agents(env: MutableMapping[str, str]) -> None:
    for name in SECRETS_KEPT_FROM_AGENTS:
        env.pop(name, None)


def _required(env: MutableMapping[str, str], name: str) -> str:
    if not (value := env.get(name)):
        raise ConfigError(f"{name} is required.")
    return value
