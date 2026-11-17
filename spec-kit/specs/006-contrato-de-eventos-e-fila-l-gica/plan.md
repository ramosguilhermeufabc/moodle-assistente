# Implementation Plan: spec-006 — Contrato de Eventos e Fila Lógica

**Branch**: `feature/sdd-ajustado` (feature dir `006-contrato-de-eventos-e-fila-l-gica`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/006-contrato-de-eventos-e-fila-l-gica/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026, com a validação técnica do §5 (CL-02) como pendência que não bloqueia o planejamento. A aprovação isolada não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Definir eventos próprios persistentes em `local_assistente_event` (course_id, event_type, payload JSON/text, created_at, status, processed_at, response) e a fila lógica alimentada pelo fluxo `Moodle event → observer → assistant event` [REQ spec-006 §Contratos]. Um evento representa uma ocorrência. Os estados são `pending`, `processing`, `processed` e `failure`. A LLM não é executada no observer. Um questionário publicado gera exatamente um evento; ele é publicado quando o questionário passa a ficar visível aos estudantes pela primeira vez, seja criado já visível, seja tornado visível depois, e o evento é único por questionário (DEC-31). Toda postagem ou interação no fórum gera evento e dispara o fluxo de atualização [REQ §Regras]. *(Alterado em 25/10/2026, conforme a spec propagada por DEC-03; texto original: "e cada atualização relevante de fórum gera um evento".)* A identidade de deduplicação é definida por tipo concreto de evento, sem identificador universal, e a repetição da mesma ocorrência reutiliza o processamento existente [REQ §Regras; DEC-08, DEC-10, DEC-21]. Os nomes dos eventos Moodle devem ser verificados na 5.2 [REQ §Conformidade].

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: Moodle Events API (observers) [REQ §Conformidade]; persistência spec-003; config spec-005.

**Storage**: `local_assistente_event` (índices `status`, `course_id,status`, `created_at`) [spec-002].

**Testing**: eventos/tasks e idempotência [REQ spec-023]. Runner em Q-023-01.

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, contrato de eventos e fila.

**Performance Goals**: N/A. O observer não executa LLM [REQ §Regras].

**Constraints**: exatamente um evento por questionário publicado; nenhum nome de evento Moodle presumido [REQ §Regras, §Conformidade].

**Scale/Scope**: os tipos de evento estão em Q-006-02.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Eventos Moodle 5.2 não verificados (Q-006-01). *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, catálogo de eventos verificado no Moodle 5.2 em CL-02 (FECHADA em 02/11/2026, com limitações residuais; T047 [x] e T066 [x]))* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | PASSA | PASSA | Evento persistido antes do processamento. |
| §8 Idempotência/concorrência | BLOQUEADO | PASSA | O desenho está definido: idempotência por evento lógico e identidade por tipo (DEC-08, DEC-21), um evento de publicação por questionário (DEC-31) e concorrência pela spec-007 (DEC-16, DEC-22). Pelo P-01 aprovado, não se acrescenta chave ao esquema antes da evidência. A validação fica no §5 (CL-02). *(Alterado em 25/10/2026; texto anterior: "BLOQUEADO \| O mecanismo que garante "exatamente um evento" está indefinido: não há chave de deduplicação no esquema (Q-006-04).")* |
| §9 Testabilidade | BLOQUEADO | PASSA | Q-006-05 foi resolvida pela spec (DEC-03: toda postagem ou interação). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Critério de "atualização relevante" indefinido (Q-006-05).")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | A decisão humana pendente (definição de publicação) foi tomada em DEC-31. A justificativa anterior, que exigia mudança de esquema, contrariava o P-01 aprovado, que manda não alterar o esquema antes da evidência. As decisões humanas restantes (Q-006-07, Q-006-08) são rastreadas nos planos consumidores e no CL-27. *(Alterado em 25/10/2026; texto anterior: "BLOQUEADO \| Q-006-04 exige "exatamente um evento" sem chave de idempotência no esquema: depende de decisão humana e de mudança de esquema aprovada.")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | A LLM não é executada no observer [REQ spec-006 §Regras]. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5. Questões Bloqueador: Q-006-01 (validação técnica, CL-02). *(Alterado em 25/10/2026 por DEC-31; texto anterior: "princípios BLOQUEADO: §5, §8, §11. Questões Bloqueador: Q-006-01, Q-006-04.")* *(Alterado em 25/10/2026; texto original: "§2, §5, §8, §9, §11".)* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa (CL-02 FECHADA); nenhum princípio BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/006-contrato-de-eventos-e-fila-l-gica/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/assistant-event.md
```

### Source Code (repository root)

```text
assistente/
└── [observers + serviço de enfileiramento]   # registro de observers e caminhos NEEDS CLARIFICATION (Q-006-01, Q-003-04) (04/11/2026: resolvido: Q-006-01 validada em CL-02 (FECHADA em 02/11/2026); observers em `assistente/classes/observer/` e registro em `assistente/db/events.php`)
```

## Dependências entre specs

- Upstream: spec-003, 004, 005 [REQ §Dependências].
- Downstream: 007 (consome a fila), 010, 013, 022 (integração de observers).

## Fases de planejamento & gates

1. Verificar os eventos Moodle 5.2 para cada gatilho (bloqueante para a validação, Q-006-01), partindo da matriz documental do P-01; para a publicação de questionário, `course_module_created`/`course_module_updated` (DEC-31). *(Alterado em 25/10/2026; texto original: "Verificar os eventos Moodle 5.2 para cada gatilho (bloqueante, Q-006-01).")*
2. Definir `event_type` e o payload por tipo (Q-006-02/03) e documentar a estratégia de deduplicação de cada tipo, com suas limitações (DEC-21); para a publicação, no máximo um evento por módulo de questionário (DEC-31). *(Alterado em 25/10/2026; texto original: "Definir `event_type` e o payload por tipo (Q-006-02/03), mais a regra de deduplicação (Q-006-04).")*
3. Definir as transições de estado ([data-model.md](data-model.md)).
4. **Gate G-006**: cenários Q1–Q10 do [quickstart.md](quickstart.md): publicação única conforme DEC-31, recepção duplicada e concorrente, edições legítimas distintas, observer sem chamada à LLM e só os quatro estados. *(Alterado em 25/10/2026; texto original: "publicação duplicada não cria segundo evento; observer sem chamada LLM; estados restritos aos quatro valores.")*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-006-01 | Resolvida *(tipo original: "Bloqueador")* | Eventos Moodle 5.2 concretos para: questionário publicado, atualização de fórum e demais gatilhos. *(04/11/2026, etapa 5a: Resolvida; evidência: `assistente/db/events.php` (15 observers) e a tabela do catálogo da T132 (`specs/022-*/tasks.md`, Q10); documentado em `contracts/assistant-event.md` (04/11/2026).)* | §Conformidade |
| Q-006-02 | Resolvida *(tipo original: "Aberto")* | Lista de valores de `event_type`. *(04/11/2026, etapa 5a: Resolvida; evidência: `assistente/classes/domain/event/event_type.php` (17 valores), listados em `contracts/assistant-event.md` (04/11/2026).)* | §Contratos |
| Q-006-03 | Resolvida *(tipo original: "Aberto")* | Esquema do `payload` por tipo. *(04/11/2026, etapa 5a: Resolvida; evidência: formato do payload de cada tipo documentado em `contracts/assistant-event.md` (04/11/2026), conferido contra `event_type::fields()` e os serviços de evento.)* | §Contratos |
| Q-006-04 | Resolvido | O que é "publicado" e como garantir "exatamente um evento" (não há índice único/chave de idempotência em `event`). **Resolvido no nível funcional (25/10/2026, DEC-31):** publicação é a primeira visibilidade aos estudantes, com um evento por questionário. O mecanismo é a estratégia por tipo da DEC-21, sem campo novo no esquema (P-01). Risco R-006-01: `event` não tem coluna de módulo (o `cmid` fica no payload), o que a validação em CL-02 deve considerar. *(Tipo anterior: "Bloqueador".)* **Parcial (25/10/2026):** a spec define idempotência por evento lógico e identidade por tipo concreto de evento, sem identificador universal (DEC-08, DEC-10, DEC-21). Continuam bloqueados: verificação dos dados de cada evento no Moodle 5.2 e o mecanismo de deduplicação sem chave no esquema. | §Regras; spec-002 |
| Q-006-05 | Resolvido | Critério de "atualização relevante de fórum". **Resolvido pela spec (25/10/2026):** toda postagem ou interação no fórum gera evento (DEC-03). *(Tipo original: "Aberto".)* | §Regras; DEC-03 |
| Q-006-06 | Resolvida *(tipo original: "Parcial")* | Transições permitidas, conteúdo de `response` e momento de `processed_at`. **Parcial (25/10/2026, DEC-32 D4):** transições definidas (`pending` → `processing` → `processed`; falha → `pending` com tentativas restantes; `failure` depois da 3ª falha). Continuam abertos o conteúdo de `response` e o momento de `processed_at`. A anotação não altera a aprovação do plano 006. *(Tipo original: "Aberto".)* *(04/11/2026, etapa 5a: Resolvida; evidência: transições por DEC-32 D4 e, para `response` e `processed_at`, o contrato de `assistente/classes/infrastructure/persistence/event_repository.php` ("What is stored in the free fields (Q-006-06)"); T042 [x]; `assistente/tests/infrastructure/persistence/event_repository_test.php`.)* | §Contratos; DEC-32 |
| Q-006-07 | Resolvida *(tipo original: "Aberto")* | *(25/10/2026: decisão de cada spec consumidora (008, 011, 012, 014, 016, 019), rastreada nos planos delas; não bloqueia o plano 006.)* Outras fontes passam pela fila? Disponibilidade de material (008), fechamento de questionário (011), notas (012), frequência (014), relatório (019). spec-006 só nomeia questionário e fórum. Impacto da síntese por curso e seção (spec-016): quais eventos iniciam ou atualizam a síntese, como se verifica a disponibilidade dos resultados necessários, o comportamento diante de resultados ausentes (espera, nova tentativa ou encerramento, compatível com o limite de tentativas) e como o `section_id` explícito é transportado no contexto do processamento. A lista de eventos e o contrato do `payload` não foram definidos. *(04/11/2026, etapa 5a: Resolvida; evidência: tipos `material_changed`, `quiz_closed`, `grade_changed`, `participation_changed`, `synthesis_requested`, `criteria_check_requested` e `course_criteria_check_requested` em `assistente/classes/domain/event/event_type.php`, consumidos pelas specs 008, 011, 012, 014 e 016; o relatório (019) não passa pela fila de eventos e é disparado pela tarefa agendada `assistente/classes/task/report_dispatcher.php`.)* | §Regras; 008/011/012/014/016/019 |
| Q-006-08 | Resolvida *(tipo original: "Aberto")* | Eventos de curso sem `config`/desabilitado devem ser criados? *(25/10/2026: depende de Q-005-03, rastreada em `closure-register.md` CL-27.)* *(04/11/2026, etapa 5a: Resolvida; evidência: DEC-45 D3 (curso sem configuração ou desabilitado não gera evento); `assistente/tests/observer/catalog_test.php` (Q4: `test_a_course_not_enabled_creates_no_event`).)* | spec-005 |
| R-006-01 | Resolvido *(tipo original: "Risco")* | *(Incluído em 25/10/2026.)* A deduplicação da publicação por módulo (DEC-31) depende do `cmid` no payload, porque `local_assistente_event` não tem coluna de módulo nem índice para isso. Pelo P-01, só se acrescenta campo depois de demonstrar a necessidade com código e testes. *(04/11/2026, etapa 5a: Resolvido; evidência: a identidade da publicação é o `cmid` (`QUIZ_PUBLISHED`, uma vez por objeto) em `assistente/classes/application/quiz/quiz_publication_service.php`, sem coluna nova; `assistente/tests/observer/catalog_test.php` (`test_the_same_occurrence_twice_is_one_event`) e `assistente/tests/application/quiz/quiz_publication_service_test.php` (`test_second_dispatch_keeps_one_event`).)* | spec-002; DEC-31; P-01 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Toda postagem ou interação no fórum gera evento [REQ spec-006 §Regras] | DEC-03 | Summary corrigido; Q-006-05 resolvida; §9 passa | Incorporada |
| Idempotência por evento lógico; nova ocorrência legítima cria novo evento [REQ §Regras] | DEC-08 | Q-006-04 parcial | Incorporada |
| Identidade de deduplicação por tipo concreto de evento, verificada no Moodle 5.2; sem identificador universal [REQ §Regras] | DEC-10, DEC-21 | Q-006-01 e Q-006-04 continuam bloqueadoras (validação técnica, `closure-register.md` CL-02) | Incorporada; validação pendente *(04/11/2026, revisão dos planos: CL-02 FECHADA em 02/11/2026.)* |
| Concorrência, tentativas e recuperação seguem a spec-007 (um processamento por evento, bloqueio por evento lógico, até 3 tentativas totais) [REQ §Regras] | DEC-16, DEC-22 | Q-006-06 (transições) deve alinhar com a spec-007 | Incorporada |
| Questionário publicado = primeira visibilidade aos estudantes; um evento por questionário; sem novo evento ao ocultar/mostrar ou editar [REQ spec-006 §Regras] | DEC-31 | Q-006-04 resolvida; §8 e §11 passam; fases 1, 2 e 4 e quickstart atualizados; R-006-01 registrado | Incorporada (25/10/2026); validação em CL-02 |

## Complexity Tracking

Nenhuma violação justificável.
