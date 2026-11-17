# Research — spec-013 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: análise incremental = resumo atual + nova mensagem + contexto da seção. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: toda postagem ou interação no fórum gera evento; histórico preservado. **Rationale**: [REQ §Regras; DEC-03, propagada em 22/10/2026]. *(Alterado em 25/10/2026; texto original: "cada atualização relevante gera evento".)* **Alternatives considered**: n/a.
- **Decision**: dificuldades persistidas por discussão em `forum_difficulty`. **Rationale**: [REQ §Contratos]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 25/10/2026, DEC-38)*
- **D1**: 0..N dificuldades por discussão; snapshot completo; substituição da discussão afetada; cópia das demais; gravação atômica. **Alternatives considered**: acumular sem remover.
- **D2**: edição incremental; exclusão = reconstrução completa. **Alternatives considered**: ignorar edições e exclusões até a próxima mensagem.

## Decididos no plano *(incluído em 25/10/2026)*
- Resumo em `analysis.summary` (`forum_analysis`); primeira mensagem com resumo vazio; seção não resolvida = erro técnico; sem identificadores do autor (DEC-34 D3).

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-013-01 Persistência do resumo | RESOLVIDO — `analysis.summary` *(original: "ABERTO — decisão humana")* | Campo existente; sem alteração de esquema | `analysis.summary`; alterar o esquema (exige aprovação) |
| Q-013-02 "Relevante" | RESOLVIDO pela spec (DEC-03, 22/10/2026): toda postagem ou interação no fórum *(alterado em 25/10/2026; original: "ABERTO")* | Não definido (até 22/10/2026) | — |
| Q-013-03 Cardinalidade de dificuldades | RESOLVIDO — DEC-38 D1 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-013-04 Primeira mensagem | RESOLVIDO — resumo vazio *(original: "ABERTO")* | Regra incremental | — |
| Q-013-05 Edição/remoção | RESOLVIDO — DEC-38 D2 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-013-06 Dependências divergentes | RESOLVIDO — README corrigido *(original: "ABERTO")* | spec.md prevalece | — |
| Q-013-07 Resultado de fórum por estudante | ABERTO — revisão do plano 015 (CL-05/CL-12) *(08/11/2026: CL-05 foi fechado em 25/10/2026, por DEC-40, e CL-12 em 25/10/2026, por DEC-40 D3; as pendências afirmadas nesta célula não estão mais abertas. Texto anterior preservado, conforme a constituição §11.)* | Não definido | — |
