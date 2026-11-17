# Contract — Evento do assistente e enfileiramento (spec-006)

## Produtor
Observer de evento Moodle → `enqueue(course_id, event_type, payload)`.
- Não executa LLM [REQ §Regras].
- Eventos Moodle observados: NEEDS CLARIFICATION (Q-006-01). *(04/11/2026, resolvido: texto anterior: "Eventos Moodle observados: NEEDS CLARIFICATION (Q-006-01).". Os 15 observers de `assistente/db/events.php` estão na seção "Eventos Moodle observados (Q-006-01)" abaixo.)*

## Registro
| Campo | Conteúdo | Estado |
|---|---|---|
| `event_type` | tipo lógico | *(04/11/2026, resolvido: texto anterior: "valores NEEDS CLARIFICATION (Q-006-02)".)* 17 valores de `assistente/classes/domain/event/event_type.php`, listados na seção "Valores de `event_type` (Q-006-02)" abaixo |
| `payload` | JSON/text | *(04/11/2026, resolvido: texto anterior: "esquema NEEDS CLARIFICATION (Q-006-03)".)* Objeto JSON com os campos de `event_type::fields()` (todos obrigatórios, nenhum outro aceito, validado por `event_type::normalize_payload()`), mais a chave `identity` que o repositório acrescenta; por tipo na seção "Payload por tipo (Q-006-03)" abaixo |
| `status` | `pending` na criação [ESCOLHA, Q-006-06] | — |

## Garantias
- Questionário publicado: exatamente um evento por questionário; publicação = o questionário passa a ficar visível aos estudantes pela primeira vez, seja criado já visível, seja tornado visível depois; ocultar/mostrar ou editar depois não gera outro (DEC-31). Eventos candidatos: `course_module_created`/`course_module_updated` (P-01). *(Alterado em 25/10/2026; texto original: "Questionário publicado: exatamente um evento (Q-006-04).")*
- Postagem ou interação no fórum: um evento por ocorrência (spec-006 §Regras; DEC-03; Q-006-05 resolvida). *(Alterado em 25/10/2026; texto original: "Atualização relevante de fórum: um evento por atualização (Q-006-05).")*

## Consumidor
spec-007 (processamento em lote).

## Eventos Moodle observados (Q-006-01)
*(04/11/2026, resolvido. Fonte: `assistente/db/events.php`, 15 entradas, todas com `'internal' => false`, isto é, executam depois do commit. Não há observer para matrícula e papel (DEC-47). O observer só traduz o evento Moodle em chamada ao serviço de evento; não executa LLM.)*

| Evento Moodle | Método do observer (`classes/observer/`) | Tipo(s) lógico(s) enfileirado(s) | Serviço |
|---|---|---|---|
| `\core\event\course_module_created` | `course_module_observer::course_module_created` | `quiz_published` (módulo `quiz`, se visível); `material_changed` (módulos `resource`, `page`, `url`) | `quiz_publication_service::handle_module_event`; `material_event_service::handle_module_event` |
| `\core\event\course_module_updated` | `course_module_observer::course_module_updated` | os mesmos do anterior | os mesmos |
| `\mod_quiz\event\attempt_submitted` | `quiz_observer::attempt_submitted` | `quiz_attempt_submitted` | `quiz_attempt_event_service::attempt_submitted` |
| `\mod_quiz\event\question_manually_graded` | `quiz_observer::question_manually_graded` | `quiz_attempt_manually_graded` | `quiz_attempt_event_service::question_manually_graded` |
| `\mod_quiz\event\attempt_regraded` | `quiz_observer::attempt_regraded` | `quiz_attempt_regraded` | `quiz_attempt_event_service::attempt_regraded` |
| `\core\event\user_graded` | `grade_observer::user_graded` | `grade_changed`; e `participation_changed` (`source` = `grade`) se o item for o item principal de um módulo de atividade | `grade_event_service::grade_changed`; `participation_event_service::grade_item_changed` |
| `\core\event\grade_deleted` | `grade_observer::grade_deleted` | os mesmos do anterior | os mesmos |
| `\mod_forum\event\discussion_created` | `forum_observer::discussion_created` | `forum_post_created` (primeira mensagem da discussão); `participation_changed` (`source` = `post`) | `forum_event_service::discussion_created`; `participation_event_service::participation_changed` |
| `\mod_forum\event\post_created` | `forum_observer::post_created` | `forum_post_created`; `participation_changed` (`post`) | `forum_event_service::post_created`; `participation_event_service::participation_changed` |
| `\mod_forum\event\post_updated` | `forum_observer::post_updated` | `forum_post_updated` | `forum_event_service::post_updated` |
| `\mod_forum\event\post_deleted` | `forum_observer::post_deleted` | `forum_post_deleted`; `participation_changed` (`post`) | `forum_event_service::post_deleted`; `participation_event_service::participation_changed` |
| `\mod_forum\event\discussion_deleted` | `forum_observer::discussion_deleted` | `forum_discussion_deleted` | `forum_event_service::discussion_deleted` |
| `\mod_assign\event\assessable_submitted` | `participation_observer::assessable_submitted` | `participation_changed` (`source` = `submission`) | `participation_event_service::participation_changed` |
| `\core\event\course_module_completion_updated` | `participation_observer::completion_updated` | `participation_changed` (`source` = `completion`) | `participation_event_service::participation_changed` |
| `\core\event\course_deleted` | `course_observer::course_deleted` | nenhum evento da fila: apaga os dados do plugin do curso | `course_data_eraser::erase` |

Tipos sem observer (nenhum Moodle event em `db/events.php`): `quiz_closed` (tarefa `task/detect_quiz_close`, via `quiz_close_detector`), `reference_date_reached` (tarefa `task/detect_reference_dates`, via `reference_date_detector`), `forum_rebuild_requested` (`initial_sync_service`, via `forum_event_service`), `synthesis_requested` (`synthesis_trigger`), `criteria_check_requested` e `course_criteria_check_requested` (`criteria_request_service`). Tarefas registradas em `assistente/db/tasks.php`.

## Valores de `event_type` (Q-006-02)
*(04/11/2026, resolvido. Fonte: `assistente/classes/domain/event/event_type.php`, enum `event_type: string`; estratégia em `event_type::strategy()`, campos de identidade em `event_type::identity_fields()`; handler em `application/queue/handler_registry.php`, `DEFAULT_HANDLERS`. Estratégias (`identity_strategy`): ONCE_PER_OBJECT = o mesmo objeto nunca gera um segundo evento do tipo; OCCURRENCE = objeto mais o segundo do evento Moodle (duas ocorrências legítimas no mesmo segundo viram um evento); COALESCE_PENDING = pedido feito com outro pendente funde-se nele, e um pedido depois da execução cria novo evento; texto em `event_type::limitation()`.)*

| Valor persistido (`event_type`) | Case | Estratégia | Campos da identidade | Handler |
|---|---|---|---|---|
| `quiz_published` | `QUIZ_PUBLISHED` | ONCE_PER_OBJECT | `cmid` | `quiz\initial_analysis_handler` |
| `material_changed` | `MATERIAL_CHANGED` | OCCURRENCE | `cmid`, `moodle_event`, `occurred_at` | `material\material_event_handler` |
| `quiz_attempt_submitted` | `QUIZ_ATTEMPT_SUBMITTED` | ONCE_PER_OBJECT | `attemptid` | `quiz\results_handler` |
| `quiz_attempt_manually_graded` | `QUIZ_ATTEMPT_MANUALLY_GRADED` | OCCURRENCE | `attemptid`, `slot`, `occurred_at` | `quiz\results_handler` |
| `quiz_attempt_regraded` | `QUIZ_ATTEMPT_REGRADED` | OCCURRENCE | `attemptid`, `occurred_at` | `quiz\results_handler` |
| `quiz_closed` | `QUIZ_CLOSED` | ONCE_PER_OBJECT | `cmid`, `timeclose` | `quiz\results_handler` |
| `grade_changed` | `GRADE_CHANGED` | OCCURRENCE | `userid`, `itemid`, `occurred_at` | `grade\performance_handler` |
| `forum_post_created` | `FORUM_POST_CREATED` | ONCE_PER_OBJECT | `postid` | `forum\forum_analysis_handler` |
| `forum_post_updated` | `FORUM_POST_UPDATED` | OCCURRENCE | `postid`, `occurred_at` | `forum\forum_analysis_handler` |
| `forum_post_deleted` | `FORUM_POST_DELETED` | ONCE_PER_OBJECT | `postid` | `forum\forum_analysis_handler` |
| `forum_discussion_deleted` | `FORUM_DISCUSSION_DELETED` | ONCE_PER_OBJECT | `discussionid` | `forum\forum_analysis_handler` |
| `forum_rebuild_requested` | `FORUM_REBUILD_REQUESTED` | COALESCE_PENDING | `cmid` | `forum\forum_analysis_handler` |
| `participation_changed` | `PARTICIPATION_CHANGED` | OCCURRENCE | `cmid`, `userid`, `source`, `occurred_at` | `frequency\frequency_handler` |
| `reference_date_reached` | `REFERENCE_DATE_REACHED` | ONCE_PER_OBJECT | `cmid`, `reference_date` | `frequency\frequency_handler` |
| `synthesis_requested` | `SYNTHESIS_REQUESTED` | COALESCE_PENDING | `section_id` | `synthesis\synthesis_handler` |
| `criteria_check_requested` | `CRITERIA_CHECK_REQUESTED` | COALESCE_PENDING | `userid` | `criteria\criteria_handler` |
| `course_criteria_check_requested` | `COURSE_CRITERIA_CHECK_REQUESTED` | COALESCE_PENDING | `course_id` | `criteria\criteria_handler` |

A identidade é `sha1` de `tipo|campo=valor|...` (`occurrence_identity::of`).

## Payload por tipo (Q-006-03)
*(04/11/2026, resolvido. Esquema: `event_type::fields()`; `normalize_payload()` exige todos os campos, rejeita campo desconhecido ou ausente (`InvalidArgumentException`), aceita `int` (inclusive string numérica, convertida) e, para `string`, texto não vazio. O curso é a coluna `course_id` do evento, não entra no payload. Quem grava: o serviço indicado; o valor de `occurred_at` é o `timecreated` do evento Moodle, quando o observer o fornece.)*

| Tipo | Campos (tipo) | Quem grava | Origem dos valores |
|---|---|---|---|
| `quiz_published` | `cmid` (int) | `quiz_publication_service::handle_module_event` | `objectid` do `course_module_*` |
| `material_changed` | `cmid` (int), `moodle_event` (string), `occurred_at` (int) | `material_event_service::handle_module_event` | `objectid`, `eventname`, `timecreated` do evento Moodle |
| `quiz_attempt_submitted` | `cmid`, `attemptid`, `userid` (int) | `quiz_attempt_event_service::attempt_submitted` | `contextinstanceid`, `objectid`, `relateduserid` |
| `quiz_attempt_manually_graded` | `cmid`, `attemptid`, `slot`, `occurred_at` (int) | `quiz_attempt_event_service::question_manually_graded` | `contextinstanceid`, `other['attemptid']`, `other['slot']` |
| `quiz_attempt_regraded` | `cmid`, `attemptid`, `occurred_at` (int) | `quiz_attempt_event_service::attempt_regraded` | `contextinstanceid`, tentativa, momento |
| `quiz_closed` | `cmid`, `timeclose` (int) | `quiz_close_detector` | `cmid` e `time_close` do questionário |
| `grade_changed` | `userid`, `itemid`, `occurred_at` (int) | `grade_event_service::grade_changed` e `grade_changed_many` | `relateduserid`, `other['itemid']`, `timecreated` |
| `forum_post_created` | `cmid`, `discussionid`, `postid` (int) | `forum_event_service::post_created` (também via `discussion_created`, com `postid` = primeira mensagem) | `contextinstanceid`, `other['discussionid']`, `objectid` |
| `forum_post_updated` | `cmid`, `discussionid`, `postid`, `occurred_at` (int) | `forum_event_service::post_updated` | idem, mais momento |
| `forum_post_deleted` | `cmid`, `discussionid`, `postid` (int) | `forum_event_service::post_deleted` | idem |
| `forum_discussion_deleted` | `cmid`, `discussionid` (int) | `forum_event_service::discussion_deleted` | `contextinstanceid`, `objectid` |
| `forum_rebuild_requested` | `cmid` (int) | `forum_event_service` (método de pedido de reconstrução), chamado pela sincronização inicial | `cmid` do fórum |
| `participation_changed` | `cmid`, `userid` (int), `source` (string: `submission`, `completion`, `grade` ou `post`), `occurred_at` (int) | `participation_event_service::participation_changed`, `grade_item_changed` | observers de participação, fórum e nota |
| `reference_date_reached` | `cmid`, `reference_date` (int) | `reference_date_detector` | menor `cmid` com a data de referência, uma data por evento |
| `synthesis_requested` | `section_id` (int) | `synthesis_trigger::request_section` | seção do curso |
| `criteria_check_requested` | `userid` (int) | `criteria_request_service::request` | estudante |
| `course_criteria_check_requested` | `course_id` (int) | `criteria_request_service::request_course` | curso |

O que os handlers leem (`json_decode` de `event->payload`):

| Handler | Campos lidos | Verificação |
|---|---|---|
| `initial_analysis_handler`, `results_handler`, `material_event_handler` | `cmid` | `cmid` inteiro positivo, senão falha técnica |
| `forum_analysis_handler` | `cmid`, `discussionid`; `postid` nos eventos incrementais | `discussionid` não exigido em `forum_rebuild_requested` |
| `performance_handler` | `userid`, `itemid` | `userid` = 0 (`erased_subject::USERID`) encerra com sucesso sem refazer análise |
| `frequency_handler` | `cmid`; `userid` (exceto em `reference_date_reached`) | idem, `userid` = 0 encerra com sucesso |
| `criteria_handler` | `userid` (por estudante); `course_id` (por curso) | `course_id` do payload deve ser igual a `course_id` do evento |
| `synthesis_handler` | `section_id` | |

### Observações
1. O repositório grava no JSON uma chave extra, `identity` (`occurrence_identity::PAYLOAD_KEY`, em `event_repository::create_event`), que não está em `event_type::fields()`. O esquema do enum descreve o que o serviço informa, não o JSON persistido completo.
2. `course_criteria_check_requested` repete o curso no payload (`course_id`), embora o docblock do enum diga que o curso não é repetido no payload; `criteria_handler` confere `payload['course_id'] === course_id` do evento.
3. Os handlers leem só parte dos campos do esquema: `results_handler` lê somente `cmid` (ignora `attemptid`, `userid`, `slot`, `timeclose`); `material_event_handler` ignora `moodle_event` e `occurred_at`. Esses campos servem à identidade e à rastreabilidade, e não alteram o comportamento do handler.
4. Um evento Moodle pode gerar mais de um evento da fila: `user_graded`/`grade_deleted` (`grade_changed` mais `participation_changed` no item principal), `post_created`, `discussion_created` e `post_deleted` do fórum (evento de fórum mais `participation_changed`).
5. No fórum, a edição e a exclusão de discussão não geram `participation_changed` (`forum_observer`).
6. A deduplicação por OCCURRENCE tem precisão de segundo (`event_type::limitation()`): duas ocorrências legítimas no mesmo segundo viram um evento.
7. Pontos não confirmados no código desta consulta: o comportamento dos eventos no Moodle em execução (pendente da VT T132, conforme `022-integra-o-events-hooks/tasks.md`); o formato exato de `moodle_event` (o observer passa `$event->eventname`, valor definido pelo Moodle, não pelo plugin). Nenhuma divergência foi encontrada entre o catálogo da 022 (15 linhas) e as 15 entradas de `db/events.php`. *(04/11/2026, revisão dos planos: T132 [x]; CL-02 FECHADA em 02/11/2026.)*
