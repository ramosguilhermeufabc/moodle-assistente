# Quickstart (validação) — spec-012

> Nada foi executado. *(25/10/2026: Q11 é validação técnica no Moodle 5.2, CL-34.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Curso com pesos configurados no gradebook | `grade` persistida = nota final do Moodle | §Regras *(25/10/2026: convertida por DEC-37 D2)* |
| Q2 | Inspecionar o código | Nenhuma ponderação própria | §Regras |
| Q3 | Média do estudante | Igual à derivada do Moodle pela configuração | §Regras (Q-012-03) *(25/10/2026: total final do curso no snapshot, DEC-37 D3)* |
| Q4 | Item 0–20, nota 13,333 | `grade` = 66.665; exibida "66,66%" | DEC-37 D2 |
| Q5 | Item 5–10 (mínimo diferente de zero), nota 7 | `grade` = 40.000 | DEC-37 D2 |
| Q6 | Atividade com escala de 4 níveis, nível 3 (min 1, max 4) | `grade` = 66.666 | DEC-37 D2 |
| Q7 | Atividade sem nota; outra com nota 0; falha técnica | sem linha (`no_data`); linha 0.000 (`valid`); `error`, sem zero | DEC-24; P-06 |
| Q8 | Professor altera uma nota, depois remove | Cada evento gera nova `analysis` do estudante; a anterior é preservada; após a remoção, a atividade fica sem linha | DEC-37 D1; §7 |
| Q9 | Item de categoria, item manual e nota só textual | Não entram como atividade | tratamento de Q-012-04 |
| Q10 | Instrumentar a porta LLM | Nenhuma chamada | spec-009 §Regras |
| Q11 | Moodle 5.2: substituição de nota, escalas, papéis avaliados, alteração de pesos e reavaliação do total | Eventos emitidos e total lido após a reavaliação | CL-34; R-012-01 |

*(Q4–Q11 incluídos em 25/10/2026, depois de DEC-37.)*
