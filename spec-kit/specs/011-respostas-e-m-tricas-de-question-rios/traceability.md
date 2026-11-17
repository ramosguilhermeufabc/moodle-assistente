# Rastreabilidade — spec-011

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-052 | Calcular métricas por questão: average_correctness, correct_count, incorrect_count, partial_result | spec-011; DEC-36; DEC-64 *(03/11/2026)* | Confirmado; definições por DEC-36 D1 (25/10/2026) *(03/11/2026: implementado e testado em T070 e T072; `partial_result` de `match` e `ddwtos` em T075; CL-33 aberta)* |
| AT-053 | Calcular mean, population_standard_deviation, minimum e maximum ao fechar questionário | spec-011; DEC-36 | Confirmado; unidade e fechamento por DEC-36 D3/D4 (25/10/2026) |
| AT-054 | Calcular partial_result = correct_items_answered / total_correct_items | spec-011; decisão confirmada em 2026-10-21; DEC-36; DEC-64 *(03/11/2026)* | Fórmula confirmada; regra de caso-limite confirmada; escala e truncamento por DEC-36 D2 (25/10/2026) *(03/11/2026: múltipla escolha com várias respostas pela DEC-52, testada; `match` e `ddwtos` pela DEC-64, implementados e testados em T075; `randomsamatch` e tipos de resposta única `NULL`; `multianswer` incluído pelo complemento de 03/11/2026 da DEC-64 *(texto anterior: "`randomsamatch`, `multianswer` e tipos de resposta única `NULL`")*; CL-33 aberta)* |

*(25/10/2026: colunas "Fonte" e "Estado" atualizadas depois da DEC-36; textos originais: fonte "spec-011" (AT-052, 053) e "spec-011; decisão confirmada em 2026-10-21" (AT-054); estados "Confirmado" e "Fórmula confirmada; regra de caso-limite confirmada".)*

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-01 | Métricas de questionário |
| DEC-24 | Relatórios (zero, `no_data`, `error`; aplicado às métricas pela spec-011) *(incluída em 25/10/2026)* |
| DEC-35 | Análise inicial de questionário (D1, linhas analisadas) *(incluída em 25/10/2026)* |
| DEC-36 | Métricas de questionário *(incluída em 25/10/2026)* |
| DEC-52 | Métricas de questionário (`partial_result` de múltipla escolha com várias respostas, Q-011-07) *(incluída em 03/11/2026 nesta tabela; a decisão é de 26/10/2026)* |
| DEC-64 | Métricas de questionário (`partial_result` de `match` e `ddwtos`; complementa DEC-36 D1 e DEC-52 sem alterá-las) *(incluída em 03/11/2026; complemento do mesmo dia: `multianswer`)* |

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q14; Q15–Q21 incluídos em 03/11/2026; Q22–Q25 no complemento de `multianswer` do mesmo dia). Validação técnica em `closure-register.md` CL-33. Nada foi executado. *(03/11/2026: a frase anterior descreve 25/10/2026. Cobertura executada: T070 (`metrics_calculator_test`), T072 (`results_handler_test`, `results_handler_parts_test`), T073 e T074 (observers e `detect_quiz_close`) e T075 (`cl33_attempts_test`, `cl33_types_test` e os scripts de `spec-kit/validation/cl33-ui/`). CL-33 e T075 continuam abertos; ver a avaliação de fechamento em `closure-register.md` §4.4.1.)*
