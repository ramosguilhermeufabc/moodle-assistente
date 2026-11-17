# Data Model — spec-019

## `local_assistente_report`
`course_id` (foreign-unique), `created_at`, `next_update_at` (indexado, anulável), `final_activity_date` (anulável), `final_update_at` (anulável), `final_completed_at` (anulável), `current_version` (anulável → `report_version.id`). *(Alterado em 25/10/2026 por DEC-29; texto original: "`course_id` (foreign-unique), `created_at`, `next_update_at` (indexado), `final_activity_date`, `final_update_at`.")*

## `local_assistente_report_version`
`report_id`, `version`, `snapshot_json`, `created_at`. Único `report_id,version`. A versão corrente é `report.current_version`. *(Alterado em 25/10/2026 por DEC-29; texto original: "`report_id`, `version`, `snapshot_json`, `created_at`, `is_current`. Único `report_id,version`.")* *(26/10/2026: `snapshot_json` = manifesto de DEC-44 D1, sem cópia de valores.)*

## Ciclo
```text
[criado] --72 h desde o último sucesso (next_update_at)--> nova versão (anterior preservada)
   ... até final_activity_date (recalculada se a data mudar)
[final_activity_date + 1 dia = final_update_at] --> versão final (uma só, se coincidir com a periódica)
[sem data final aplicável] --> nenhuma atualização periódica agendada (datas nulas, DEC-29)
[falha] --> registrada; não conta como sucesso; não reinicia as 72 h
```

*(Ciclo alterado em 25/10/2026 conforme a spec-019 propagada por DEC-06, DEC-07, DEC-17 e DEC-18; primeira linha original: "[criado] --a cada 3 dias (next_update_at)--> nova versão (anterior preservada)".)*
*(26/10/2026, DEC-44:)*
```text
[final concluída (final_completed_at)] --última data elegível movida para o futuro--> reaberto:
   final_completed_at = nulo; final_activity_date/final_update_at recalculadas; ciclo de 72 h retomado
[final concluída, sem mudança de datas] --> nenhuma versão nova
```
Regra de leitura [ESCOLHA, a confirmar]: `final_update_at = final_activity_date + 1 dia` (derivado de §Regras). Âncora: Q-019-05. *(26/10/2026: confirmada e precisada por DEC-44 D2 — 00:00 do próximo dia-calendário no fuso do site; Q-019-05 resolvida.)*
