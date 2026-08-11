from __future__ import annotations

import os

from crewai import Agent, Crew, LLM, Process, Task
from crewai.agents.agent_builder.base_agent import BaseAgent
from crewai.project import CrewBase, agent, crew, task

from laravel_vue_dev_team.tools.project_tools import (
    ApplyProjectPatchTool,
    InspectProjectCommandTool,
    ListProjectFilesTool,
    ReadProjectFileTool,
    RunProjectCommandTool,
)


@CrewBase
class DevTeamCrew:
    """A repository-aware Laravel and Vue development team."""

    agents: list[BaseAgent]
    tasks: list[Task]

    agents_config = "config/agents.yaml"
    tasks_config = "config/tasks.yaml"

    def llm(self) -> LLM:
        return LLM(
            model=os.getenv(
                "CREWAI_MODEL",
                "openrouter/nvidia/nemotron-3-ultra-550b-a55b:free",
            ),
            base_url=os.getenv("CREWAI_LLM_BASE_URL") or None,
        )

    def max_requests_per_minute(self) -> int:
        configured_value = os.getenv("CREWAI_MAX_RPM", "10")

        try:
            max_requests_per_minute = int(configured_value)
        except ValueError as error:
            raise ValueError("CREWAI_MAX_RPM must be a positive integer.") from error

        if max_requests_per_minute < 1:
            raise ValueError("CREWAI_MAX_RPM must be a positive integer.")

        return max_requests_per_minute

    def read_tools(self) -> list:
        return [
            ListProjectFilesTool(),
            ReadProjectFileTool(),
            InspectProjectCommandTool(),
        ]

    def development_tools(self) -> list:
        return [
            ListProjectFilesTool(),
            ReadProjectFileTool(),
            ApplyProjectPatchTool(),
            RunProjectCommandTool(),
        ]

    @agent
    def architect(self) -> Agent:
        return Agent(
            config=self.agents_config["architect"],  # type: ignore[index]
            llm=self.llm(),
            tools=self.read_tools(),
        )

    @agent
    def laravel_engineer(self) -> Agent:
        return Agent(
            config=self.agents_config["laravel_engineer"],  # type: ignore[index]
            llm=self.llm(),
            tools=self.development_tools(),
        )

    @agent
    def vue_engineer(self) -> Agent:
        return Agent(
            config=self.agents_config["vue_engineer"],  # type: ignore[index]
            llm=self.llm(),
            tools=self.development_tools(),
        )

    @agent
    def quality_engineer(self) -> Agent:
        return Agent(
            config=self.agents_config["quality_engineer"],  # type: ignore[index]
            llm=self.llm(),
            tools=self.development_tools(),
        )

    @task
    def architecture_task(self) -> Task:
        return Task(config=self.tasks_config["architecture_task"])  # type: ignore[index]

    @task
    def backend_task(self) -> Task:
        return Task(config=self.tasks_config["backend_task"])  # type: ignore[index]

    @task
    def frontend_task(self) -> Task:
        return Task(config=self.tasks_config["frontend_task"])  # type: ignore[index]

    @task
    def quality_task(self) -> Task:
        return Task(config=self.tasks_config["quality_task"])  # type: ignore[index]

    @crew
    def crew(self) -> Crew:
        return Crew(
            agents=self.agents,
            tasks=self.tasks,
            process=Process.sequential,
            verbose=True,
            memory=False,
            max_rpm=self.max_requests_per_minute(),
        )
