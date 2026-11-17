# Data Model — spec-018

Modelos de leitura (não persistidos) por consulta:

| Consulta | Fonte (tabela / spec) | Organização |
|---|---|---|
| Notas | `performance` (012) | por estudante *(26/10/2026: + total do curso do snapshot da `analysis` `grade_performance` atual; filtro por grupo)* |
| Métricas de questionários | `question_result`, `questionnaire_metric` (011) | por questionário/questão *(26/10/2026: + `questionnaire_analysis`/`questionnaire_question` (010); "sem materiais" pelo snapshot sem materiais)* |
| Frequência | `frequency`, `frequency_activity` (014) | por estudante/seção *(26/10/2026: + acesso ao curso do snapshot; filtro por grupo)* |
| Fóruns | análise de fórum + `forum_difficulty` (013) | por fórum/discussão *(26/10/2026: `analysis` `forum_analysis` atual)* |
| Alunos abaixo dos critérios | `criteria_result` (015) | por estudante *(26/10/2026: filtro por grupo)* |
| Resumos de materiais | `material_version` corrente (008) | por material |
| Síntese | `pedagogical_synthesis` (016) | por curso (Q-016-02) *(26/10/2026: por curso e seção, versão vigente)* |
| Histórico | cadeia de spec-017 | por entidade (Q-018-04) *(26/10/2026: resolvida)* |

*(Incluído em 26/10/2026, DEC-43:)* grupos e membros lidos da API de grupos do Moodle na consulta; nenhum campo de turma no plugin.
