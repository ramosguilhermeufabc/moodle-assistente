# Research — spec-005 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: Access API + context de curso no backend. **Rationale**: [REQ §Contratos, §Conformidade]. **Alternatives considered**: n/a.
- **Decision**: o professor vê só os cursos que possui; o administrador vê todos. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: três critérios separados (atividade numérico, fórum binário, frequência numérico), imutáveis e sem reprocessamento retroativo. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-005-01 "possui"/capabilities | RESOLVIDO (DEC-30): capability `local/assistente:view` no contexto do curso, padrão `editingteacher` *(original: "ABERTO")* | Não definido (até 25/10/2026) | papel `editingteacher`; inscrição com papel de professor (opções B e C, não adotadas) |
| Q-005-02 Administrador | RESOLVIDO (DEC-30): capability no contexto de sistema, padrão `manager`; administrador do site incluído pelo modelo do Moodle *(original: "ABERTO")* | Não definido (até 25/10/2026) | só administrador do site |
| Q-005-03 Cursos existentes / `enabled` | ABERTO | Não definido | — |
| Q-005-04 Alcance da imutabilidade | ABERTO | Não definido | — |
| Q-005-05 Unidade dos critérios numéricos | ABERTO | Não definido | — |
| Q-005-06 Semântica do critério de fórum | ABERTO | Não definido | — |
| Q-005-07 Curso sem configuração | ABERTO | Não definido | — |
