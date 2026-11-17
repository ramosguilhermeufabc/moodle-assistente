# Data Model — spec-005

Campos exatos: `../002-esquema-xmldb/spec.md` §Inventário.

## `local_assistente_config`
- `course_id` (foreign-unique → `course`), `enabled` int(1), `created_at`.
- Regra: uma configuração por curso [spec-002 §Inventário]. Semântica de `enabled`: Q-005-03.

## `local_assistente_criteria`
- `config_id` (foreign-unique → `config`), `activity_criterion` number(10,3), `forum_criterion` int(1), `frequency_criterion` number(10,3).
- Regras: atividade numérico; fórum binário; frequência numérico [REQ §Regras]. Faixas: Q-005-05/06.

## Estados
```text
[sem config] --definição na criação/configuração do curso--> [critérios definidos] (terminal: imutável)
```
Não há transição de alteração [REQ §Regras]. Habilitar/desabilitar via `enabled`: Q-005-03.
