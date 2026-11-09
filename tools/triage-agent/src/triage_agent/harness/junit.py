import xml.etree.ElementTree as ElementTree
from dataclasses import dataclass, field


@dataclass(frozen=True)
class Problem:
    kind: str
    type: str
    text: str


@dataclass(frozen=True)
class TestCase:
    name: str
    file: str
    problem: Problem | None = None
    skipped: bool = False

    @property
    def passed(self) -> bool:
        return self.problem is None and not self.skipped


@dataclass(frozen=True)
class TestReport:
    cases: tuple[TestCase, ...] = field(default_factory=tuple)
    exit_code: int = 0
    output: str = ""

    @property
    def failed(self) -> tuple[TestCase, ...]:
        return tuple(case for case in self.cases if case.problem is not None)

    @property
    def passed(self) -> bool:
        return self.exit_code == 0 and bool(self.cases) and not self.failed

    def cases_in(self, path: str) -> tuple[TestCase, ...]:
        return tuple(case for case in self.cases if case.file == path or case.file.endswith("/" + path))


def parse_junit(xml: str, *, exit_code: int = 0, output: str = "") -> TestReport:
    root = ElementTree.fromstring(xml)
    cases = tuple(_case(element) for element in root.iter("testcase"))
    return TestReport(cases=cases, exit_code=exit_code, output=output)


def _case(element: ElementTree.Element) -> TestCase:
    problem = None
    for kind in ("error", "failure"):
        if (child := element.find(kind)) is not None:
            problem = Problem(kind=kind, type=child.get("type", ""), text=child.text or "")
            break
    return TestCase(
        name=element.get("name", ""),
        file=element.get("file", ""),
        problem=problem,
        skipped=element.find("skipped") is not None,
    )
