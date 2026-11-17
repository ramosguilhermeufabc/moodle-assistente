# Research — spec-004 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: preferir APIs de domínio Moodle; DML só sem API apropriada. **Rationale**: [REQ §Contratos, §Conformidade]. **Alternatives considered**: n/a.
- **Decision**: identificar módulos por `cmid` e questões por `question_id + question_version`; `slot` não é requisito. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: notas vêm do gradebook. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Decididos por decisões aprovadas *(incluído em 26/10/2026)*
- Materiais (DEC-33 D2); estudante (DEC-37, DEC-47); grupos (DEC-43); participação (DEC-39); questionário `mod_quiz` (DEC-35, DEC-36); nota e total (DEC-37); disponibilidade por usuário (DEC-26); idioma e fuso (DEC-44, DEC-46).

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-004-01 APIs por reader | RESOLVIDO no nível funcional — APIs candidatas por reader; verificação nas CL *(original: "ABERTO — verificar no Moodle 5.2")* | Constituição §5 | — |
| Q-004-02 Mapeamento de materiais | RESOLVIDO — DEC-33 D2 *(original: "ABERTO")* | — | — |
| Q-004-03 Definição de estudante | RESOLVIDO — DEC-37, DEC-47 *(original: "ABERTO")* | — | — |
| Q-004-04 Fontes de participação | RESOLVIDO — DEC-39 *(original: "ABERTO")* | — | — |
| Q-004-05 Módulo de "questionário" | RESOLVIDO — `mod_quiz` (DEC-35) *(original: "ABERTO")* | — | — |
| Q-004-06 Valor final de nota | RESOLVIDO — DEC-37 *(original: "ABERTO")* | Ver Q-012-03 | — |
