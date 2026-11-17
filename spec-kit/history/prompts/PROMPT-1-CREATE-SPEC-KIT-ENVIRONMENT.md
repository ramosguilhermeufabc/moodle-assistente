# Prompt 1 — Create Spec Kit Environment

> **`HISTÓRICO`** · movido em 08/11/2026 de `spec-kit/prompts/PROMPT-1-CREATE-SPEC-KIT-ENVIRONMENT.md` para `spec-kit/history/prompts/PROMPT-1-CREATE-SPEC-KIT-ENVIRONMENT.md`, sem alteração de conteúdo. Índice do histórico: [`README.md`](../README.md).

## Objective
Create the Spec Kit working environment in the dedicated `/spec-kit` directory, using the official GitHub Spec Kit workflow and the **Claude integration**. This prompt is only for environment creation.

## Scope and isolation
- Work only in `/spec-kit` and only on files required to create the Spec Kit environment.
- Do not configure project memory, project-specific templates, or project requirements in this phase.
- Do not run `specify`, `plan`, `tasks`, or implementation workflows.
- Do not use Codex or another agent as a substitute for Claude.
- Do not inspect or modify the Moodle plugin implementation, database schema, or `install_auditado.xml`.
- Do not search or scan GitHub project repositories. The official Spec Kit documentation/repository may be consulted only for installation instructions if necessary.

## Actions
1. Check the current directory and confirm that `/spec-kit` is the intended isolated workspace.
2. Check whether Spec Kit is already present. Avoid overwriting a working installation.
3. Create/install the official Spec Kit environment with Claude as the selected agent, following the current official installation instructions.
4. If Claude integration cannot be installed or the available runtime does not support the required commands, stop and report the exact blocker. Do not silently switch agents.
5. Verify that the installation exposes the expected Spec Kit command surface for Claude. Do not run project commands yet.

## Completion criteria
Report only:
- environment path;
- Spec Kit version/source and installation method;
- confirmation that Claude is the selected integration;
- commands detected and verification performed;
- files created/changed;
- any blocker.

Do not proceed to Prompt 2. Wait for explicit review/authorization before the next phase.
