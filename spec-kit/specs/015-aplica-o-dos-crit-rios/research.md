# Research — spec-015 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: aplicação determinística, sem LLM e sem limiares implícitos. **Rationale**: [REQ §Regras, §Conformidade]. **Alternatives considered**: n/a.
- **Decision**: atividade numérico; fórum binário; frequência conforme critério configurado. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 25/10/2026, DEC-40)*
- **D1**: critérios numéricos em percentual 0–100. **Alternatives considered**: escala do item.
- **D2**: atividade = total final do curso (DEC-37 D3). **Alternatives considered**: abaixo se qualquer nota estiver abaixo.
- **D3**: fórum = ≥ 1 postagem em cada fórum elegível vencido. **Alternatives considered**: ≥ 1 postagem em qualquer fórum; usar a análise de dificuldades (descartado: não é por estudante e exigiria LLM).
- **D4**: frequência global agregada. **Alternatives considered**: média das seções; abaixo em qualquer seção.

## Decididos no plano *(incluído em 25/10/2026)*
- Critérios de mínimo; status 1/0/2; gatilhos por análise e por postagem; sem critérios, sem linha; `criteria_check` imutável.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-015-01 Operador | RESOLVIDO pela spec (DEC-11, 22/10/2026): mínimo `>=` atende e `<` abaixo; máximo `<=` atende e `>` ultrapassa *(alterado em 25/10/2026; original: "ABERTO — decisão humana")* | Não definido (até 22/10/2026) | `<`; `≤` |
| Q-015-02 Agregação da atividade | RESOLVIDO — DEC-40 D1/D2 *(original: "ABERTO — decisão humana")* | Decisão do responsável | por atividade; média |
| Q-015-03 Fórum por estudante | RESOLVIDO — DEC-40 D3 *(original: "ABERTO")* | Contradição com 013 resolvida pelas postagens | — |
| Q-015-04 Frequência | RESOLVIDO — DEC-40 D4 *(original: "ABERTO")* | Decisão do responsável | por seção; consolidada |
| Q-015-05 `*_status` | RESOLVIDO — 1/0/2 *(original: "ABERTO")* | DEC-24; P-06 | — |
| Q-015-06 Gatilho | RESOLVIDO — por análise e por postagem *(original: "ABERTO")* | Padrão de DEC-37/39 | — |
| Q-015-07 Dados ausentes | RESOLVIDO — `2 = no_data` *(original: "ABERTO")* | DEC-24 | — |
| Q-015-08 Frequência 005×015 | RESOLVIDO — mesmo `frequency_criterion` *(original: "ABERTO")* | spec-002 | — |
