from typing import Any

import pytest


def make_exception(**overrides: Any) -> dict[str, Any]:
    return {
        "class": "ErrorException",
        "message": 'Undefined array key "results"',
        "code": 0,
        "file": "/var/www/app/Pipelines/Stages/ProcessWatchProviders.php:19",
        "trace": [
            "/var/www/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:219",
            "/var/www/app/Pipelines/Stages/ProcessKeywords.php:31",
            "/var/www/app/Jobs/EnrichTitleJob.php:42",
            "/var/www/artisan:16",
        ],
        **overrides,
    }


def make_record(exception: dict[str, Any] | None = None, **overrides: Any) -> dict[str, Any]:
    return {
        "message": "Job failed",
        "context": {"exception": exception if exception is not None else make_exception()},
        "level": 400,
        "level_name": "ERROR",
        "channel": "production",
        "datetime": "2026-11-07T03:04:05+00:00",
        "extra": {},
        **overrides,
    }


@pytest.fixture
def record() -> dict[str, Any]:
    return make_record()
