from fakes import APP_FILE, TEST_PATH, failing_case, passing_case, raw_event, report

from triage_agent.harness.expected import check_expected_failure
from triage_agent.harness.git import Change
from triage_agent.harness.junit import parse_junit
from triage_agent.harness.verify import VerifyInputs, verify
from triage_agent.sanitize import clean_event

JUNIT = f"""<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="Feature" tests="3">
    <testcase name="test_fails" class="Tests\\Feature\\X" file="/app/{TEST_PATH}" line="20">
      <error type="ErrorException">Tests\\Feature\\X::test_fails
ErrorException: Undefined array key "results"

/app/{APP_FILE}:19
/app/{TEST_PATH}:30</error>
    </testcase>
    <testcase name="test_passes" class="Tests\\Feature\\X" file="/app/{TEST_PATH}" line="40"/>
    <testcase name="test_skipped" class="Tests\\Feature\\Y" file="/app/tests/Feature/YTest.php" line="9"><skipped/></testcase>
  </testsuite>
</testsuites>"""


def test_parses_phpunit_junit_output():
    parsed = parse_junit(JUNIT, exit_code=2)

    assert len(parsed.cases) == 3
    assert [case.name for case in parsed.failed] == ["test_fails"]
    assert parsed.failed[0].problem is not None and parsed.failed[0].problem.type == "ErrorException"
    assert len(parsed.cases_in(TEST_PATH)) == 2
    assert parsed.cases[2].skipped
    assert not parsed.passed


def test_expected_failure_needs_the_event_class_and_an_event_frame():
    event = clean_event(raw_event())

    assert check_expected_failure(report(failing_case()), TEST_PATH, event).expected
    assert not check_expected_failure(report(failing_case(error_type="TypeError")), TEST_PATH, event).expected
    only_test_frame = failing_case(trace=f"ErrorException: boom\n/app/{TEST_PATH}:30")
    assert not check_expected_failure(report(only_test_frame), TEST_PATH, event).expected
    assert not check_expected_failure(report(passing_case()), TEST_PATH, event).expected
    assert not check_expected_failure(report(), TEST_PATH, event).expected


def test_expected_failure_matches_namespaced_classes():
    event = clean_event(raw_event(error_class="Illuminate\\Database\\QueryException"))
    case = failing_case(error_type="Illuminate\\Database\\QueryException")

    assert check_expected_failure(report(case), TEST_PATH, event).expected


def inputs(**overrides: object) -> VerifyInputs:
    values: dict[str, object] = {
        "changes": [Change(TEST_PATH, "??"), Change(APP_FILE, "M")],
        "repro_path": TEST_PATH,
        "repro_hash_before": "h1",
        "repro_hash_after": "h1",
        "diff_lines": 40,
        "suite": report(passing_case()),
        "pint": (True, ""),
        "larastan": (True, ""),
    }
    return VerifyInputs(**{**values, **overrides})  # type: ignore[arg-type]


def failed_checks(**overrides: object) -> list[str]:
    result = verify(inputs(**overrides), allowed_prefixes=("app/", "routes/"), max_diff_lines=300)
    return [check.name for check in result.failures]


def test_verify_passes_a_clean_fix():
    assert failed_checks() == []


def test_verify_rejects_a_deleted_repro_test():
    assert failed_checks(repro_hash_after=None) == ["reproduction test unchanged"]


def test_verify_rejects_a_fix_that_changes_nothing():
    assert failed_checks(changes=[Change(TEST_PATH, "??")]) == ["only allowed paths changed"]


def test_verify_rejects_forbidden_paths_even_when_the_prefix_is_allowed():
    assert failed_checks(changes=[Change(APP_FILE, "M"), Change("composer.lock", "M")]) == ["only allowed paths changed"]


def test_verify_requires_the_repro_test_to_run_in_the_full_suite():
    suite = report(passing_case("tests/Feature/OtherTest.php", "test_other"))

    assert failed_checks(suite=suite) == ["reproduction test passes"]


def test_verify_skips_larastan_when_it_is_not_configured():
    result = verify(inputs(larastan=None), allowed_prefixes=("app/",), max_diff_lines=300)

    assert "larastan" not in [check.name for check in result.checks]
