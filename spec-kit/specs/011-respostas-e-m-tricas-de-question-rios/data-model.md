# Data Model — spec-011

## `local_assistente_question_result`
`analysis_id`, `questionnaire_question_id` (→ spec-010), `question_id`, `question_version`, `average_correctness` number(10,3), `correct_count`, `incorrect_count`, `partial_result` number(10,3) nullable. Único `analysis_id,question_id,question_version`.

*(25/10/2026, DEC-36 D5)* `questionnaire_question_id` NOT NULL: aponta para a linha analisada da mesma entrada do banco de questões; `question_id`/`question_version` guardam a versão respondida, que pode diferir da analisada.

## `local_assistente_questionnaire_metric`
`analysis_id` (foreign-unique), `mean`, `population_standard_deviation`, `minimum`, `maximum`, todos number(10,3).

## `local_assistente_analysis` *(incluído em 25/10/2026)*
`analysis_type = quiz_results`; uma por execução, imutável; a atual é a última bem-sucedida.

## Regras
- `partial_result = correct_items_answered / total_correct_items` [REQ] (escala e arredondamento: Q-011-02). *(25/10/2026: Q-011-02 resolvida — percentual 0–100 truncado a três casas, DEC-36 D2; média entre as respostas; `NULL` sem múltiplos itens corretos, D1.)*
- Desvio-padrão populacional [REQ].
- No fechamento, todas as respostas [REQ] (Q-011-03/05). *(25/10/2026: resolvidas por DEC-36 D3/D4 — tentativas concluídas; fechamento = `timeclose` passado.)*
- *(25/10/2026)* Sem campo novo: `no_data` = ausência da linha num processamento bem-sucedido; `error` = processamento em `failure` (P-06).
