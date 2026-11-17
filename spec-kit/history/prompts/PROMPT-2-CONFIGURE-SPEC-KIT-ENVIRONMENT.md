# Prompt 2 — Configure Spec Kit Environment

> **`HISTÓRICO`** · movido em 08/11/2026 de `spec-kit/prompts/PROMPT-2-CONFIGURE-SPEC-KIT-ENVIRONMENT.md` para `spec-kit/history/prompts/PROMPT-2-CONFIGURE-SPEC-KIT-ENVIRONMENT.md`, sem alteração de conteúdo. Índice do histórico: [`README.md`](../README.md).

## Objective
Configure the already-created `/spec-kit` environment for this project. This phase configures the environment only; it does not generate a project plan or tasks.

## Required precondition
Prompt 1 must have completed successfully, and the user must have reviewed its result. If the Spec Kit environment or Claude integration is not verified, stop and report the blocker.

## Scope and isolation
- Work only in `/spec-kit` environment/configuration files.
- Use Claude integration only; do not substitute Codex or another agent.
- Configure project-level Spec Kit conventions, constitution/memory loading, and command behavior as supported by the installed Spec Kit version.
- Do not execute `/speckit.plan`, `/speckit.tasks`, `/speckit.implement`, or equivalent planning/task/implementation workflows.
- Do not change the content of the 23 source `spec.md` files in this phase.
- Do not modify plugin code, XMLDB, or `install_auditado.xml`.
- Do not search or scan GitHub project repositories.

## Configuration requirements
1. Inspect the installed Spec Kit version and its supported Claude command names; use the commands actually available rather than assuming aliases.
2. Configure the workspace so commands run from `/spec-kit` and use the project's constitution at `.specify/memory/constitution.md` where supported.
3. Ensure generated artifacts remain in the Spec Kit workspace and that the workflow does not invoke implementation automatically.
4. Preserve existing specs and reference files unchanged.
5. If any required configuration depends on a missing decision or unsupported feature, document it as a blocker instead of inventing a setting.

## Completion criteria
Report:
- configuration files created/changed;
- exact Claude commands available;
- how constitution/memory is loaded;
- verification that existing specs and reference files remain unchanged;
- limitations or unresolved setup issues.

Do not proceed to Prompt 3. Wait for explicit review/authorization before the next phase.
