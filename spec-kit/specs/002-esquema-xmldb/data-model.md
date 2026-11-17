# Data Model — spec-002

Fonte: `spec.md` §Inventário (as definições exatas de campo estão lá e não são duplicadas aqui). Prefixo físico `local_assistente_` [REQ §Regras].

## Tabelas, chaves e unicidade

| Tabela | Propósito (inventário) | FKs | Únicos (além de PK `id`) | Spec consumidora |
|---|---|---|---|---|
| `config` | Configuração por curso | `course_id`→`course` (foreign-unique) | `course_id` | 005 |
| `criteria` | Critérios por curso | `config_id`→`config` (foreign-unique) | `config_id` | 005, 015 |
| `event` | Eventos do assistente | `course_id`→`course` | — (índices `status`, `course_id,status`, `created_at`) | 006 |
| `processing` | Processamento lógico de evento | `event_id`→`event` (foreign-unique) | `event_id` | 007 |
| `processing_attempt` | Tentativas | `processing_id`→`processing` | `processing_id,attempt_number` | 007 |
| `processing_context` | Snapshot do contexto | `processing_id`→`processing` (foreign-unique) | `processing_id` | 008, 017 |
| `material` | Materiais usados como contexto | `course_id`→`course`; `section_id`→`course_sections`; `cmid`→`course_modules` | `course_id,cmid` | 008 |
| `material_version` | Versões e resumos | `material_id`→`material` | `material_id,version`; versão corrente em `material.current_version` *(Alterado em 25/10/2026 por DEC-29; texto original: "`material_id,version` (índice não único `material_id,is_current`)")* | 008 |
| `analysis` | Análises produzidas | `course_id`→`course`; `processing_id`→`processing`; `cmid`→`course_modules` (nullable) | — (índice `analysis_type`) | 010–016 |
| `questionnaire_analysis` | Análise inicial de questionário | `analysis_id`→`analysis` (foreign-unique) | `analysis_id` | 010 |
| `questionnaire_question` | Questões consideradas | `questionnaire_analysis_id`→…; `question_id`→`question` | `questionnaire_analysis_id,question_id,question_version` | 010 |
| `question_result` | Resultado por questão | `analysis_id`; `questionnaire_question_id`; `question_id`→`question` | `analysis_id,question_id,question_version` | 011 |
| `questionnaire_metric` | Métricas agregadas | `analysis_id` (foreign-unique) | `analysis_id` | 011 |
| `forum_difficulty` | Dificuldades por discussão | `analysis_id`; `discussion_id`→`forum_discussions` | — | 013 |
| `performance` | Desempenho por estudante/atividade | `analysis_id`; `student_id`→`user`; `activity_id`→`course_modules` | `analysis_id,student_id,activity_id` | 012 |
| `frequency` | Frequência por estudante/seção | `analysis_id`; `student_id`→`user`; `section_id`→`course_sections` | `analysis_id,student_id,section_id` | 014 |
| `frequency_activity` | Participação por atividade | `frequency_id`; `activity_id`→`course_modules` | `frequency_id,activity_id` | 014 |
| `criteria_result` | Resultado de critérios por estudante | `analysis_id`; `student_id`→`user` | `analysis_id,student_id` | 015 |
| `pedagogical_synthesis` | Síntese pedagógica | `analysis_id`; `course_id`→`course`; `section_id`→`course_sections` *(26/10/2026, DEC-29)* | `course_id,section_id,version` *(26/10/2026, DEC-29; texto original: "—")* | 016 |
| `report` | Relatório por curso | `course_id`→`course` (foreign-unique) | `course_id` (índice `next_update_at`) | 019 |
| `report_version` | Versões do relatório | `report_id`→`report` | `report_id,version`; versão corrente em `report.current_version` *(Alterado em 25/10/2026 por DEC-29; texto original: "`report_id,version` (índice não único `report_id,is_current`)")* | 019 |

Total: 21 tabelas [REQ §Objetivo].

## Relações principais

```text
course ─1:1─ config ─1:1─ criteria
course ─1:N─ event ─1:1─ processing ─1:N(≤3, spec-007)─ processing_attempt
                         processing ─1:1─ processing_context
                         processing ─1:N─ analysis ─┬─1:1─ questionnaire_analysis ─1:N─ questionnaire_question
                                                    ├─1:N─ question_result (→ questionnaire_question)
                                                    ├─1:1─ questionnaire_metric
                                                    ├─1:N─ forum_difficulty
                                                    ├─1:N─ performance
                                                    ├─1:N─ frequency ─1:N─ frequency_activity
                                                    ├─1:N─ criteria_result
                                                    └─1:N─ pedagogical_synthesis
course ─1:N─ material ─1:N─ material_version
course ─1:1─ report ─1:N─ report_version
```

## Validações e estados
- Domínios de `char` e semântica de `int(1)`: NEEDS CLARIFICATION (Q-002-03, Q-002-04) (04/11/2026: resolvido: `summary_status` por DEC-50 e `availability` por DEC-51; domínios implementados em `assistente/classes/infrastructure/persistence/schema_domains.php`). *(26/10/2026: definidos abaixo, exceto `summary_status` e `availability`.)* *(26/10/2026: `summary_status` por DEC-50 e `availability` por DEC-51, este como `char(20)`.)*
- Transições de estado: definidas apenas para `event` (spec-006). *(26/10/2026: também `processing` e `processing_attempt`, DEC-32 D4.)*

*(Incluído em 26/10/2026.)*

| Campo | Valores | Fonte |
|---|---|---|
| `event.status`, `processing.status` | `pending`, `processing`, `processed`, `failure` | spec-006; DEC-32 |
| `processing_attempt.status` | `processing`, `processed`, `failure` | DEC-32 D4 |
| `analysis.analysis_type` | `quiz_initial`, `quiz_results`, `grade_performance`, `forum_analysis`, `frequency`, `criteria_check`, `section_synthesis` | planos 010–016 |
| `material.material_type` | `pdf`, `text`, `youtube` | DEC-33 D2 |
| `material_version.summary_status` | `pending`, `completed`, `failed` | DEC-50 |
| `config.enabled` | 0, 1 | DEC-45 D3 |
| `frequency_activity.participated` | 0, 1 | DEC-39 D1 |
| `criteria.forum_criterion` | 0 = desativado; 1 = participação obrigatória | DEC-40 D3 |
| `criteria_result.*_status` | 1 = atende; 0 = abaixo; 2 = `no_data` | DEC-40 |
| `material.availability`, `material_version.availability` | `available`, `unavailable`, `pending` (campos `char(20)`, DEC-51) | DEC-51 |
| Campos de data `int(10)` | timestamp Unix em segundos | convenção do Moodle |

O XMLDB não restringe valores; a validação é da camada de persistência (spec-003).
