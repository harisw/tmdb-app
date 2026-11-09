import re

from conftest import make_exception, make_record

from triage_agent.fingerprint import fingerprint_record, parse_frames, primary_exception


def test_output_is_safe_for_branch_names_and_thread_ids(record):
    assert re.fullmatch(r"[a-f0-9]{16}", fingerprint_record(record))


def test_is_deterministic(record):
    assert fingerprint_record(record) == fingerprint_record(make_record())


def test_ignores_line_numbers_so_deploys_keep_the_fingerprint():
    shifted = make_exception(
        file="/var/www/app/Pipelines/Stages/ProcessWatchProviders.php:27",
        trace=[
            "/var/www/app/Pipelines/Stages/ProcessKeywords.php:44",
            "/var/www/app/Jobs/EnrichTitleJob.php:90",
        ],
    )

    assert fingerprint_record(make_record(shifted)) == fingerprint_record(make_record())


def test_ignores_vendor_frame_changes_so_framework_upgrades_keep_the_fingerprint():
    upgraded = make_exception(
        trace=[
            "/var/www/vendor/laravel/framework/src/Illuminate/Pipeline/Hub.php:9",
            "/var/www/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:300",
            "/var/www/app/Pipelines/Stages/ProcessKeywords.php:31",
            "/var/www/app/Jobs/EnrichTitleJob.php:42",
        ]
    )

    assert fingerprint_record(make_record(upgraded)) == fingerprint_record(make_record())


def test_ignores_message_values_when_app_frames_exist():
    other_value = make_exception(message='Undefined array key "provider"')

    assert fingerprint_record(make_record(other_value)) == fingerprint_record(make_record())


def test_ignores_entrypoint_boilerplate_frames():
    with_boilerplate = make_exception(
        trace=[
            *make_exception()["trace"],
            "/var/www/app/Http/Middleware/AssignRequestId.php:23",
            "/var/www/public/index.php:20",
        ]
    )

    assert fingerprint_record(make_record(with_boilerplate)) == fingerprint_record(make_record())


def test_treats_alternate_deploy_roots_the_same():
    relocated = make_exception(
        file="/var/www/html/app/Pipelines/Stages/ProcessWatchProviders.php:19",
        trace=[
            "/var/www/html/app/Pipelines/Stages/ProcessKeywords.php:31",
            "/var/www/html/app/Jobs/EnrichTitleJob.php:42",
        ],
    )

    assert fingerprint_record(make_record(relocated)) == fingerprint_record(make_record())


def test_separates_exception_classes():
    other_class = make_exception(**{"class": "TypeError"})

    assert fingerprint_record(make_record(other_class)) != fingerprint_record(make_record())


def test_separates_different_code_paths():
    other_path = make_exception(file="/var/www/app/Services/TitleService.php:12", trace=[])

    assert fingerprint_record(make_record(other_path)) != fingerprint_record(make_record())


def test_separates_sqlstates_on_the_same_path():
    def query_error(state: str) -> dict:
        return make_record(
            make_exception(
                **{"class": "Illuminate\\Database\\QueryException"},
                message=f"SQLSTATE[{state}]: failed (Connection: pgsql, SQL: select 1)",
            )
        )

    assert fingerprint_record(query_error("08006")) != fingerprint_record(query_error("23505"))


def test_ignores_sql_values_with_the_same_sqlstate():
    def query_error(sql: str) -> dict:
        return make_record(
            make_exception(
                **{"class": "Illuminate\\Database\\QueryException"},
                message=f"SQLSTATE[23505]: Unique violation (SQL: {sql})",
            )
        )

    assert fingerprint_record(query_error("insert 1")) == fingerprint_record(query_error("insert 2"))


def test_separates_wrapped_causes():
    def wrapped(cause: str) -> dict:
        return make_record(make_exception(previous=make_exception(**{"class": cause})))

    assert fingerprint_record(wrapped("TypeError")) != fingerprint_record(wrapped("ValueError"))


def test_uses_a_previous_exception_when_the_outer_has_no_app_frames():
    vendor_only = {
        "file": "/var/www/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php:9",
        "trace": ["/var/www/vendor/laravel/framework/src/Illuminate/Bus/Queue.php:3"],
    }

    def wrapping(cause_file: str) -> dict:
        return make_record(make_exception(**vendor_only, previous=make_exception(file=cause_file, trace=[])))

    first = wrapping("/var/www/app/Services/TitleService.php:1")
    second = wrapping("/var/www/app/Services/UserService.php:1")

    assert fingerprint_record(first) != fingerprint_record(second)


def test_separates_vendor_only_errors_by_job():
    vendor_only = make_exception(
        **{"class": "Illuminate\\Database\\QueryException"},
        message="SQLSTATE[08006]: connection failed",
        file="/var/www/vendor/laravel/framework/src/Illuminate/Database/Connection.php:864",
        trace=["/var/www/artisan:16"],
    )

    def job_record(name: str) -> dict:
        return make_record(vendor_only, extra={"job": {"name": name, "id": "a1", "attempt": 2}})

    assert fingerprint_record(job_record("App\\Jobs\\A")) != fingerprint_record(job_record("App\\Jobs\\B"))
    assert fingerprint_record(job_record("App\\Jobs\\A")) == fingerprint_record(job_record("App\\Jobs\\A"))


def test_falls_back_to_a_normalized_message_without_frames_or_job():
    def vendor_only(message: str) -> dict:
        return make_record(make_exception(message=message, file="/var/www/vendor/a/b.php:1", trace=[]))

    assert fingerprint_record(vendor_only("Timed out after 30 seconds on 10.0.0.1")) == fingerprint_record(
        vendor_only("Timed out after 45 seconds on 10.0.0.2")
    )
    assert fingerprint_record(vendor_only("Timed out")) != fingerprint_record(vendor_only("Disk full"))


def test_groups_logged_messages_without_an_exception_by_template():
    def logged(message: str) -> dict:
        return {"message": message, "context": {}, "level_name": "ERROR", "extra": {}}

    template_one = logged('Failed title 123 for user "a@example.com" id 8f14e45f-ceea-467f-a5d5-0a1f6e3b1c2d')
    template_two = logged('Failed title 456 for user "b@example.com" id 1c2d3e4f-ceea-467f-a5d5-0a1f6e3b1c2d')

    assert fingerprint_record(template_one) == fingerprint_record(template_two)
    assert fingerprint_record(template_one) != fingerprint_record(logged("Something else"))


def test_handles_context_that_is_not_an_object():
    assert re.fullmatch(r"[a-f0-9]{16}", fingerprint_record({"message": "boom", "context": [], "extra": []}))


def test_parse_frames_normalizes_paths_and_keeps_lines(record):
    frames = parse_frames(primary_exception(record))

    assert frames[0].file == "app/Pipelines/Stages/ProcessWatchProviders.php"
    assert frames[0].line == 19
    assert frames[0].function is None


def test_parse_frames_reads_internal_frames_as_functions():
    exception = make_exception(trace=["internal[App\\Jobs\\EnrichTitleJob->__unserialize]"])

    frame = parse_frames(exception)[1]

    assert frame.file == "internal"
    assert frame.line is None
    assert frame.function == "App\\Jobs\\EnrichTitleJob->__unserialize"


def test_internal_frames_separate_otherwise_vendor_only_errors():
    def internal(function: str) -> dict:
        return make_record(make_exception(file="/var/www/vendor/a/b.php:1", trace=[f"internal[{function}]"]))

    assert fingerprint_record(internal("App\\A->x")) != fingerprint_record(internal("App\\B->x"))
