import json
from collections.abc import Callable
from dataclasses import replace
from pathlib import Path
from typing import Any

from triage_agent.github.client import AGENT_LABEL, Comment, Issue, IssueState, Pull

Echo = Callable[[str], None]


class InMemoryGitHub:
    def __init__(self, repo: str, bot_login: str, *, echo: Echo | None = None) -> None:
        self.repo = repo
        self.bot_login = bot_login
        self.issues: dict[int, Issue] = {}
        self.pulls: dict[int, Pull] = {}
        self.issue_comments: dict[int, list[Comment]] = {}
        self.writes: list[str] = []
        self._echo = echo
        self._next_number = 1

    @classmethod
    def from_state_file(cls, path: Path, repo: str, bot_login: str, *, echo: Echo | None = None) -> "InMemoryGitHub":
        github = cls(repo, bot_login, echo=echo)
        state = json.loads(path.read_text())
        for raw in state.get("issues", []):
            github.seed_issue(**raw)
        for raw in state.get("pulls", []):
            github.seed_pull(**raw)
        return github

    def seed_issue(self, *, body: str, state: IssueState = "open", labels: list[str] | None = None, author: str | None = None, title: str = "seeded", comments: list[dict[str, str]] | None = None) -> Issue:
        number = self._take_number()
        issue = Issue(number, self._url("issues", number), title, body, state, frozenset(labels or [AGENT_LABEL]), author or self.bot_login)
        self.issues[number] = issue
        self.issue_comments[number] = [Comment(**comment) for comment in comments or []]
        return issue

    def seed_pull(self, *, head: str, state: IssueState = "open", merged: bool = False, draft: bool = True) -> Pull:
        number = self._take_number()
        pull = Pull(number, self._url("pull", number), head, state, merged, draft)
        self.pulls[number] = pull
        return pull

    def agent_issues(self) -> list[Issue]:
        return [issue for issue in self.issues.values() if AGENT_LABEL in issue.labels]

    def pulls_for_branch(self, branch: str) -> list[Pull]:
        return [pull for pull in self.pulls.values() if pull.head == branch]

    def open_agent_pull_count(self) -> int:
        return sum(1 for pull in self.pulls.values() if pull.state == "open" and pull.head.startswith("agent/fix-"))

    def create_issue(self, title: str, body: str, labels: list[str]) -> Issue:
        number = self._take_number()
        issue = Issue(number, self._url("issues", number), title, body, "open", frozenset(labels), self.bot_login)
        self.issues[number] = issue
        self.issue_comments[number] = []
        self._record(f"create issue #{number} {title!r} labels={sorted(labels)}", body)
        return issue

    def update_issue(self, number: int, *, title: str | None = None, body: str | None = None, state: IssueState | None = None) -> Issue:
        issue = self.issues[number]
        changes: dict[str, Any] = {key: value for key, value in {"title": title, "body": body, "state": state}.items() if value is not None}
        self.issues[number] = replace(issue, **changes)
        self._record(f"update issue #{number} {sorted(changes)}" + (f" state={state}" if state else ""), body)
        return self.issues[number]

    def add_labels(self, number: int, labels: list[str]) -> None:
        issue = self.issues[number]
        self.issues[number] = replace(issue, labels=issue.labels | set(labels))
        self._record(f"add labels to #{number}: {sorted(labels)}")

    def remove_labels(self, number: int, labels: list[str]) -> None:
        issue = self.issues[number]
        self.issues[number] = replace(issue, labels=issue.labels - set(labels))
        self._record(f"remove labels from #{number}: {sorted(labels)}")

    def comments(self, number: int) -> list[Comment]:
        return list(self.issue_comments.get(number, []))

    def add_comment(self, number: int, body: str) -> None:
        self.issue_comments.setdefault(number, []).append(Comment(self.bot_login, body))
        self._record(f"comment on #{number}", body)

    def create_pull(self, *, head: str, base: str, title: str, body: str) -> Pull:
        number = self._take_number()
        pull = Pull(number, self._url("pull", number), head, "open", False, True)
        self.pulls[number] = pull
        self._record(f"create draft PR #{number} {head} -> {base} {title!r}", body)
        return pull

    def update_pull(self, number: int, *, title: str, body: str) -> Pull:
        self._record(f"update PR #{number} {title!r}", body)
        return self.pulls[number]

    def _take_number(self) -> int:
        number = self._next_number
        self._next_number += 1
        return number

    def _url(self, kind: str, number: int) -> str:
        return f"https://github.com/{self.repo}/{kind}/{number}"

    def _record(self, action: str, body: str | None = None) -> None:
        self.writes.append(action)
        if self._echo:
            self._echo(f"[dry-run] would {action}" + (f"\n{body}\n" if body else ""))
