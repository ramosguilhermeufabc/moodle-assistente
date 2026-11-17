# Contract — Aplicação de critérios (spec-015)

| Operação | Entrada | Saída | Regras |
|---|---|---|---|
| `evaluateStudents(course)` | critérios do curso; atividade, fórum e frequência por estudante | `criteria_result` por estudante | determinístico; sem limiares implícitos; sem LLM [REQ] |

Comparações: critério de mínimo atende se valor >= limite e fica abaixo se < limite; critério de máximo atende se valor <= limite e ultrapassa se > limite [REQ spec-015 §Regras; DEC-11]. NEEDS CLARIFICATION: unidade da atividade (Q-015-02), fórum (Q-015-03), frequência (Q-015-04). (04/11/2026: resolvido: Q-015-02, Q-015-03 e Q-015-04 Resolvidas por DEC-40, conforme a tabela de questões de specs/015-*/plan.md; implementação em assistente/classes/domain/criteria/criteria_evaluator.php) Saída listada com desempate em ordem alfabética crescente do nome (DEC-02). *(Alterado em 25/10/2026; texto original: "Comparações (todas NEEDS CLARIFICATION): atividade (Q-015-01/02), fórum (Q-015-03), frequência (Q-015-04).")* (04/11/2026: resolvido: o mesmo, por DEC-40 e DEC-11) *(25/10/2026: Q-015-02 a 04 resolvidas por DEC-40; ver abaixo.)*
Consumidores: 018 ("alunos abaixo dos critérios"), 019.

## Regras por dimensão *(incluído em 25/10/2026, DEC-40)*

Critérios numéricos em percentual 0–100, de **mínimo** (DEC-11; `criteria` não guarda o tipo, e nota e frequência são grandezas em que mais é melhor). Valores comparados como armazenados, truncados a três casas.

| Dimensão | Valor comparado | Regra | `no_data` (2) |
|---|---|---|---|
| Atividade (D2) | total final do curso do estudante, em percentual, do snapshot da `analysis` `grade_performance` atual (DEC-37 D3) | `1` se `valor >= activity_criterion`; `0` se `<` | total do curso ausente |
| Fórum (D3) | postagens do estudante no Moodle | `forum_criterion = 0` ⇒ `1`. `forum_criterion = 1` ⇒ `1` se postou ≥ 1 vez em **cada** fórum elegível (DEC-39 D1, inclusive disponível ao estudante) cuja data de referência (DEC-39 D2) passou; senão `0` | nenhum fórum elegível com data de referência vencida |
| Frequência (D4) | `Σ participating_activities / Σ total_activities × 100` sobre as linhas `frequency` da `analysis` `frequency` atual do estudante, truncado a três casas | `1` se `valor >= frequency_criterion`; `0` se `<` | `Σ total_activities = 0` |

**Estados:** `1 = atende`, `0 = abaixo`, `2 = no_data` em cada `*_status` (`int(1)`, sem campo novo). Falha de processamento ⇒ `error` (processamento em `failure`), sem linha nova. Ausência de dados nunca vira `0`.

**Sem critérios:** curso sem registro em `criteria` ⇒ nenhuma `criteria_result` (comportamento geral em Q-005-07, CL-27).

**Gatilhos:** após `analysis` bem-sucedida de 012 ou 014 do estudante; após evento de postagem em fórum; a Scheduled Task recalcula o curso quando vence a data de referência de um fórum.

**Histórico:** cada execução é uma `analysis` imutável `criteria_check`; a atual é a última bem-sucedida. Sem LLM; a análise de dificuldades da spec-013 não é usada.
