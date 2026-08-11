from __future__ import annotations

import fnmatch
import os
import shlex
import subprocess
from pathlib import Path
from typing import Type

from crewai.tools import BaseTool
from pydantic import BaseModel, Field


EXCLUDED_DIRECTORIES = {
    ".git",
    ".venv",
    "venv",
    "node_modules",
    "vendor",
    "storage",
}
PROTECTED_PATHS = {
    ".crewai-dev-team",
    ".env",
    "pyproject.toml",
    "uv.lock",
}
PROTECTED_PREFIXES = ("src/laravel_vue_dev_team/",)
MAX_TOOL_OUTPUT = 30_000


def project_root() -> Path:
    configured_root = Path(
        os.getenv("CREWAI_PROJECT_ROOT")
        or os.getenv("LARAVEL_PROJECT_ROOT", "../..")
    )
    root = configured_root if configured_root.is_absolute() else Path.cwd() / configured_root
    root = root.resolve()

    if not (root / "artisan").is_file() or not (root / "composer.json").is_file():
        raise ValueError("CREWAI_PROJECT_ROOT must point to a Laravel project.")

    return root


def resolve_project_path(relative_path: str, *, for_write: bool = False) -> Path:
    relative = Path(relative_path)

    if relative.is_absolute() or ".." in relative.parts:
        raise ValueError("Use a relative path inside the project.")

    if any(part in EXCLUDED_DIRECTORIES for part in relative.parts):
        raise ValueError("That dependency, cache, or VCS path is protected.")

    normalized = relative.as_posix()

    if normalized == ".env" or normalized.startswith(".env."):
        raise ValueError("Environment files are protected.")

    if for_write and (
        normalized in PROTECTED_PATHS
        or normalized.startswith(PROTECTED_PREFIXES)
    ):
        raise ValueError("The CrewAI runtime and environment configuration are protected.")

    resolved = (project_root() / relative).resolve(strict=False)

    try:
        resolved.relative_to(project_root())
    except ValueError as error:
        raise ValueError("Path escapes the project root.") from error

    return resolved


class ListProjectFilesInput(BaseModel):
    pattern: str = Field(
        default="*",
        description="Glob or substring used to filter relative project paths, such as resources/js/**/*.vue.",
    )
    limit: int = Field(default=300, ge=1, le=1000)


class ListProjectFilesTool(BaseTool):
    name: str = "list_project_files"
    description: str = (
        "List repository files using a glob or substring. Dependencies, caches, and Git internals are excluded."
    )
    args_schema: Type[BaseModel] = ListProjectFilesInput

    def _run(self, pattern: str = "*", limit: int = 300) -> str:
        if ".." in Path(pattern).parts:
            return "Error: pattern cannot leave the project."

        root = project_root()
        matches: list[str] = []
        lowered_pattern = pattern.lower()

        for directory, directory_names, file_names in os.walk(root):
            directory_names[:] = [
                name for name in directory_names if name not in EXCLUDED_DIRECTORIES
            ]

            for file_name in file_names:
                relative = (Path(directory) / file_name).relative_to(root).as_posix()

                if (
                    pattern in {"", "*"}
                    or fnmatch.fnmatch(relative, pattern)
                    or lowered_pattern in relative.lower()
                ):
                    matches.append(relative)

                if len(matches) >= limit:
                    return "\n".join(sorted(matches)) + f"\n... limited to {limit} files"

        return "\n".join(sorted(matches)) if matches else "No matching files found."


class ReadProjectFileInput(BaseModel):
    path: str = Field(description="Relative path to a text file inside the project.")
    start_line: int = Field(default=1, ge=1)
    end_line: int | None = Field(default=None, ge=1)


class ReadProjectFileTool(BaseTool):
    name: str = "read_project_file"
    description: str = "Read a line range from a repository text file. Environment files are inaccessible."
    args_schema: Type[BaseModel] = ReadProjectFileInput

    def _run(self, path: str, start_line: int = 1, end_line: int | None = None) -> str:
        try:
            file_path = resolve_project_path(path)

            if not file_path.is_file():
                return f"Error: file does not exist: {path}"

            lines = file_path.read_text(encoding="utf-8").splitlines()
            final_line = min(end_line or start_line + 399, len(lines))

            if start_line > len(lines):
                return f"Error: {path} has only {len(lines)} lines."

            rendered = [
                f"{line_number}: {lines[line_number - 1]}"
                for line_number in range(start_line, final_line + 1)
            ]
            return "\n".join(rendered)
        except (OSError, UnicodeError, ValueError) as error:
            return f"Error: {error}"


class ApplyProjectPatchInput(BaseModel):
    patch: str = Field(
        description="A complete unified Git patch with diff --git headers and paths relative to the repository."
    )


class ApplyProjectPatchTool(BaseTool):
    name: str = "apply_project_patch"
    description: str = (
        "Apply a unified Git patch to repository source or test files. The patch is checked first; secrets, dependencies, "
        "Git metadata, and the CrewAI runtime cannot be changed."
    )
    args_schema: Type[BaseModel] = ApplyProjectPatchInput

    def _run(self, patch: str) -> str:
        if not patch.strip() or len(patch) > 250_000:
            return "Error: patch is empty or exceeds 250,000 characters."

        touched_paths: set[str] = set()

        try:
            for line in patch.splitlines():
                if line.startswith("diff --git "):
                    parts = shlex.split(line)
                    if len(parts) != 4:
                        return "Error: malformed diff --git header."
                    touched_paths.update(part[2:] for part in parts[2:] if part != "/dev/null")
                elif line.startswith(("+++ ", "--- ")):
                    raw_path = line[4:].split("\t", 1)[0]
                    if raw_path != "/dev/null":
                        touched_paths.add(raw_path[2:] if raw_path.startswith(("a/", "b/")) else raw_path)

            if not touched_paths:
                return "Error: patch contains no file paths."

            for touched_path in touched_paths:
                resolve_project_path(touched_path, for_write=True)
        except ValueError as error:
            return f"Error: {error}"

        root = project_root()
        check = subprocess.run(
            ["git", "apply", "--check", "--whitespace=nowarn", "-"],
            cwd=root,
            input=patch,
            text=True,
            capture_output=True,
            timeout=30,
            check=False,
        )

        if check.returncode != 0:
            return f"Patch check failed:\n{check.stderr[-MAX_TOOL_OUTPUT:]}"

        applied = subprocess.run(
            ["git", "apply", "--whitespace=nowarn", "-"],
            cwd=root,
            input=patch,
            text=True,
            capture_output=True,
            timeout=30,
            check=False,
        )

        if applied.returncode != 0:
            return f"Patch application failed:\n{applied.stderr[-MAX_TOOL_OUTPUT:]}"

        return "Applied patch to:\n" + "\n".join(sorted(touched_paths))


class RunProjectCommandInput(BaseModel):
    command: str = Field(
        description=(
            "A non-interactive project command. Allowed families include git status/diff, selected php artisan commands, "
            "Pest/Pint/PHPStan, selected npm scripts, and Composer test/lint/analyse scripts."
        )
    )
    timeout_seconds: int = Field(default=180, ge=1, le=600)


class RunProjectCommandTool(BaseTool):
    name: str = "run_project_command"
    description: str = (
        "Run a restricted, non-shell command in the Laravel project and return its output. Destructive Artisan, Git, "
        "package-installation, server, and migration commands are blocked."
    )
    args_schema: Type[BaseModel] = RunProjectCommandInput

    def _run(self, command: str, timeout_seconds: int = 180) -> str:
        try:
            arguments = shlex.split(command)
        except ValueError as error:
            return f"Error: {error}"

        if not arguments:
            return "Error: command is empty."

        if not self._is_allowed(arguments):
            return "Error: command is not in the project-safe allowlist."

        if arguments[:2] == ["php", "artisan"] and arguments[2].startswith("make:"):
            if "--no-interaction" not in arguments:
                arguments.append("--no-interaction")

        environment = os.environ.copy()
        environment["CI"] = "1"

        try:
            result = subprocess.run(
                arguments,
                cwd=project_root(),
                env=environment,
                text=True,
                capture_output=True,
                timeout=timeout_seconds,
                check=False,
            )
        except (OSError, subprocess.TimeoutExpired) as error:
            return f"Error: {error}"

        output = (result.stdout + result.stderr)[-MAX_TOOL_OUTPUT:]
        return f"Exit code: {result.returncode}\n{output}"

    @staticmethod
    def _is_allowed(arguments: list[str]) -> bool:
        unsafe_arguments = {
            "--ext-diff",
            "--no-index",
            "--textconv",
        }

        if any(
            argument in unsafe_arguments
            or argument.startswith("--output")
            or Path(argument).is_absolute()
            or ".." in Path(argument).parts
            for argument in arguments[1:]
        ):
            return False

        if arguments[0] == "git" and len(arguments) >= 2:
            if arguments[1] == "status":
                return True
            if arguments[1] == "diff":
                return True
            if arguments[1] == "ls-files":
                return True

        if arguments[:2] == ["php", "artisan"] and len(arguments) >= 3:
            artisan_command = arguments[2]
            return artisan_command in {
                "about",
                "config:show",
                "list",
                "route:list",
                "test",
            } or artisan_command.startswith("make:")

        if arguments[0] in {
            "vendor/bin/pest",
            "vendor/bin/phpstan",
            "vendor/bin/pint",
        }:
            return True

        if arguments[:2] == ["npm", "run"] and len(arguments) >= 3:
            return arguments[2] in {
                "build",
                "lint",
                "test",
                "type-check",
                "format:check",
            }

        if arguments[:1] == ["npm"] and len(arguments) >= 2:
            return arguments[1] == "test"

        if arguments[:1] == ["composer"] and len(arguments) >= 2:
            return arguments[1] in {"analyse", "lint", "test"}

        return False


class InspectProjectCommandTool(RunProjectCommandTool):
    name: str = "inspect_project_command"
    description: str = (
        "Run a read-only Git or Artisan inspection command in the Laravel project. File generators, formatters, tests, "
        "builds, migrations, and package commands are unavailable."
    )

    @staticmethod
    def _is_allowed(arguments: list[str]) -> bool:
        if not RunProjectCommandTool._is_allowed(arguments):
            return False

        if arguments[0] == "git":
            return True

        return arguments[:2] == ["php", "artisan"] and arguments[2] in {
            "about",
            "config:show",
            "list",
            "route:list",
        }
