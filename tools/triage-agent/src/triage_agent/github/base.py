from dataclasses import dataclass
from typing import Literal, Protocol

AGENT_LABEL = "agent"
AUTO_LABEL = "triage:auto"
NEEDS_HUMAN_LABEL = "triage:needs-human"
NOT_REPRODUCED_LABEL = "triage:not-reproduced"
VERIFY_FAILED_LABEL = "triage:verify-failed"
AGENT_BRANCH_PREFIX = "agent/fix-"
TERMINAL_LABELS = frozenset({NEEDS_HUMAN_LABEL, NOT_REPRODUCED_LABEL, VERIFY_FAILED_LABEL})

IssueState = Literal["open", "closed"]

class GitHubError(RuntimeError):
    pass

class GitHubTransientError(GitHubError):
    pass

@dataclass(frozen=True)
class Issue:
    number: int
    url: str
    title: str
    body: str
    state: IssueState
    labels: frozenset[str]
    author: str


@dataclass(frozen=True)
class Comment:
    author: str
    body: str


@dataclass(frozen=True)
class Pull:
    number: int
    url: str
    head: str
    state: IssueState
    merged: bool
    draft: bool


def marker(fingerprint: str) -> str:
    return f"<!-- triage-fp: {fingerprint} -->"


def note_marker(key: str) -> str:
    return f"<!-- triage-note: {key} -->"


def branch_name(fingerprint: str, base: str) -> str:
    return f"{AGENT_BRANCH_PREFIX}{base}/{fingerprint}"


def is_issue_for(issue: Issue, fingerprint: str, bot_login: str) -> bool:
    first_line = issue.body.split("\n", 1)[0].strip()
    return issue.author == bot_login and AGENT_LABEL in issue.labels and first_line == marker(fingerprint)


class GitHub(Protocol):
    def agent_issues(self) -> list[Issue]: ...

    def pulls_for_branch(self, branch: str) -> list[Pull]: ...

    def open_agent_pull_count(self) -> int: ...

    def create_issue(self, title: str, body: str, labels: list[str]) -> Issue: ...

    def update_issue(self, number: int, *, title: str | None = None, body: str | None = None, state: IssueState | None = None) -> Issue: ...

    def add_labels(self, number: int, labels: list[str]) -> None: ...

    def remove_labels(self, number: int, labels: list[str]) -> None: ...

    def comments(self, number: int) -> list[Comment]: ...

    def add_comment(self, number: int, body: str) -> None: ...

    def create_pull(self, *, head: str, base: str, title: str, body: str) -> Pull: ...

    def update_pull(self, number: int, *, title: str, body: str) -> Pull: ...


def find_issue(github: GitHub, fingerprint: str, bot_login: str) -> Issue | None:
    matches = [issue for issue in github.agent_issues() if is_issue_for(issue, fingerprint, bot_login)]
    return min(matches, key=lambda issue: issue.number) if matches else None


def comment_once(github: GitHub, number: int, key: str, body: str, bot_login: str) -> bool:
    tag = note_marker(key)
    if any(comment.author == bot_login and comment.body.startswith(tag) for comment in github.comments(number)):
        return False
    github.add_comment(number, f"{tag}\n{body}")
    return True
