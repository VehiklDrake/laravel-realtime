#!/usr/bin/env python
from __future__ import annotations

import os
from pathlib import Path

from crewai.flow import Flow, listen, start
from pydantic import BaseModel

from laravel_vue_dev_team.crews.dev_team.dev_team import DevTeamCrew


class DevelopmentState(BaseModel):
    development_request: str = ""
    project_root: str = ""
    final_report: str = ""


class DevelopmentFlow(Flow[DevelopmentState]):
    @start()
    def prepare_request(self) -> None:
        print(f"Project: {self.state.project_root}")
        print(f"Request: {self.state.development_request}")

    @listen(prepare_request)
    def run_development_team(self) -> None:
        result = DevTeamCrew().crew().kickoff(
            inputs={
                "development_request": self.state.development_request,
                "project_root": self.state.project_root,
            }
        )
        self.state.final_report = result.raw

    @listen(run_development_team)
    def report_completion(self) -> None:
        print("\nDevelopment team finished.")
        print("Report: output/development-report.md")


def project_root() -> Path:
    configured_root = Path(os.getenv("LARAVEL_PROJECT_ROOT", "../.."))
    root = configured_root if configured_root.is_absolute() else Path.cwd() / configured_root
    root = root.resolve()

    if not (root / "artisan").is_file() or not (root / "composer.json").is_file():
        raise RuntimeError("Run `crewai run` from the Laravel project root.")

    return root


def kickoff() -> None:
    root = project_root()
    os.environ["CREWAI_PROJECT_ROOT"] = str(root)

    development_request = os.getenv("DEVELOPMENT_REQUEST", "").strip()

    if not development_request:
        development_request = input("What should the development team build or fix? ").strip()

    if not development_request:
        raise ValueError("A development request is required.")

    DevelopmentFlow().kickoff(
        inputs={
            "development_request": development_request,
            "project_root": str(root),
        }
    )


def plot() -> None:
    DevelopmentFlow().plot("laravel_vue_dev_team")


if __name__ == "__main__":
    kickoff()
