# Data Model — spec-017

## Cadeia (tabelas de spec-002)
```text
Moodle source (course_modules / question / forum_discussions / user …)
   ↓ observer
event (course_id, event_type, payload, status, created_at, processed_at)
   ↓ 1:1
processing (status, created_at, finished_at)
   ├─1:N(≤3)─ processing_attempt (attempt_number, started_at, finished_at, status)
   ├─1:1─ processing_context (context_json — versões de material usadas)
   └─1:N─ analysis (analysis_type, cmid, summary, created_at)
              └─ resultados (questionnaire_*, question_result, forum_difficulty,
                             performance, frequency*, criteria_result, pedagogical_synthesis)
                    ↓ (elo não relacional — Q-017-04)
report (course_id, current_version) ─1:N─ report_version (version, snapshot_json, created_at)   # DEC-29 (original: report_version com is_current)
```
*(26/10/2026: o elo resultado → relatório é o registro dos `analysis_id` e das versões de síntese no `snapshot_json`; Q-017-04 resolvida no escopo da 017.)*

## Regras
- Timestamps disponíveis em cada nível (`created_at`, `started_at`, `finished_at`, `processed_at`) [REQ].
- Versões: `material_version.version`, `report_version.version`, `question_version` [REQ].
- Preservação: Q-017-01. *(26/10/2026: resolvida — `analysis` imutável por execução; atual = última bem-sucedida por escopo.)*
- *(26/10/2026, DEC-42 D3)* Referências a registros Moodle removidos permanecem; "fonte removida" é derivada na leitura; sem campo novo. Premissa de chaves `foreign` não impostas pelo banco a confirmar (CL-37).
- *(26/10/2026, DEC-42 D1)* Linhas por estudante removíveis pela Privacy API: `performance`, `frequency`, `frequency_activity`, `criteria_result`; entradas identificáveis do estudante nos `context_json`.
- *(26/10/2026, DEC-42 D2)* Exclusão do curso remove todas as linhas do plugin com aquele `course_id`, direta ou indiretamente.
- *(26/10/2026, DEC-42 D4)* Sem expurgo automático.
- *(27/10/2026, DEC-57)* Na exclusão de um estudante, `event` e `analysis` são preservados com os mesmos IDs e relações; nos eventos em estado final do estudante o `userid` do payload vira `0` e a `identity` calculada com o `userid` vira 40 zeros. Análise individual não é apagada por ficar sem resultados. Os eventos não terminais também são neutralizados e a fila os conclui como sujeito apagado (DEC-57, lacuna 1; `userid` 0 nos handlers, locks `privacy_user_<id>` e de evento). IDs de registros do Moodle (`attemptid`, `postid`, `discussionid`), cópias de texto em `processing_context` e mensagens de falha seguem como lacunas abertas.
