# Research — spec-008 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: tipos PDF, texto e YouTube; resumos assíncronos. **Rationale**: [REQ §Objetivo]. **Alternatives considered**: n/a.
- **Decision**: cada alteração gera nova versão; a anterior permanece; a nova vira corrente. **Rationale**: [REQ §Regras]. *(25/10/2026: "alteração" definida por DEC-33 D3 como mudança de conteúdo processável.)* **Alternatives considered**: n/a.
- **Decision**: contexto = snapshot imutável por processing; apenas materiais da mesma seção. **Rationale**: [REQ §Contratos, §Regras]. **Alternatives considered**: n/a.
- **Decision**: File API para arquivos. **Rationale**: [REQ §Conformidade]. **Alternatives considered**: n/a.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-008-01 Disponibilidade parcial | RESOLVIDO pela spec no nível funcional (DEC-26, 22/10/2026); API a validar (Q-004-01) *(alterado em 25/10/2026; original: "ABERTO — decisão humana")* *(04/11/2026, revisão dos planos: Q-004-01 resolvida no plano 004; os readers foram validados nas CL correspondentes, todas Fechadas.)* | A constituição §6 restringe, mas a spec não definia (até 22/10/2026) | — |
| Q-008-02 Mapeamento de tipos | RESOLVIDO (DEC-33 D2): `mod_resource` PDF, `mod_page`, `mod_url` YouTube *(original: "ABERTO")* | Não definido (até 25/10/2026) | — |
| Q-008-03 Extração PDF/YouTube | ABERTO; depende de CL-19 e validação técnica *(25/10/2026)*. *(31/10/2026, DEC-60: fornecedor OpenRouter e modelo administrativo; capacidades dependem do modelo configurado; continua aberto)* *(08/11/2026: CL-19 foi fechado em 03/11/2026, por decisão do responsável; a pendência afirmada nesta célula não está mais aberta. Texto anterior preservado, conforme a constituição §11.)* | Não definida | — |
| Q-008-04 "Alteração" | RESOLVIDO (DEC-33 D3): só o conteúdo processável *(original: "ABERTO")* | Não definida (até 25/10/2026) | leitura literal: toda alteração (não adotada) |
| Q-008-05 Falha de resumo | ABERTO | Não definida | — |
| Q-008-06 `context_json` | RESOLVIDO (T064; Q-017-02): formato 1 documentado no docblock de `context_snapshot_builder` e em `contracts/material-context-snapshot.md`; versões usadas só em `context_json` (31/10/2026) *(original: "ABERTO")* | Não definido (até 26/10/2026) | — |
| Q-008-07 `availability` duplicado | RESOLVIDO em 31/10/2026 pela DEC-51 e sua ratificação complementar, implementadas na T058 (commit 6384411). A disponibilidade atual do material é atualizável e representa o último estado conhecido; a disponibilidade da versão é histórica e imutável; o snapshot avalia a disponibilidade por estudante e contexto. O fluxo de reavaliação e as transições de `pending` para `available` ou `unavailable` foram especificados, implementados e testados. **Ressalva:** `material.availability` é o último estado conhecido e atualizável, não uma garantia de disponibilidade em tempo real; `material_version.availability` é o estado histórico registrado na criação da versão, imutável; o snapshot é a avaliação independente por estudante e contexto e é a referência para decidir o que entra no contexto de análise. Mudanças que não geram `material_changed` podem deixar o indicador persistido desatualizado, o que não invalida a avaliação do snapshot. *(estado anterior: "PARCIAL"; original: "ABERTO")* | Não definido (até 26/10/2026) | — |
| Q-008-08 Mudança de seção | RESOLVIDO (DEC-33 D3): atualiza a seção sem versão nova *(original: "ABERTO")* | Não definida (até 25/10/2026) | — |
| Q-008-09 Detecção de indisponibilidade | RESOLVIDO pela spec (DEC-26): avaliada a cada snapshot, sem evento *(original: "ABERTO")* | Não definida (até 25/10/2026) | — |
