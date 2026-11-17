# Data Model — spec-015

## `local_assistente_criteria_result`
`analysis_id`, `student_id` (→ `user`), `activity_status`, `forum_status`, `frequency_status` (int(1) NOT NULL). Único `analysis_id,student_id`.

Entradas: `criteria` (spec-005); `performance` (012); resultado de fórum (013, Q-015-03); `frequency` (014).
Semântica dos status: Q-015-05.

*(Incluído em 25/10/2026, DEC-40 e tratamentos da revisão:)*
- Entradas efetivas: `criteria` (percentuais 0–100, de mínimo); total do curso no snapshot da `analysis` `grade_performance` atual (012); postagens do Moodle nos fóruns elegíveis vencidos (DEC-39); linhas `frequency` da `analysis` `frequency` atual (014). A análise de fórum da 013 não é entrada (Q-015-03 resolvida).
- `*_status`: `1 = atende`, `0 = abaixo`, `2 = no_data` (Q-015-05 resolvida). Sem campo novo.
- `analysis.analysis_type = criteria_check`; imutável; atual = última bem-sucedida.
- Sem `criteria` no curso: nenhuma linha.
