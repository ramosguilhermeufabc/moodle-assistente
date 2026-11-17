# Quickstart (validação) — spec-015

> Nada foi executado. Os valores esperados dependem de Q-015-02…05 (Q-015-01 resolvida por DEC-11). *(Alterado em 25/10/2026; original: "dependem de Q-015-01…05".)* *(25/10/2026: Q-015-02 a 05 resolvidas por DEC-40 e pelos tratamentos da revisão.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Valor logo abaixo do critério numérico | Estudante marcado abaixo | §Objetivo |
| Q2 | Valor igual ao critério | Mínimo: atende; máximo: atende *(alterado em 25/10/2026; original: "Conforme Q-015-01")* | spec-015 §Regras; DEC-11 |
| Q3 | Instrumentar a porta LLM | Nenhuma chamada | §Conformidade |
| Q4 | Procurar constantes de limiar no código | Somente valores vindos de `criteria` | §Regras |
| Q5 | `activity_criterion = 60`; total do curso 59.999 / 60.000 / 60.001 | `0` / `1` / `1` | DEC-40 D1, D2; DEC-11 |
| Q6 | Estudante sem total do curso | `activity_status = 2` | DEC-40; DEC-24 |
| Q7 | `forum_criterion = 1`; dois fóruns elegíveis vencidos; estudante postou em um só | `forum_status = 0` | DEC-40 D3 |
| Q8 | Mesmo cenário, estudante postou nos dois; um terceiro fórum ainda não venceu | `forum_status = 1` | DEC-40 D3; DEC-39 D2 |
| Q9 | `forum_criterion = 0` | `forum_status = 1` | DEC-40 D3 |
| Q10 | `forum_criterion = 1` e nenhum fórum elegível vencido (ou fórum indisponível ao estudante) | `forum_status = 2` | DEC-40 D3; DEC-39 D1 |
| Q11 | Seções com 1/2 e 3/8 participações; `frequency_criterion = 40` | Global 4/10 = 40.000 ⇒ `frequency_status = 1` (a média das seções, 43.75, não é usada) | DEC-40 D4 |
| Q12 | `Σ total_activities = 0` | `frequency_status = 2` | DEC-40 D4 |
| Q13 | Curso sem critérios; depois falha de processamento | Nenhuma `criteria_result`; falha = `error`, sem linha | tratamentos da revisão |

| Q14 | Data de referência alcançada num curso com vários estudantes; depois, falha na gravação | Um único pedido de critérios do curso; uma `analysis` `criteria_check` com uma `criteria_result` por estudante elegível e um contexto; falha de leitura, avaliação ou gravação não deixa linha, análise nem contexto, e a nova tentativa grava tudo | DEC-55 D2–D4; DEC-40; PHPUnit executado (T141, `course_criteria_handler_test`: um pedido por curso, uma análise, N linhas e um contexto; falha de leitura, avaliação e persistência sem linha, análise nem contexto; a repetição grava com o contexto da nova tentativa); não validado no site real |

*(Q5–Q13 incluídos em 25/10/2026, depois de DEC-40; Q14 em 27/10/2026, depois de DEC-55.)*
