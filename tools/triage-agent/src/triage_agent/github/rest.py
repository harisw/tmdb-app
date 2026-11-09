from collections.abc import Iterator
from typing import Any
from urllib.parse import quote

import httpx

from triage_agent.github.base import (
    AGENT_BRANCH_PREFIX,
    AGENT_LABEL,
    Comment,
    GitHubError,
    GitHubTransientError,
    Issue,
    IssueState,
    Pull,
)

API_URL = "https://api.github.com"
API_VERSION = "2022-11-28"
PAGE_SIZE = 100
TIMEOUT_SECONDS = 30.0
TRANSIENT_STATUSES = frozenset({429, 500, 502, 503, 504})


class RestGitHub:
    def __init__(self, repo: str, token: str, *, transport: httpx.BaseTransport | None = None) -> None:
        self._owner = repo.split("/", 1)[0]
        self._client = httpx.Client(
            base_url=f"{API_URL}/repos/{repo}",
            transport=transport,
            timeout=TIMEOUT_SECONDS,
            headers={
                "Accept": "application/vnd.github+json",
                "Authorization": f"Bearer {token}",
                "X-GitHub-Api-Version": API_VERSION,
            },
        )

    def authenticated_login(self) -> str:
        return str(self._send("GET", f"{API_URL}/user").json()["login"])

    def agent_issues(self) -> list[Issue]:
        return [_issue(raw) for raw in self._paged("/issues", labels=AGENT_LABEL, state="all") if "pull_request" not in raw]

    def pulls_for_branch(self, branch: str) -> list[Pull]:
        return [_pull(raw) for raw in self._paged("/pulls", head=f"{self._owner}:{branch}", state="all")]

    def open_agent_pull_count(self) -> int:
        return sum(1 for raw in self._paged("/pulls", state="open") if raw["head"]["ref"].startswith(AGENT_BRANCH_PREFIX))

    def create_issue(self, title: str, body: str, labels: list[str]) -> Issue:
        return _issue(self._send("POST", "/issues", json={"title": title, "body": body, "labels": labels}).json())

    def update_issue(self, number: int, *, title: str | None = None, body: str | None = None, state: IssueState | None = None) -> Issue:
        changes = {key: value for key, value in {"title": title, "body": body, "state": state}.items() if value is not None}
        return _issue(self._send("PATCH", f"/issues/{number}", json=changes).json())

    def add_labels(self, number: int, labels: list[str]) -> None:
        self._send("POST", f"/issues/{number}/labels", json={"labels": labels})

    def remove_labels(self, number: int, labels: list[str]) -> None:
        for label in labels:
            self._send("DELETE", f"/issues/{number}/labels/{quote(label, safe='')}", allow_missing=True)

    def comments(self, number: int) -> list[Comment]:
        return [Comment(raw["user"]["login"], raw["body"] or "") for raw in self._paged(f"/issues/{number}/comments")]

    def add_comment(self, number: int, body: str) -> None:
        self._send("POST", f"/issues/{number}/comments", json={"body": body})

    def create_pull(self, *, head: str, base: str, title: str, body: str) -> Pull:
        return _pull(self._send("POST", "/pulls", json={"head": head, "base": base, "title": title, "body": body, "draft": True}).json())

    def update_pull(self, number: int, *, title: str, body: str) -> Pull:
        return _pull(self._send("PATCH", f"/pulls/{number}", json={"title": title, "body": body}).json())

    def _paged(self, path: str, **params: str) -> Iterator[dict[str, Any]]:
        response = self._send("GET", path, params={"per_page": PAGE_SIZE, **params})
        yield from response.json()
        while next_url := response.links.get("next", {}).get("url"):
            response = self._send("GET", next_url)
            yield from response.json()

    def _send(self, method: str, url: str, *, allow_missing: bool = False, **kwargs: Any) -> httpx.Response:
        try:
            response = self._client.request(method, url, **kwargs)
        except httpx.TransportError as error:
            raise GitHubTransientError(f"GitHub {method} {url} failed: {type(error).__name__}") from error
        if response.is_success or (allow_missing and response.status_code == 404):
            return response
        message = f"GitHub {method} {url} returned {response.status_code}: {response.text[:300]}"
        raise (GitHubTransientError if _is_transient(response) else GitHubError)(message)


def _is_transient(response: httpx.Response) -> bool:
    if response.status_code in TRANSIENT_STATUSES:
        return True
    return response.status_code == 403 and ("retry-after" in response.headers or response.headers.get("x-ratelimit-remaining") == "0")


def _issue(raw: dict[str, Any]) -> Issue:
    return Issue(
        number=raw["number"],
        url=raw["html_url"],
        title=raw["title"],
        body=raw["body"] or "",
        state=raw["state"],
        labels=frozenset(label["name"] for label in raw["labels"]),
        author=raw["user"]["login"],
    )


def _pull(raw: dict[str, Any]) -> Pull:
    return Pull(
        number=raw["number"],
        url=raw["html_url"],
        head=raw["head"]["ref"],
        state=raw["state"],
        merged=raw.get("merged_at") is not None,
        draft=raw["draft"],
    )