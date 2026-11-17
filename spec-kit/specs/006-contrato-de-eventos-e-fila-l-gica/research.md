# Research — spec-006 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: fluxo `Moodle event → observer → assistant event`, persistido. **Rationale**: [REQ §Contratos]. **Alternatives considered**: n/a.
- **Decision**: estados `pending`, `processing`, `processed`, `failure`. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: nenhuma chamada LLM no observer. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-006-01 Eventos Moodle 5.2 | ABERTO — verificar | §Conformidade | — |
| Q-006-02 `event_type` | ABERTO | Não definido | — |
| Q-006-03 Payload | ABERTO | Não definido | — |
| Q-006-04 Deduplicação | ABERTO — decisão humana | Esquema sem chave única | — |
| Q-006-04 Publicação / "exatamente um" | RESOLVIDO no nível funcional (DEC-31, 25/10/2026): primeira visibilidade aos estudantes, um evento por questionário | Lacuna (1) do P-01 | criação do módulo; primeira visibilidade + data de abertura (opções C e B, não adotadas) |
| Q-006-05 "Relevante" | RESOLVIDO pela spec (DEC-03, 22/10/2026): toda postagem ou interação no fórum gera evento *(alterado em 25/10/2026; original: "ABERTO")* | Não definido (até 22/10/2026) | — |
| Q-006-06 Transições | ABERTO | Só os estados estão definidos | — |
| Q-006-07 Outras fontes na fila | ABERTO | Não definido | — |
| Q-006-08 Curso desabilitado | ABERTO | Não definido | — |
