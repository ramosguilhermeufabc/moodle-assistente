# Implementation Plan: spec-019 — Relatórios e Versionamento

**Branch**: `feature/sdd-ajustado` (feature dir `019-relat-rios-e-versionamento`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/019-relat-rios-e-versionamento/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, conforme DEC-44 (D1–D3 inalteradas) e as cinco correções documentais da revisão final. A validação técnica de fuso, cron, concorrência e datas continua pendente em CL-39, sem resultado antecipado, e R-019-01 fica como risco; nenhum dos dois bloqueia o planejamento. Nenhum cenário do quickstart foi executado. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-39 FECHADA 28/10/2026; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Manter um relatório lógico por curso (`local_assistente_report`) com versões históricas (`local_assistente_report_version`) [REQ spec-019 §Contratos]. O relatório é atualizado a cada 72 horas, contadas desde a última atualização bem-sucedida, até a data final da atividade elegível mais distante. A atualização final ocorre no dia seguinte a essa data; se coincidir com a periódica, executa-se uma única vez, identificada como final. Sem data final aplicável, não se agendam atualizações periódicas. A mudança da data mais distante recalcula a data da atualização final. A falha não conta como sucesso, não reinicia o intervalo e não cria outro relatório lógico. As versões anteriores permanecem consultáveis [REQ §Regras; DEC-06, DEC-07, DEC-17, DEC-18]. *(Alterado em 25/10/2026, conforme a spec propagada; texto original: "O relatório é atualizado a cada 3 dias até a data final da atividade mais distante, com uma atualização final um dia após essa data.")* O agendamento pode usar a Tasks API, e a versão atual respeita o escopo de acesso [REQ §Conformidade].

*(Incluído em 26/10/2026 por DEC-44 e pelos tratamentos da revisão.)* Cada versão é um **manifesto**: referências às análises imutáveis usadas por bloco, versões de síntese por seção, tipo, momento e estado de cada bloco, sem cópia de valores (D1). O relatório é renderizado na leitura com as regras do plano 018, a restrição por grupo de DEC-43 D2 e sem expor estudantes cujos dados foram excluídos (DEC-42 D1). "Dia seguinte" = 00:00 do próximo dia-calendário no fuso do site; as 72 horas são absolutas (D2). Datas movidas para o futuro depois da final reabrem o relatório (D3). Despachante próprio em Scheduled Task, sem passar pela fila de eventos (P-05).

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: Tasks API (opcional, "pode usar") [REQ §Conformidade]; consultas (spec-018); rastreabilidade (spec-017); autorização (spec-005). *(26/10/2026: despachante em Scheduled Task e Lock API por relatório (P-05); elegibilidade de atividades da DEC-39 D1; fuso do site Moodle (D2).)*

**Storage**: `report` (1:1 curso; `next_update_at`, `final_activity_date`, `final_update_at` e `final_completed_at`, anuláveis; `current_version` → `report_version.id`, referência única à versão corrente) e `report_version` (único `report_id,version`; `snapshot_json` = manifesto de DEC-44 D1; `created_at`) [spec-002; DEC-29]. *(Alterado em 26/10/2026; texto original: "`report` (1:1 curso; `next_update_at`, `final_activity_date`, `final_update_at`), `report_version` (único `report_id,version`; `is_current`; `snapshot_json`) [spec-002]." — `is_current` foi removido por DEC-29.)*

**Testing**: atualização final de relatório [REQ spec-023 §Regras]. Runner em Q-023-01. *(26/10/2026: também manifesto e renderização, fuso, reabertura, privacidade e exclusão de curso, com relógio simulado.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, consolidação agendada.

**Performance Goals**: N/A.

**Constraints**: periodicidade de 72 horas desde o último sucesso; atualização final em D+1, única quando coincide com a periódica; versões preservadas [REQ; DEC-06]. *(Alterado em 25/10/2026; texto original: "periodicidade de 3 dias; atualização final em D+1; versões preservadas [REQ].")* O snapshot não copia valores; a renderização não expõe resultados de estudantes excluídos (DEC-44 D1). *(26/10/2026: incluída a última frase.)*

**Scale/Scope**: 1 relatório por curso.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-39): fuso do site, 00:00 do dia seguinte, Scheduled Task e atraso do cron, Lock API por relatório, datas de entrega/encerramento no Moodle 5.2. *(Alterado em 26/10/2026; observação original: "Agendamento e Tasks API não verificados.")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-39 (FECHADA em 28/10/2026, com limitações residuais).)* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade/histórico | BLOQUEADO | PASSA | A versão corrente é a referência `current_version`, atualizada sob lock e transação (DEC-23, DEC-29); o conteúdo é o manifesto de DEC-44 D1, sobre análises imutáveis e sem expurgo (DEC-42 D4); versões anteriores, inclusive a final anterior numa reabertura, preservadas. *(Alterado em 26/10/2026; texto anterior: "BLOQUEADO \| A versão corrente passou a ser a referência `current_version`, atualizada sob lock e transação (DEC-23, DEC-29), mas o conteúdo do snapshot continua indefinido (Q-019-01)."; observação original: "A troca de `is_current` não é atômica no esquema (Q-002-05); conteúdo do snapshot indefinido (Q-019-01).")* |
| §8 Idempotência | BLOQUEADO | PASSA | A spec define reagendamento idempotente, serialização por relatório (Lock API) e uma única execução quando periódica e final coincidem (DEC-23). Q-019-06 (passagem pela fila) continua aberta, mas não afeta a idempotência. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Execução duplicada do ciclo pode gerar versões duplicadas (Q-019-06).")* *(26/10/2026: Q-019-06 resolvida — despachante próprio, P-05.)* |
| §9 Testabilidade | BLOQUEADO | PASSA | Fuso e "dia seguinte" definidos (DEC-44 D2); cenários Q1–Q16 do [quickstart.md](quickstart.md). *(Alterado em 26/10/2026; texto anterior: "BLOQUEADO \| A âncora e o caso sem data foram definidos pela spec (DEC-06, DEC-07), mas o fuso horário (Q-019-05, `closure-register.md` CL-06) continua aberto; o conflito do caso sem data com colunas NOT NULL foi resolvido por DEC-29 (25/10/2026)."; observação original: "Âncora dos 3 dias e casos sem data indefinidos (Q-019-03/05).")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | Q-019-01, Q-019-05 e Q-019-07 decididas pelo responsável (DEC-44); CL-06 e CL-17 fechados no nível funcional. *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| Q-019-01 (conteúdo do relatório) depende de decisão humana.")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | PASSA | A versão atual respeita o escopo de acesso [REQ spec-019 §Conformidade]; o critério operacional é o da DEC-30 (spec-005). *(Reavaliado em 25/10/2026: DEC-30; Pós anterior: "BLOQUEADO", "herda Q-005-01".)* As partes individuais seguem o modelo de grupos na leitura (DEC-43 D2). *(26/10/2026.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-39; não bloqueia o planejamento). Questões Bloqueador: nenhuma. *(Alterado em 26/10/2026 por DEC-44; texto anterior: "princípios BLOQUEADO: §5, §7, §9, §11 (§14 passou a PASSA por DEC-30 em 25/10/2026). Questões Bloqueador: Q-019-01.")* *(alterado em 25/10/2026; original: "§2, §5, §7, §8, §9, §11")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa a PASSA (validado em CL-39 (FECHADA em 28/10/2026, com limitações residuais)); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/019-relat-rios-e-versionamento/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/report.md
```

### Source Code (repository root)

```text
assistente/
└── [serviço de relatório + agendamento]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: layout de Q-003-04 em 003/plan.md; serviço de relatório em `assistente/classes/application/report/` (`report_generator.php`, `report_builder.php`, `report_collector.php`); agendamento em `assistente/classes/task/report_dispatcher.php` registrado em `assistente/db/tasks.php`)
```

## Dependências entre specs

- Upstream: spec-017, spec-018 [REQ §Dependências].
- Downstream: 020 (filtros), 021 (exibição). *(26/10/2026: 021 renderiza o manifesto com as regras do plano 018; 023 testa fuso, reabertura e privacidade.)*

## Fases de planejamento & gates

1. Conteúdo do relatório e do `snapshot_json`: manifesto de referências (Q-019-01, resolvida por DEC-44 D1).
2. Ciclo: âncora de 72 h absolutas, atividade elegível mais distante pela data própria, "dia seguinte" no fuso do site, reabertura e mudanças de data (Q-019-02…05, Q-019-07, resolvidas por DEC-44 D2/D3 e pelos tratamentos da revisão).
3. Execução: despachante próprio em Scheduled Task, sem passar pela fila de eventos (Q-019-06, resolvida por P-05).

*(Fases 1 a 3 alteradas em 26/10/2026, sem classificação de bloqueio; textos originais: "1. Definir o conteúdo do relatório e do `snapshot_json` (bloqueante, Q-019-01)."; "2. Definir o ciclo: âncora, atividade mais distante e mudanças de data (Q-019-02…05, Q-019-07)."; "3. Decidir se o relatório passa pela fila/processing (Q-019-06).")*
4. **Gate G-019**: simulação temporal com 72 horas desde o último sucesso, falha sem reinício do intervalo, data final, D+1, coincidência periódica/final e mudança da data final ([quickstart.md](quickstart.md)). *(Alterado em 25/10/2026; texto original: "simulação temporal com 3 dias, data final e D+1".)* *(26/10/2026: cenários Q1–Q16.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-019-01 | Resolvido | O que o relatório consolida (o conteúdo de `snapshot_json`). **Resolvido por DEC-44 D1 (26/10/2026):** manifesto com as referências às análises imutáveis por bloco (todos os blocos da spec-018, exceto histórico), versões de síntese por seção, tipo, momento e estado; sem cópia de valores; renderização na leitura com as regras do plano 018, restrição por grupo e sem expor estudantes excluídos. CL-17 fechado no nível funcional. *(Tipo original: "Bloqueador".)* | §Objetivo; DEC-44 |
| Q-019-02 | Resolvido | "Atividade mais distante": quais atividades e qual data (final? devida?); recálculo se a data mudar. **Parcial (25/10/2026):** a spec define o recálculo quando a data muda (DEC-18) e a "atividade elegível com a data mais distante" (DEC-06). Quais atividades são elegíveis e qual data usar continuam abertos. **Resolvido (26/10/2026):** atividades elegíveis da DEC-39 D1; a data é a de entrega ou encerramento **própria** da atividade, sem `course.enddate` (DEC-07: "data final aplicável de atividade"). *(Tipo original: "Aberto".)* | §Regras; DEC-06, DEC-07, DEC-18, DEC-39 |
| Q-019-03 | Resolvido | Curso sem atividades datadas: `final_activity_date`/`final_update_at` são NOT NULL. **Resolvido (25/10/2026):** a spec define que não se agendam atualizações periódicas sem data final aplicável (DEC-07), e DEC-29 tornou anuláveis `next_update_at`, `final_activity_date` e `final_update_at`. *(Tipo original: "Aberto"; estado intermediário do mesmo dia: "Parcial".)* | §Regras; spec-002; DEC-07, DEC-29 |
| Q-019-04 | Resolvido | Quando o primeiro relatório é criado e o valor inicial de `next_update_at`. **Resolvido (26/10/2026):** o `report` é criado quando o despachante encontra a primeira atividade elegível datada no curso; a primeira versão é gerada nessa execução; depois, 72 horas desde o último sucesso. *(Tipo original: "Aberto".)* | §Regras; DEC-06; P-05 |
| Q-019-05 | Resolvido | "A cada 3 dias": a partir da criação ou da última atualização; fuso horário. **Parcial (25/10/2026):** a âncora foi definida pela spec, 72 horas desde a última atualização bem-sucedida (DEC-06). O fuso horário continua aberto (`closure-register.md` CL-06). **Resolvido por DEC-44 D2 (26/10/2026):** "dia seguinte" = 00:00 do próximo dia-calendário no fuso do site Moodle; 72 horas em tempo absoluto. *(Tipo original: "Aberto".)* | §Regras; DEC-06; DEC-44 |
| Q-019-06 | Resolvido | O relatório passa pela fila (event/processing)? O esquema não vincula `report` a processing (Q-017-04). **Resolvido (26/10/2026):** não; despachante próprio em Scheduled Task, com Lock API por relatório e transação DML (P-05, DEC-23); a rastreabilidade vem das referências do manifesto (plano 017). *(Tipo original: "Aberto".)* | spec-017; spec-002; P-05 |
| Q-019-07 | Resolvido | Novas informações após a atualização final. **Resolvido por DEC-44 D3 (26/10/2026):** sem alteração de datas, nenhuma versão nova; se a última data elegível for movida para o futuro, o relatório é reaberto (`final_completed_at` limpo, data final recalculada, ciclo de 72 h retomado), com o histórico preservado. A falha da atualização final é registrada e retomada na execução seguinte do despachante, sem reiniciar as 72 horas (DEC-17). *(Tipo original: "Aberto".)* | §Regras; DEC-17; DEC-44 |
| R-019-01 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | *(Incluído em 26/10/2026.)* O manifesto depende de as análises referenciadas continuarem existindo. Hoje não há expurgo (DEC-42 D4); uma futura política de retenção terá de preservar as análises referenciadas por versões de relatório ou tratar a renderização das versões antigas. *(04/11/2026, etapa 5a: Aceito como limitação (04/11/2026); evidência: decisão do responsável: volume, custo ou falha repetida, casos de exceção não tratados.)* | DEC-44 D1; DEC-42 D4 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| 72 horas desde a última atualização bem-sucedida; final no dia seguinte; coincidência executa uma vez como final [REQ spec-019 §Regras] | DEC-06 | Summary, Constraints e gate corrigidos; Q-019-05 parcial | Incorporada |
| Sem data final aplicável, não agenda [REQ §Regras] | DEC-07 | Q-019-03 resolvida (datas anuláveis por DEC-29) | Incorporada *(estado original: "Incorporada; esquema bloqueado")* |
| Falha não conta como sucesso nem reinicia o intervalo; recuperação sem outro relatório lógico [REQ §Regras] | DEC-17 | Summary; o gate cobre a falha | Incorporada |
| Mudança da data final recalcula a atualização final [REQ §Regras] | DEC-18 | Q-019-02 parcial | Incorporada |
| Ausência de dados × zero real × erro técnico [REQ §Regras] | DEC-24 | O conteúdo do relatório (Q-019-01) deve carregar o estado | Incorporada |
| Pontuação igual em ordem alfabética do nome [REQ §Regras] | DEC-02 | Aplica-se às listas do relatório | Incorporada |
| Despachante periódico, datas persistidas, serialização por relatório, `final_completed_at` e `current_version` [REQ spec-019 §Regras] | DEC-23, DEC-29 | Propagada em 25/10/2026; §8 passa; Q-019-06 resolvida em 26/10/2026 (despachante de P-05) *(texto original: "Q-019-06 continua aberta")* | Incorporada; validação pendente *(estado original: "**Não propagada à spec** (CL-07); Q-019-06 e §8 continuam abertos \| Bloqueada")* |
| Manifesto, fuso do site, reabertura | DEC-44 | Q-019-01, 05 e 07 resolvidas; CL-06 e CL-17 fechados no nível funcional; §7, §9 e §11 passam | Incorporada (26/10/2026); validação em CL-39 |
| Regras de apresentação das consultas; turma e grupos | DEC-35 a DEC-41; DEC-43 | Renderização do manifesto segue o plano 018; partes individuais com restrição por grupo na leitura | Incorporada (26/10/2026) |
| `snapshot_json` com `analysis_id` e versões de síntese; exclusão com o curso; privacidade | DEC-42 | Manifesto; exclusão de `report` e versões com o curso; renderização sem estudantes excluídos | Incorporada (26/10/2026); validação em CL-37 |
| Elegibilidade das atividades | DEC-39 D1 | Q-019-02 resolvida | Incorporada (26/10/2026) |

Q-019-01 a Q-019-07 estão resolvidas no nível funcional (DEC-06, DEC-07, DEC-29, DEC-44, P-05 e tratamentos da revisão). Nenhuma questão do plano continua aberta; resta a validação técnica não bloqueante CL-39 e o risco R-019-01. *(Alterado em 26/10/2026; texto original: "Continuam abertas: Q-019-01 (bloqueadora, conteúdo do relatório), Q-019-04, Q-019-06 e Q-019-07.")*

O `spec.md` desta feature não foi alterado: DEC-44 afeta só o plano. Propagações pendentes, a aplicar na revisão de cada plano: 020 (filtros do relatório e de grupo), 021 (renderização do manifesto, estados e indicações, versões históricas), 023 (testes de fuso, reabertura, privacidade e exclusão de curso). *(Incluído em 26/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-44 feita: todos os planos-alvo (020, 021, 023) citam a decisão nos artefatos de plano.)*

## Complexity Tracking

Nenhuma violação justificável.
