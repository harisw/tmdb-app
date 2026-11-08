from datetime import datetime
from typing import Protocol

from triage_agent.models import ErrorEvent


class ErrorSource(Protocol):
    def fetch(self, start: datetime, end: datetime) -> list[ErrorEvent]: ...
