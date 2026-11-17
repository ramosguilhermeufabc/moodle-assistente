# Contract — Snapshot de contexto (spec-008)

Consumidores: 010 (questionário), 013 (fórum), 016 (síntese), 017 (rastreabilidade).

| Operação | Entrada | Saída | Regras |
|---|---|---|---|
| `buildContext(processing, course, section)` | processing, seção alvo | snapshot persistido em `processing_context` | só versões correntes, elegíveis e da mesma seção; imutável depois de gravado [REQ] |
| `getContext(processing)` | processing | snapshot | leitura |
| `registerChange(cmid)` | material alterado | nova `material_version` + resumo assíncrono | versão anterior preservada [REQ] |

Formato do `context_json`: formato 1, documentado no docblock de `context_snapshot_builder` (T064; Q-008-06): escopo (`course_id`, `section_id`), `language`, `created_at`, `students_evaluated`, `materials` (um item por material registrado da seção, com `availability` = `coverage` `all`/`partial`/`none`, `available_students`, `total_students`; `eligible`; `reason`; `summary` só se elegível) e `used_versions`. Lista todos os materiais e versões usados [REQ §Regras; spec-017]. *(Atualizado em 31/10/2026; texto anterior: "NEEDS CLARIFICATION (Q-008-06)" (04/11/2026: resolvido: Q-008-06 resolvida em 31/10/2026; formato 1 implementado em `assistente/classes/application/context/context_snapshot_builder.php` (T064)).)*

## Disponibilidade (DEC-26, DEC-51; T058)

| Aspecto | Regra |
|---|---|
| Fonte da decisão de elegibilidade | O snapshot. Ele avalia, no momento da construção, a disponibilidade de cada material para os estudantes relevantes daquela análise (regra por usuário do Moodle) e grava o resultado. |
| Elegibilidade | Cobertura `all` = elegível (se também houver versão corrente concluída). `partial` e `none` = não elegível (`not_available_to_all`). Sem estudantes relevantes, nada é elegível (`no_students`). |
| Campos persistidos | O snapshot não lê nem altera `material.availability` nem `material_version.availability`. Mudar qualquer um deles não altera um snapshot já gravado. |
| `material.availability` | Indicador agregado da última avaliação conhecida do material: `available` (pelo menos um estudante do curso acessa; parcial inclusive), `unavailable` (nenhum acessa), `pending` (não determinado). Atualizado em `registerChange`/`material_service::process` a cada `material_changed`, sem criar versão. |
| `material_version.availability` | Valor observado quando a versão foi criada; imutável. |
