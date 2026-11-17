# Research — spec-012 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: usar a nota final do gradebook, sem pesos próprios. **Rationale**: [REQ §Regras, §Conformidade]. **Alternatives considered**: n/a.
- **Decision**: a média do estudante deriva das notas conforme a configuração do Moodle. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 25/10/2026, DEC-37)*
- **D1**: eventos de nota na fila; recálculo por estudante no curso. **Alternatives considered**: ciclo periódico; eventos + ciclo.
- **D2**: percentual do intervalo do item, truncado. **Alternatives considered**: nota bruta na escala do item.
- **D3**: média = total final do curso do gradebook, no snapshot. **Alternatives considered**: média simples das atividades (ponderação implícita própria, contrária à spec).

## Decididos no plano *(incluído em 25/10/2026)*
- Sem nota ⇒ sem linha (`no_data`); escopo de atividades por `cmid` e tipo de nota; `analysis` `grade_performance` imutável.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-012-01 Gatilho | RESOLVIDO — DEC-37 D1 *(original: "ABERTO")* | Decisão do responsável | evento; ciclo periódico |
| Q-012-02 Nota ausente | RESOLVIDO — sem linha, `no_data` *(original: "ABERTO")* | `grade` NOT NULL; DEC-24, P-06 | — |
| Q-012-03 Média do estudante | RESOLVIDO — DEC-37 D3 *(original: "ABERTO")* | Sem campo; snapshot | — |
| Q-012-04 Escopo de atividades | RESOLVIDO — itens com `cmid`, numéricos ou escala *(original: "ABERTO")* | spec-012 §Contratos | — |
| Q-012-05 Escala | RESOLVIDO — DEC-37 D2 *(original: "ABERTO")* | Decisão do responsável | — |
