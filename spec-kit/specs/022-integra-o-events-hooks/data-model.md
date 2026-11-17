# Data Model — spec-022

Sem entidades próprias. Produz linhas em `local_assistente_event` (ver `../006-contrato-de-eventos-e-fila-l-gica/data-model.md`).

| Gatilho (spec de origem) | Evento Moodle 5.2 | `event_type` |
|---|---|---|
| Questionário publicado (006, 010) | NEEDS CLARIFICATION (04/11/2026: resolvido: `\core\event\course_module_created` e `\core\event\course_module_updated`, `assistente/db/events.php`) | Q-006-02 (04/11/2026: resolvido: `event_type` `quiz_published`, `assistente/classes/domain/event/event_type.php`) |
| Postagem ou interação no fórum (006, 013) *(alterado em 26/10/2026 conforme DEC-03; texto original: "Atualização relevante de fórum (006, 013)")* | NEEDS CLARIFICATION (04/11/2026: resolvido: `\mod_forum\event\discussion_created`, `post_created`, `post_updated`, `post_deleted` e `discussion_deleted`, `assistente/db/events.php`) | Q-006-02 (04/11/2026: resolvido: `event_type` `forum_post_created`, `forum_post_updated`, `forum_post_deleted`, `forum_discussion_deleted`) |
| Nova disponibilidade/alteração de material (008) | NEEDS CLARIFICATION (04/11/2026: resolvido: `\core\event\course_module_created` e `\core\event\course_module_updated`, `assistente/db/events.php`; teste `assistente/tests/observer/cl02_material_events_test.php`) | Q-006-02 (04/11/2026: resolvido: `event_type` `material_changed`) |
| Respostas/fechamento de questionário (011) | NEEDS CLARIFICATION (04/11/2026: resolvido: `\mod_quiz\event\attempt_submitted`, `question_manually_graded` e `attempt_regraded`, `assistente/db/events.php`; o fechamento não vem de evento Moodle, é detectado por `assistente/classes/task/detect_quiz_close.php`) | Q-006-02 (04/11/2026: resolvido: `event_type` `quiz_attempt_submitted`, `quiz_attempt_manually_graded`, `quiz_attempt_regraded` e `quiz_closed`) |
| Participação (014) | NEEDS CLARIFICATION (04/11/2026: resolvido: `\mod_assign\event\assessable_submitted` e `\core\event\course_module_completion_updated`, `assistente/db/events.php`; postagens e notas pelos observers de fórum e nota; a data de referência é detectada por `assistente/classes/task/detect_reference_dates.php`) | Q-006-02 (04/11/2026: resolvido: `event_type` `participation_changed` e `reference_date_reached`) |
| *(26/10/2026)* Nota atribuída, alterada ou removida (012) | candidato no catálogo do plano; CL-34 | Q-006-02 |
| *(26/10/2026)* Exclusão do curso (017) | candidato no catálogo do plano; CL-37 | — (remove os dados do curso, DEC-42 D2) |

*(26/10/2026: os eventos candidatos de cada gatilho estão no "Catálogo de gatilhos" de [plan.md](plan.md); os nomes concretos e os valores de `event_type` são definidos depois da verificação no Moodle 5.2. O fechamento do questionário não vem de evento: é detectado pela Scheduled Task, DEC-36 D4.)*
