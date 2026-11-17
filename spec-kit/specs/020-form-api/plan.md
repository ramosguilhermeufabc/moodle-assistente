# Implementation Plan: spec-020 — Form API

**Branch**: `feature/sdd-ajustado` (feature dir `020-form-api`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/020-form-api/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, conforme DEC-45 e as verificações de consistência. A validação técnica do §5 (CL-40) continua pendente e R-020-01 fica como risco; nenhum dos dois bloqueia o planejamento. Nenhum cenário do quickstart foi executado. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-40 Fechado (VT) 03/11/2026; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado. *(Declaração da etapa de planejamento, mantida como histórico. Execuções posteriores estão registradas nas tarefas de `tasks.md`; para a sincronização inicial, Q9 a Q13 foram executados em 29/10/2026 e Q10 foi ampliado com o fórum e reexecutado em 31/10/2026 por `assistente/tests/application/sync/initial_sync_service_test.php` (ver T121). Esta nota não afirma execução de nenhum outro cenário.)*

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Usar a Form API do Moodle (`moodleform`/formslib) para configuração, critérios e filtros de consulta/relatório, sem regras de domínio na apresentação, no fluxo `form → validation → application service` [REQ spec-020 §Objetivo, §Contratos]. Os critérios são imutáveis depois da configuração, a autorização é validada antes de persistir e a sanitização/validação segue as convenções Moodle [REQ §Regras]. HTML/POST manual não é usado quando houver formulário Moodle [REQ §Conformidade].

*(Incluído em 26/10/2026 por DEC-45 e pelos tratamentos da revisão.)* Gravação só com `local/assistente:manage` no contexto do curso, verificada no backend (D1). Critérios somente leitura para professores depois de salvos; correção por administrador, válida só para novas avaliações (D2). O assistente começa desabilitado; habilitar dispara uma sincronização inicial do estado atual, desabilitar interrompe novos eventos e preserva o histórico (D3). Critérios em percentual 0–100 com até três casas e fórum como caixa de seleção (DEC-40). Filtros: grupo, seção e versão/data de relatório (DEC-43, DEC-44).

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: Moodle Form API (`moodleform`/formslib) [REQ]; serviços de 005, 018 e 019. *(26/10/2026: Access API para `local/assistente:manage` e `view`; serviço de sincronização inicial (D3), cujo disparo técnico fica em CL-40.)*

**Storage**: via serviços (spec-005: `config`/`criteria`). *(26/10/2026: `config.enabled` 0/1; sem campo novo.)*

**Testing**: acesso e UI quando aplicável [spec-023]. Runner em Q-023-01. *(26/10/2026: também permissão de gravação, somente leitura para professor, correção por administrador, faixas e habilitação/sincronização.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, apresentação (formulários).

**Performance Goals**: N/A. Ver R-020-01.

**Constraints**: sem regra de domínio no form; autorização antes de persistir [REQ]. O formulário valida só formato e faixa; a comparação dos critérios fica no serviço (DEC-11). *(26/10/2026: incluída a segunda frase.)*

**Scale/Scope**: 3 grupos de formulário [REQ §Contratos].

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | Form → service. |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-40): Form API e validação, sesskey, capability `manage` e arquétipos, página de configuração por curso e disparo da sincronização no Moodle 5.2. *(Alterado em 26/10/2026; observação original: "Form API no Moodle 5.2 não verificada.")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-40 (Fechado (VT) em 03/11/2026, com limitações aceitas; commit de7d506).)* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | PASSA | PASSA | A imutabilidade dos critérios é imposta no serviço (spec-005). *(26/10/2026: a correção administrativa vale só para novas avaliações e cada `criteria_check` preserva os limites usados, DEC-45 D2; desabilitar preserva o histórico, D3.)* |
| §8 Idempotência | N/A | N/A | — *(26/10/2026: a sincronização inicial respeita DEC-31 e DEC-33 D3 na reabilitação; ver a seção de consistência.)* |
| §9 Testabilidade | BLOQUEADO | PASSA | Campos, faixas e filtros definidos (DEC-40, DEC-45 e tratamentos); cenários Q1–Q14 do [quickstart.md](quickstart.md). *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| Campos e faixas de validação indefinidos (Q-020-01/02/04).")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | — *(26/10/2026: decisões humanas de CL-27 tomadas em DEC-45.)* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. Os formulários usam a Form API. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | PASSA | Gravação autorizada por `local/assistente:manage` no contexto do curso, verificada no backend antes de persistir; correção de critérios restrita ao administrador; leitura por DEC-30 (DEC-45 D1, D2). *(Alterado em 26/10/2026; texto anterior: "BLOQUEADO \| Autorização validada antes de persistir [REQ spec-020 §Regras]; o acesso de leitura está definido por DEC-30, mas a autorização para gravar a configuração e os critérios continua indefinida (herda Q-005-08)."; texto original da observação: "herda Q-005-01".)* |
| §15 Interfaces externas | — | PASSA | Formulários Moodle internos, sem API pública. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-40; não bloqueia o planejamento). Questões Bloqueador: nenhuma. *(Alterado em 26/10/2026 por DEC-45; texto anterior: "princípios BLOQUEADO: §5, §9, §14. Questões Bloqueador: nenhum.")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa a PASSA (validado em CL-40 (Fechado (VT) em 03/11/2026, com limitações aceitas; commit de7d506)); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/020-form-api/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/forms.md
```

### Source Code (repository root)

```text
assistente/
└── [classes moodleform: configuração, critérios, filtros]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: layout de Q-003-04 em 003/plan.md; classes moodleform em `assistente/classes/form/course_config_form.php` e `assistente/classes/form/query_filter_form.php`; testes em `assistente/tests/form/`)
```

## Dependências entre specs

- Upstream: spec-005, 018, 019 [REQ §Dependências].
- Downstream: 021. *(26/10/2026: 021 exibe "assistente não habilitado" e os critérios em somente leitura.)*
- *(26/10/2026)* A sincronização inicial (D3) aciona os fluxos das specs 008, 010, 012, 013 e 014 por eventos (spec-006/007).

## Fases de planejamento & gates

1. Campos de cada formulário e faixas de validação: `enabled` e critérios (percentual 0–100, até três casas; fórum 0/1) (Q-020-01, Q-020-04, resolvidas). *(Alterado em 26/10/2026; texto original: "Definir os campos de cada formulário (Q-020-01/02) e as faixas de validação (Q-020-04 = Q-005-05).")*
2. Formulário de critérios depois de salvos: somente leitura para professor; edição só para administrador (Q-020-03, resolvida por DEC-45 D2). *(Alterado em 26/10/2026; texto original: "Definir o comportamento do formulário de critérios após a definição (Q-020-03).")*
3. **Gate G-020**: um POST sem autorização é rejeitado no backend; a tentativa de alterar critérios é rejeitada. *(26/10/2026: a rejeição vale para professor; o administrador corrige com efeito só em novas avaliações; cenários Q1–Q14.)*

## Consistência da sincronização inicial com decisões aprovadas *(incluído em 26/10/2026)*

A sincronização de DEC-45 D3, sobretudo na reabilitação, não pode contrariar regras já aprovadas. Aplica-se, sem reabrir nenhuma delas:

| Item sincronizado | Regra preservada | Como |
|---|---|---|
| Questionário já publicado | DEC-31: um único evento de publicação e uma única análise inicial por questionário | A sincronização só gera o evento de publicação se o questionário estiver visível aos estudantes **e** ainda não tiver análise `quiz_initial`; na reabilitação, os já analisados são ignorados |
| Material | DEC-33 D3: nova versão só com mudança do conteúdo processável | Gera evento por material elegível; a versão só é criada se o conteúdo diferir da versão corrente |
| Notas e frequência | DEC-37 D1; DEC-39 D2 | Um recálculo por estudante; novas análises imutáveis |
| Fórum | DEC-38 D2 | Modo de reconstrução completa. *(31/10/2026, CL-47: um evento interno `forum_rebuild_requested` por módulo `forum` do curso, sem filtro por visibilidade ou tipo (fóruns ocultos e de avisos incluídos), enfileirado pela sincronização e processado por `forum_analysis_handler` no modo de reconstrução; coalesce enquanto pendente, de modo que reabilitar antes do processamento não empilha reconstruções, e não usa os eventos de exclusão, para que uma exclusão real posterior tenha evento próprio; ver o catálogo do plano 022.)* |
| Síntese | DEC-41 D2 | Fingerprint igual não cria versão |
| Eventos pendentes ao desabilitar | DEC-08; DEC-32 | O observer não cria eventos com o curso desabilitado; eventos já pendentes ficam `pending` e não são processados enquanto o curso estiver desabilitado; ao reabilitar, são processados normalmente |

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-020-01 | Resolvido | Campos de "configuração" além dos critérios (ex.: `enabled`). **Resolvido (26/10/2026):** só `enabled` (0/1) e os campos de critérios; o esquema não tem outros. Semântica de `enabled` por DEC-45 D3. *(Tipo original: "Aberto".)* | §Contratos; spec-005; spec-002; DEC-45 |
| Q-020-02 | Resolvido | Quais filtros de consulta/relatório existem. **Resolvido (26/10/2026):** grupo, só nas consultas individuais e nas partes individuais do relatório (DEC-43); seção, onde o dado é por seção; versão e data de execução para histórico e relatório (DEC-44). Nenhum outro. *(Tipo original: "Aberto".)* | §Contratos; spec-018; DEC-43; DEC-44 |
| Q-020-03 | Resolvido | Formulário de critérios após a definição: somente leitura? oculto? **Resolvido por DEC-45 D2 (26/10/2026):** somente leitura para professor; editável só pelo administrador, com efeito apenas em novas avaliações. *(Tipo original: "Aberto".)* | §Regras; DEC-45 |
| Q-020-04 | Resolvido | Faixas numéricas válidas (herda Q-005-05). **Resolvido por DEC-40 D1 (26/10/2026):** `activity_criterion` e `frequency_criterion` de 0 a 100, até três casas; `forum_criterion` 0/1. O formulário valida formato e faixa. *(Tipo original: "Aberto".)* | §Regras; DEC-40 |
| Q-020-05 | Resolvido | A configuração do intervalo da task pelo administrador (spec-007) usa formulário próprio? **Resolvido por DEC-32 (D1, 25/10/2026):** não; usa o agendamento nativo das Scheduled Tasks do Moodle. *(Tipo original: "Aberto".)* | spec-007; DEC-32 |
| R-020-01 | Aceito como limitação (03/11/2026) *(tipo original: "Risco")* | *(Incluído em 26/10/2026.)* A sincronização inicial de um curso grande gera muitos eventos e chamadas à LLM (análises iniciais, fóruns, materiais, sínteses) de uma vez, e se repete a cada reabilitação. Nenhum limite foi criado; medir nas tasks. *(Nota de 03/11/2026: a sincronização inicial dos materiais foi medida na CL-48, por contagem de leituras de banco em teste mockado; um evento por material, leituras proporcionais ao número de materiais, e a soma no pior caso (materiais ocultos a todos) em torno de estudantes × módulos², aceita como limitação; ver DEC-72.)* **Aceito como limitação (03/11/2026)**, conforme a CL-48 e a DEC-72 *(tipo original preservado: Risco)*. | DEC-45 D3 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Os formulários não redefinem a regra de comparação dos critérios, que é da spec-005 [REQ spec-020 §Regras] | DEC-11 | Confirma a ausência de regra de domínio no formulário; Q-020-04 (faixas) continua aberta, porque depende de Q-005-05 | Incorporada *(26/10/2026: Q-020-04 resolvida por DEC-40)* |
| Gravação, imutabilidade e habilitação | DEC-45 | Q-020-01 e Q-020-03 resolvidas; §9 e §14 passam; CL-27 reduzido | Incorporada (26/10/2026); validação em CL-40 |
| Unidade e faixas dos critérios | DEC-40 | Q-020-04 resolvida | Incorporada (26/10/2026) |
| Filtros de grupo e de relatório | DEC-43, DEC-44 | Q-020-02 resolvida | Incorporada (26/10/2026) |

Nenhuma questão deste plano foi resolvida pela propagação. Nada foi executado nem testado. *(26/10/2026: Q-020-01 a 05 resolvidas na revisão; nenhuma aberta.)* *(Declaração da etapa de planejamento, mantida como histórico. Execuções posteriores estão registradas nas tarefas de `tasks.md`; para a sincronização inicial, Q9 a Q13 foram executados em 29/10/2026 e Q10 foi ampliado com o fórum e reexecutado em 31/10/2026 por `assistente/tests/application/sync/initial_sync_service_test.php` (ver T121). Esta nota não afirma execução de nenhum outro cenário.)*

O `spec.md` desta feature não foi alterado: DEC-45 afeta só o plano. Propagações pendentes: 018 e 021 ("assistente não habilitado"; critérios em somente leitura); 023 (testes de permissão, correção administrativa e sincronização). No plano 005 aprovado, só foram acrescentadas referências em Q-005-03, 04, 07 e 08. No plano 015 aprovado, só foi acrescentada a referência a DEC-45 D2 (limites usados preservados no contexto de cada `criteria_check`), com autorização do responsável. *(Incluído em 26/10/2026; texto anterior da lista de pendências: "plano 015 aprovado (registrar no contexto de cada `criteria_check` os limites usados, DEC-45 D2 — exige autorização para incluir a referência); 018 e 021 …")* *(04/11/2026, revisão dos planos: propagação da DEC-45 feita: todos os planos-alvo (018, 021, 023) citam a decisão nos artefatos de plano.)*

## Critérios de aceitação de Q-020-02 *(incluído em 29/10/2026)*

A questão continua **Resolvida** (DEC-43, DEC-44); a decisão original não foi alterada. Esta seção torna explícito o comportamento dos filtros, detalhado em [contracts/forms.md](contracts/forms.md) e verificado pelos cenários Q15–Q26 do [quickstart.md](quickstart.md). O intervalo `de`/`até` inclusivo, a combinação com a versão e a mensagem de ausência de resultados vêm do pedido do responsável para a resolução de Q-020-02; não constam do texto de DEC-44.

| Critério | Rastro |
|---|---|
| CA-1 Relatório sem filtros mostra a versão atual | Q15; DEC-44 D1 |
| CA-2 Relatório com versão escolhida (atual ou histórica) mostra os dados e as referências dessa versão, sem recalcular nem alterar o manifesto | Q16, Q25; DEC-44 D1 |
| CA-3 Data de execução = `report_version.created_at`; `de` e `até` inclusivos, dia final inteiro, dias do fuso do site | Q17; DEC-44 D2 |
| CA-4 Versão e intervalo combinam | Q18 |
| CA-5 Histórico: sem filtros como antes; por versão, por datas e pelos dois | Q19, Q20 |
| CA-6 Intervalo invertido: erro de validação da Form API, sem resultado | Q21 |
| CA-7 Ausência de resultados: mensagem clara, sem erro e sem tabela | Q22 |
| CA-8 Grupo só nas partes individuais do relatório e nas consultas individuais; agregados e histórico sem grupo; permissões e grupos separados do Moodle respeitados, sem entidade de turma | Q23, Q26; DEC-43 |
| CA-9 Seção só onde o dado é por seção | Q24 |
| CA-10 Os parâmetros não ampliam o acesso | Q26; DEC-30 |

Tarefa: T143. Validação técnica no Moodle em execução: CL-38 (grupos) e CL-39 (fuso do site), **pendentes**. *(04/11/2026, revisão dos planos: CL-38 Fechado (VT) em 03/11/2026; CL-39 FECHADA em 28/10/2026.)*
## Complexity Tracking

Nenhuma violação justificável.
