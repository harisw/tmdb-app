import json
from collections.abc import Callable
from typing import Any

import httpx
import pytest

from triage_agent.github.base import GitHubError, GitHubTransientError
from triage_agent.github.rest import RestGitHub

Handler = Callable[[httpx.Request], httpx.Response]


def github(handler: Handler) -> RestGitHub:
    return RestGitHub("harisw/tmdb-app", "secret-token", transport=httpx.MockTransport(handler))


def raw_issue(number: int, **overrides: Any) -> dict[str, Any]:
    return {
        "number": number,
        "html_url": f"https://github.com/harisw/tmdb-app/issues/{number}",
        "title": "t",
        "body": "b",
        "state": "open",
        "labels": [{"name": "agent"}],
        "user": {"login": "harisw"},
        **overrides,
    }


def raw_pull(number: int, ref: str = "agent/fix-abc", **overrides: Any) -> dict[str, Any]:
    return {
        "number": number,
        "html_url": f"https://github.com/harisw/tmdb-app/pull/{number}",
        "head": {"ref": ref},
        "state": "open",
        "merged_at": None,
        "draft": True,
        "base": {"ref": "master"},
        **overrides,
    }


def test_requests_authenticate_and_target_the_repository():
    seen: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen.append(request)
        return httpx.Response(200, json=[])

    github(handler).agent_issues()

    assert seen[0].headers["authorization"] == "Bearer secret-token"
    assert seen[0].url.path == "/repos/harisw/tmdb-app/issues"
    assert dict(seen[0].url.params) == {"per_page": "100", "labels": "agent", "state": "all"}


def test_agent_issues_skip_pull_requests_and_follow_pagination():
    def handler(request: httpx.Request) -> httpx.Response:
        if request.url.params.get("page") == "2":
            return httpx.Response(200, json=[raw_issue(3)])
        next_page = f"<{request.url.copy_add_param('page', '2')}>; rel=\"next\""
        return httpx.Response(200, json=[raw_issue(1), raw_issue(2, pull_request={})], headers={"Link": next_page})

    assert [issue.number for issue in github(handler).agent_issues()] == [1, 3]


def test_issue_with_no_body_maps_to_an_empty_string():
    issues = github(lambda request: httpx.Response(200, json=[raw_issue(1, body=None)])).agent_issues()

    assert issues[0].body == ""
    assert issues[0].labels == {"agent"}
    assert issues[0].author == "harisw"


def test_pulls_for_branch_filter_by_owner_and_report_merged():
    seen: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen.append(request)
        return httpx.Response(200, json=[raw_pull(5, merged_at="2026-11-08T00:00:00Z", state="closed")])

    [pull] = github(handler).pulls_for_branch("agent/fix-abc", "master")

    assert seen[0].url.params["head"] == "harisw:agent/fix-abc"
    assert seen[0].url.params["base"] == "master"
    assert seen[0].url.params["state"] == "all"
    assert pull.merged is True


def test_open_agent_pull_count_ignores_other_branches():
    pulls = [raw_pull(1), raw_pull(2, "feature/x"), raw_pull(3, "agent/fix-def")]

    assert github(lambda request: httpx.Response(200, json=pulls)).open_agent_pull_count() == 2


def test_create_pull_is_always_a_draft():
    sent: list[dict[str, Any]] = []

    def handler(request: httpx.Request) -> httpx.Response:
        sent.append(json.loads(request.content))
        return httpx.Response(201, json=raw_pull(9))

    pull = github(handler).create_pull(head="agent/fix-abc", base="triage-sandbox", title="t", body="b")

    assert sent == [{"head": "agent/fix-abc", "base": "triage-sandbox", "title": "t", "body": "b", "draft": True}]
    assert pull.number == 9


def test_update_issue_sends_only_the_given_fields():
    sent: list[dict[str, Any]] = []

    def handler(request: httpx.Request) -> httpx.Response:
        sent.append(json.loads(request.content))
        return httpx.Response(200, json=raw_issue(1))

    github(handler).update_issue(1, state="open")

    assert sent == [{"state": "open"}]


def test_remove_labels_encodes_names_and_ignores_labels_already_gone():
    paths: list[str] = []

    def handler(request: httpx.Request) -> httpx.Response:
        paths.append(request.url.raw_path.decode())
        return httpx.Response(404, json={"message": "Label does not exist"})

    github(handler).remove_labels(1, ["triage:needs-human"])

    assert paths == ["/repos/harisw/tmdb-app/issues/1/labels/triage%3Aneeds-human"]


@pytest.mark.parametrize("status", [429, 500, 502, 503, 504])
def test_server_errors_are_transient(status: int):
    with pytest.raises(GitHubTransientError):
        github(lambda request: httpx.Response(status, text="busy")).agent_issues()


def test_rate_limited_403_is_transient():
    response = httpx.Response(403, headers={"x-ratelimit-remaining": "0"}, text="limit")

    with pytest.raises(GitHubTransientError):
        github(lambda request: response).agent_issues()


def test_permission_403_fails_without_retrying():
    with pytest.raises(GitHubError) as caught:
        github(lambda request: httpx.Response(403, text="Resource not accessible")).agent_issues()

    assert not isinstance(caught.value, GitHubTransientError)
    assert "Resource not accessible" in str(caught.value)


def test_network_failure_is_transient():
    def handler(request: httpx.Request) -> httpx.Response:
        raise httpx.ConnectTimeout("slow", request=request)

    with pytest.raises(GitHubTransientError):
        github(handler).agent_issues()


def test_errors_never_include_the_token():
    with pytest.raises(GitHubError) as caught:
        github(lambda request: httpx.Response(422, text="invalid")).add_comment(1, "body")

    assert "secret-token" not in str(caught.value)


def test_authenticated_login_reads_the_user_endpoint():
    seen: list[str] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen.append(request.url.path)
        return httpx.Response(200, json={"login": "harisw"})

    assert github(handler).authenticated_login() == "harisw"
    assert seen == ["/user"]