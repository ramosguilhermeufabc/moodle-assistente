# Data Model — spec-012

## `local_assistente_performance`
`analysis_id`, `student_id` (→ `user`), `activity_id` (→ `course_modules`, ou seja, `cmid`), `grade` number(10,3) NOT NULL. Único `analysis_id,student_id,activity_id`.

Regras: `grade` = nota final do gradebook, sem pesos próprios [REQ §Regras]. A média do estudante não é persistida no esquema (Q-012-03).

*(Incluído em 25/10/2026, DEC-37 e tratamentos da revisão:)*
- `grade` em percentual do intervalo do item, `(nota − min) / (max − min) × 100`, truncado a três casas (D2).
- Sem nota atribuída ou intervalo nulo: sem linha (`no_data`).
- `analysis.analysis_type = grade_performance`; uma por evento, escopo curso × estudante, imutável.
- Média do estudante = total final do curso do gradebook, em percentual, no snapshot de contexto da análise (D3). Nenhum campo novo.

*(27/10/2026, DEC-53)* Atividade com mais de um item de nota: uma linha por atividade, com o item principal (`itemnumber = 0`); as notas dos itens não são combinadas e o item não é escolhido por ter nota; item 0 sem nota ou com intervalo nulo = sem linha (`no_data`), ainda que outro item tenha nota.
