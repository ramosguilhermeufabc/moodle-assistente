# Research — spec-019 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: um relatório lógico por curso, com versões históricas consultáveis. **Rationale**: [REQ §Contratos, §Regras]. **Alternatives considered**: n/a.
- **Decision**: atualização a cada 72 horas desde a última atualização bem-sucedida até a data final da atividade elegível mais distante; atualização final em D+1, única quando coincide com a periódica. **Rationale**: [REQ §Regras; DEC-06, propagada em 22/10/2026]. *(Alterado em 25/10/2026; texto original: "atualização a cada 3 dias até a data final da atividade mais distante; atualização final em D+1.")* **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 26/10/2026, DEC-44)*
- **D1**: `snapshot_json` como manifesto de referências, renderizado na leitura, sem expor estudantes excluídos. **Alternatives considered**: cópia integral dos valores (duplicaria dados pessoais fora da DEC-42 D1 e fixaria o filtro de grupo).
- **D2**: fuso do site; "dia seguinte" = 00:00 do próximo dia-calendário; 72 h absolutas. **Alternatives considered**: exatamente 24 h depois da data.
- **D3**: reabrir quando a data é movida para o futuro depois da final. **Alternatives considered**: encerrar definitivamente.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-019-01 Conteúdo | RESOLVIDO — DEC-44 D1 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-019-02 Atividade mais distante | RESOLVIDO — DEC-39 D1, data própria *(original: "ABERTO")* | DEC-07 | — |
| Q-019-03 Sem datas | RESOLVIDO — DEC-07, DEC-29 *(original: "ABERTO")* | NOT NULL no esquema (até DEC-29) | — |
| Q-019-04 Criação | RESOLVIDO — na primeira atividade elegível datada *(original: "ABERTO")* | P-05 | — |
| Q-019-05 Âncora/fuso | RESOLVIDO — DEC-06, DEC-44 D2 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-019-06 Via fila? | RESOLVIDO — despachante próprio (P-05) *(original: "ABERTO — decisão humana")* | A spec diz "pode usar Tasks API"; P-05 aprovado | Scheduled Task direta; fila 006/007 |
| Q-019-07 Pós-final | RESOLVIDO — DEC-44 D3 *(original: "ABERTO")* | Decisão do responsável | — |
