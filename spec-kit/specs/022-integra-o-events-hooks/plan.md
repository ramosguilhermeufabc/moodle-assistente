# Implementation Plan: spec-022 — Integração Events/Hooks

**Branch**: `feature/sdd-ajustado` (feature dir `022-integra-o-events-hooks`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/022-integra-o-events-hooks/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, conforme DEC-47, com as referências autorizadas registradas nos planos 018 e 021. A verificação dos nomes concretos dos eventos (CL-02, CL-33 a CL-37, CL-40, CL-41) e da leitura de matrículas (CL-38) continua pendente e não bloqueia o planejamento; R-G3 fica como risco. Nenhum cenário do quickstart foi executado. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-02 (02/11), CL-33 a CL-36, CL-38, CL-40, CL-41 (03/11) e CL-37 (28/10): todos Fechados; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado. *(Declaração da etapa de planejamento, mantida como histórico. Execuções posteriores estão registradas nas tarefas de `tasks.md` e, para o tipo interno `forum_rebuild_requested`, em `closure-register.md` CL-47 e na T121 da spec 020 (31/10/2026). Esta nota não afirma que os cenários desta spec tenham sido reexecutados.)*

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Conectar o plugin aos pontos de extensão do Moodle sem espalhar regras de negócio. Events seguem o fluxo `Moodle event → observer → assistant event`. Hooks são usados somente quando houver hook Moodle apropriado e necessário. Observers e hooks são finos e delegam para application services [REQ spec-022 §Objetivo, §Contratos]. A LLM não é executada no request/observer, nenhum evento ou hook é inventado, o evento Moodle é validado na 5.2 e os eventos próprios continuam persistidos na fila [REQ §Regras].

*(Incluído em 26/10/2026 por DEC-47 e pelos tratamentos da revisão.)* O catálogo lógico de gatilhos está consolidado a partir das decisões aprovadas (ver "Catálogo de gatilhos"); os nomes concretos dos eventos do Moodle 5.2 são candidatos, verificados nas validações técnicas já abertas. Observers verificam `config.enabled` e não criam evento em curso sem configuração ou desabilitado. Não há observer de matrícula nem de papel (DEC-47). Hooks só para navegação e, se confirmado, exclusão de curso.

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: Moodle Events API; Hooks API (PSR-14, segundo a spec) [REQ §Conformidade]; enfileiramento (spec-006). *(26/10/2026: deduplicação por tipo de evento, DEC-21; Scheduled Task para as detecções sem evento, spec-007.)*

**Storage**: `local_assistente_event` (spec-006).

**Testing**: eventos/tasks [spec-023]. Runner em Q-023-01. *(26/10/2026: também curso desabilitado, ausência de LLM e de regra de negócio no observer, e ausência de gatilho de matrícula.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, integração com pontos de extensão.

**Performance Goals**: N/A. Observer sem LLM [REQ].

**Constraints**: não inventar evento/hook; observer fino [REQ]. Nenhum gatilho além dos previstos nas specs e decisões aprovadas (DEC-47). *(26/10/2026: incluída a segunda frase.)*

**Scale/Scope**: lista de eventos em Q-022-01. *(26/10/2026: consolidada no catálogo abaixo.)*

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | Nenhum evento/hook foi nomeado sem evidência. *(26/10/2026: os nomes do catálogo são candidatos marcados como não verificados.)* |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | Observer → service. |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento: nomes e dados dos eventos do Moodle 5.2 em CL-02, CL-33, CL-34, CL-35, CL-36, CL-37 e CL-40; hook de navegação em CL-41; registro dos observers em CL-02. *(Alterado em 26/10/2026; observação original: "Nenhum evento/hook 5.2 verificado (Q-022-01/02).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-02 (FECHADA em 02/11/2026), CL-33, CL-34, CL-35, CL-36, CL-40 e CL-41 (Fechado (VT) em 03/11/2026) e CL-37 (Fechado (VT) em 28/10/2026), todas com limitações aceitas ou residuais.)* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | PASSA | PASSA | Eventos persistidos na fila. |
| §8 Idempotência | BLOQUEADO | PASSA | Q-006-04 resolvida no nível funcional (DEC-31); estratégia por tipo (DEC-21); validação em CL-02. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Herda Q-006-04.")* |
| §9 Testabilidade | PASSA | PASSA | [quickstart.md](quickstart.md). *(26/10/2026: cenários Q1–Q10.)* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | — *(26/10/2026: matrícula e papéis decididos pelo responsável, DEC-47.)* |
| §12 Plataforma e arquitetura | — | PASSA | Observers e hooks finos que delegam para application services [REQ spec-022]. O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | A LLM não é executada no request nem no observer [REQ spec-022 §Regras]. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | PASSA | Pontos de extensão internos do Moodle, sem API pública. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 *(alterado em 25/10/2026; original: "§5, §8")* (validação técnica; não bloqueia o planejamento). Questões Bloqueador: nenhuma. *(Alterado em 26/10/2026; texto anterior: "Questões Bloqueador: Q-022-01.")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa a PASSA (validado em CL-02 (FECHADA em 02/11/2026), CL-33, CL-34, CL-35, CL-36, CL-40 e CL-41 (Fechado (VT) em 03/11/2026) e CL-37 (Fechado (VT) em 28/10/2026), todas com limitações aceitas ou residuais); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/022-integra-o-events-hooks/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/observers-and-hooks.md
```

### Source Code (repository root)

```text
assistente/
└── [registro de observers/hooks + classes observer]   # arquivos de registro NEEDS CLARIFICATION (04/11/2026: resolvido: observers registrados em `assistente/db/events.php`, hook de navegação em `assistente/db/hooks.php`, classes em `assistente/classes/observer/` e `assistente/classes/hook_callbacks.php`; Q-022-01 verificada em CL-02, Fechada em 02/11/2026) (Q-022-01)
```
*(26/10/2026: o arquivo de registro de observers e hooks é verificado em CL-02 e CL-41; caminhos em Q-003-04.)*

## Dependências entre specs

- Upstream (spec.md): spec-006, 007, 008, 010–021 [REQ §Dependências]. Divergência com o README em Q-022-03. *(26/10/2026: `specs/README.md` corrigido; Q-022-03 resolvida; CL-11 fechado.)*
- Downstream: 023.

## Catálogo de gatilhos *(incluído em 26/10/2026)*

Eventos do Moodle observados. Os nomes são **candidatos**, a verificar no Moodle 5.2, sem presumir existência (constituição §5):

| Gatilho lógico | Decisão de origem | Eventos Moodle candidatos | Validação |
|---|---|---|---|
| Questionário publicado (primeira visibilidade) | DEC-31 | criação e atualização de módulo de curso | CL-02 |
| Material incluído ou alterado | DEC-33 | criação e atualização de módulo de curso; alteração de arquivo | CL-02 |
| Tentativa submetida; correção manual; reavaliação | DEC-36 D4 | eventos do `mod_quiz` de submissão, correção manual e reavaliação | CL-33 |
| Nota atribuída, alterada ou removida | DEC-37 D1 | evento de nota do usuário; evento de remoção de nota | CL-34 |
| Mensagem criada, editada ou excluída; discussão excluída | DEC-03; DEC-38 | eventos do `mod_forum` de mensagem e discussão | CL-35 |
| Participação (submissão, conclusão, postagem, nota) | DEC-39 D2 | evento de submissão; evento de conclusão de módulo; eventos de fórum e de nota | CL-36 |
| Exclusão do curso | DEC-42 D2 | evento ou hook de exclusão de curso | CL-37 |
| Habilitação do assistente | DEC-45 D3 | gravação da configuração pelo serviço (sem evento Moodle) | CL-40 |
| Reconstrução completa do fórum solicitada pela sincronização inicial *(incluído em 31/10/2026)* | DEC-38 D2; DEC-45 D3; CL-47 | **Nenhum evento Moodle e nenhum observer.** Evento interno do plugin: tipo lógico `forum_rebuild_requested` (`event_type::FORUM_REBUILD_REQUESTED`), enfileirado por `initial_sync_service` (um por módulo `forum` do curso, sem filtro por visibilidade ou tipo: fóruns ocultos e de avisos também recebem o pedido) por `forum_event_service::rebuild_requested()`. Payload: só `cmid`. Identidade: `cmid`. Deduplicação: `COALESCE_PENDING` (pedido repetido enquanto pendente é o mesmo evento; pedido depois do processamento cria outro). Handler: `forum_analysis_handler`, no modo de reconstrução completa. Não reutiliza `forum_post_deleted` nem `forum_discussion_deleted`, que seguem independentes (uma vez por objeto) | CL-47 |

Detecções sem evento Moodle (Scheduled Task, não observer):

| Detecção | Decisão de origem |
|---|---|
| `timeclose` do questionário vencido | DEC-36 D4 |
| Data de referência de atividade ou fórum vencida | DEC-39 D2; DEC-40 |
| Relatório devido | P-05; DEC-23; DEC-44 |

Encadeamentos internos (sem evento Moodle): sínteses enfileiradas depois das análises a montante (DEC-41 D3); critérios recalculados depois das análises de 012 e 014 (DEC-40); reconstrução do fórum pedida pela sincronização inicial (`forum_rebuild_requested`, DEC-38 D2, DEC-45 D3, CL-47, 31/10/2026). *(04/11/2026, revisão da propagação, DEC-55: critérios por estudante após as análises individuais (`criteria_check_requested`) e um pedido por curso em `reference_date_reached` (`course_criteria_check_requested`).)*

Fora do catálogo: matrícula e atribuição de papéis (DEC-47).

## Fases de planejamento & gates

1. Inventariar e verificar no Moodle 5.2 os eventos para cada gatilho de 006, 008, 010, 011, 013 e 014 (bloqueante, Q-022-01). *(26/10/2026: o inventário lógico está consolidado no catálogo acima e deixa de bloquear; a verificação dos nomes fica nas validações técnicas indicadas.)*
2. Avaliar se algum hook é necessário (Q-022-02). Nenhum é exigido pelas specs. *(26/10/2026: só navegação, CL-41, e, se confirmado, exclusão de curso, CL-37.)*
3. **Gate G-022**: cada observer tem evidência do evento 5.2; nenhum observer contém regra de negócio ou chamada LLM. *(26/10/2026: também nenhum evento criado em curso desabilitado; cenários Q1–Q10.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-022-01 | Resolvido | Lista de eventos Moodle 5.2 a observar (herda Q-006-01/07). **Resolvido no nível funcional (26/10/2026):** catálogo lógico consolidado a partir de DEC-31, 33, 36, 37, 38, 39, 42, 45 e 47; os nomes concretos são candidatos, verificados em CL-02, 33 a 37 e 40. *(Tipo original: "Bloqueador".)* | §Regras; decisões citadas |
| Q-022-02 | Resolvido | Há hook necessário? (ex.: navegação, spec-021 Q-021-01). Nenhum é nomeado nas specs. **Resolvido (26/10/2026):** só navegação do curso (CL-41) e, se confirmado, exclusão de curso (CL-37); nenhum outro. *(Tipo original: "Aberto".)* | §Contratos |
| Q-022-03 | Resolvido | O `spec.md` lista 006, 007, 008, 010–021 (omite 009); o `specs/README.md` lista 006–021. Este plano usa o `spec.md`. **Resolvido (26/10/2026):** `specs/README.md` corrigido para seguir o `spec.md`, com o texto original preservado; CL-11 fechado. *(Tipo original: "Contradição".)* | §Dependências |
| Q-022-04 | Resolvido | O observer deve filtrar cursos sem `config`/desabilitados (Q-006-08)? **Resolvido por DEC-45 D3 (26/10/2026):** sim; o observer verifica `config.enabled` e não cria evento em curso sem configuração ou desabilitado. *(Tipo original: "Aberto".)* | spec-005/006; DEC-45 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |
| R-G3 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | Marcador `citeturn…` não resolvível. *(26/10/2026: mantido como risco; `spec.md` não alterado.)* *(04/11/2026, etapa 5a: Aceito como limitação, por decisão do responsável: as citações não são resolvíveis; os marcadores (`cite` e `turn` com caracteres Unicode de uso privado U+E200, U+E202 e U+E201, que `grep citeturn` não encontra) são mantidos nos 8 `spec.md` e no `research.md` porque o texto original é preservado; as regras dessas frases foram confirmadas por decisões e validações, não pelas citações.)* | §Conformidade |

## Revisão pós-propagação (25/10/2026)

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Identidade de deduplicação por tipo concreto de evento, sem identificador universal; limitações documentadas [REQ spec-022 §Regras] | DEC-10, DEC-21 | Q-022-01 continua bloqueadora (eventos do Moodle 5.2 a verificar) *(26/10/2026: Q-022-01 resolvida no nível funcional; verificação nas validações técnicas)* | Incorporada; validação pendente |
| Repetição reutiliza o processamento; nova ocorrência cria novo evento [REQ §Regras] | DEC-08 | §8 continua BLOQUEADO por Q-006-04 *(26/10/2026: §8 passa desde DEC-31)* | Incorporada |
| Concorrência e tentativas seguem a spec-007 [REQ §Regras] | DEC-16, DEC-22 | Sem mudança de desenho no observer | Incorporada |
| Toda postagem ou interação no fórum é observada e gera evento; o fluxo roda fora do observer [REQ §Regras] | DEC-03 | Q-022-01 deve incluir os eventos de postagem e interação | Incorporada |
| Publicação de questionário observada em `course_module_created`/`course_module_updated`, com verificação de visibilidade e de publicação anterior [REQ spec-022 §Regras] | DEC-31 | Q-022-01 deve incluir esses eventos; §8 passa | Incorporada (25/10/2026); validação em CL-02 |
| Gatilhos de questionário, nota, fórum, participação, exclusão de curso e habilitação | DEC-33, 36, 37, 38, 39, 42, 45 | Catálogo consolidado; Q-022-01 resolvida | Incorporada (26/10/2026) |
| Sem gatilho de matrícula ou papel; filtro na leitura | DEC-47 | Catálogo; Constraints | Incorporada (26/10/2026) |
| Curso sem configuração ou desabilitado não gera evento | DEC-45 D3 | Q-022-04 resolvida | Incorporada (26/10/2026) |

Continua aberta a contradição Q-022-03 (dependências × `specs/README.md`, `closure-register.md` CL-11). Nada foi executado nem testado. *(26/10/2026: deixou de valer — Q-022-03 resolvida e CL-11 fechado; Q-022-01 a 04 resolvidas.)*

O `spec.md` desta feature não foi alterado: DEC-47 afeta este plano e a leitura das listas por estudante. Propagações pendentes: 023 (testes de eventos, curso desabilitado e matrícula). Nos planos aprovados 018 e 021, só foram acrescentadas referências a DEC-47 (filtro na leitura), com autorização; o 019 renderiza pelas regras do 018 e não precisa de referência. *(Incluído em 26/10/2026; texto anterior: "Propagações pendentes: planos aprovados 018 e 021 (listas por estudante filtradas na leitura por matrícula ativa e papel avaliado; recém-matriculado como "sem dados") — exigem autorização para incluir referências; 019 já aprovado renderiza pelas regras do 018; 023 …")* *(04/11/2026, revisão dos planos: propagação da DEC-47 feita: todos os planos-alvo (023) citam a decisão nos artefatos de plano.)*

## Complexity Tracking

Nenhuma violação justificável.
