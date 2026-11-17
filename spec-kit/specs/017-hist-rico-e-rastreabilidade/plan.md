# Implementation Plan: spec-017 — Histórico, Versionamento e Rastreabilidade

**Branch**: `feature/sdd-ajustado` (feature dir `017-hist-rico-e-rastreabilidade`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/017-hist-rico-e-rastreabilidade/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, conforme DEC-42. A validação técnica do §5 (CL-37) continua pendente, inclusive a premissa sobre as chaves XMLDB; Q-017-07 estava aberta e foi resolvida em 27/10/2026 por DEC-56 (gate T100). Nenhum deles bloqueia o planejamento. Nenhum cenário do quickstart foi executado. A aprovação isolada não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Garantir que qualquer informação derivada possa ser reconstruída pela cadeia `Moodle source → assistant event → processing → attempt → context snapshot → analysis → result → report`, com o contexto registrando as versões efetivamente usadas [REQ spec-017 §Objetivo, §Contratos]. Regras [REQ §Regras]:
- toda atualização preserva o estado anterior;
- a remoção da fonte não apaga o histórico;
- todos os materiais usados ficam associados;
- timestamp e versão permanecem disponíveis;
- detecção, processamento e elegibilidade de cada versão de material ficam preservados; o snapshot registra a versão usada e o resultado da avaliação de disponibilidade (DEC-05, DEC-26);
- versões anteriores de materiais e sínteses são preservadas; cada síntese mantém vínculo com o processamento e o contexto; falha na transição preserva a versão corrente anterior (DEC-12, DEC-15);
- a síntese tem histórico próprio por curso e seção, sem versão redundante (DEC-04, DEC-13);
- uma ocorrência lógica corresponde a um único processamento; repetições, concorrência e retentativas não duplicam processamento nem resultados (DEC-08, DEC-16, DEC-22).

*(Itens incluídos em 25/10/2026 conforme a spec-017 propagada em 22/10/2026.)*

A implementação usa as tabelas do XMLDB e transações DML quando necessário [REQ §Conformidade].

*(Incluído em 26/10/2026 por DEC-42 e pelos tratamentos da revisão.)* Cada execução é uma `analysis` imutável; a atual é a última bem-sucedida por escopo. O `context_json` registra as versões de material e a disponibilidade; o `report_version.snapshot_json` registra os `analysis_id` e as versões de síntese usados. Fonte removida dentro do curso não altera o histórico; a remoção é derivada na leitura (D3). Duas exceções explícitas: a exclusão de dados pessoais pela Privacy API (D1) e a exclusão do curso (D2). Sem expurgo automático (D4).

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: esquema de spec-002; produtores 006–016. *(26/10/2026: também Privacy API e o mecanismo de exclusão de curso do Moodle 5.2, a confirmar em CL-37.)*

**Storage**: todas as tabelas de spec-002 (sem tabelas novas).

**Testing**: histórico e versionamento [spec-023]. Runner em Q-023-01. *(26/10/2026: também reconstrução da cadeia, fonte removida, exportação e exclusão por estudante, exclusão de curso.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, requisito transversal de dados.

**Performance Goals**: N/A. *(26/10/2026: crescimento do histórico medido na implementação, R-011-01 e R-016-01.)*

**Constraints**: nenhuma sobrescrita silenciosa (constituição §7); sem campos novos (spec-002). *(26/10/2026: as únicas remoções de histórico são as de DEC-42 D1 e D2, registradas.)*

**Scale/Scope**: toda informação derivada.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-37): chaves `foreign` do XMLDB no banco, provider da Privacy API, detecção da exclusão de curso e leitura de registros removidos no Moodle 5.2. *(Alterado em 26/10/2026; observação original: "Comportamento de FKs quando a fonte Moodle é removida (Q-017-03).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-37 (Fechado (VT) em 28/10/2026, com limitações residuais).)* |
| §6 Materiais | PASSA | PASSA | Todos os materiais usados associados (via snapshot). |
| §7 Integridade/histórico | BLOQUEADO | PASSA | Análises imutáveis por execução (Q-017-01); elo resultado → relatório pelo `snapshot_json` (Q-017-04); fonte removida preserva o histórico (D3); remoções só nas exceções de D1 e D2, registradas. *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| Estratégia de preservação para tabelas com unicidade por análise (Q-017-01); elo result→report (Q-017-04).")* |
| §8 Idempotência | N/A | N/A | — |
| §9 Testabilidade | BLOQUEADO | PASSA | Cenários Q1–Q12 do [quickstart.md](quickstart.md). *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| Depende de Q-017-01/02.")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | Decisões humanas tomadas (DEC-42); nenhuma mudança de esquema; CL-15 fechado no nível funcional. *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| Possíveis mudanças de esquema (Q-002-07) exigem aprovação.")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-37; não bloqueia o planejamento). Questões Bloqueador: nenhuma. Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Alterado em 26/10/2026 por DEC-42; texto original: "princípios BLOQUEADO: §5, §7, §9, §11. Questões Bloqueador: Q-017-01.")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa a PASSA (validado em CL-37 (Fechado (VT) em 28/10/2026, com limitações residuais)); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/017-hist-rico-e-rastreabilidade/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/traceability-chain.md
```

### Source Code (repository root)

Não há código próprio desta spec: ela impõe regras às camadas de 003 e 006–019. Caminhos em Q-003-04. *(26/10/2026: exceção — o provider da Privacy API e o tratamento da exclusão de curso são componentes próprios, caminhos em Q-003-04.)*

## Dependências entre specs

- Upstream (spec.md): spec-002, 006, 007, 008, 010–016 [REQ §Dependências]. Divergência com o README em Q-017-05. *(26/10/2026: `specs/README.md` corrigido; Q-017-05 resolvida.)*
- Downstream: 018, 019, 021, 022. *(26/10/2026: 019 deve registrar os `analysis_id` e as versões de síntese no `snapshot_json`; 021 exibe "fonte removida".)*

## Fases de planejamento & gates

1. Decidir a estratégia de preservação por tabela (bloqueante, Q-017-01). *(26/10/2026: análise imutável por execução, já aplicada nos planos 010–016.)*
2. Fechar a representação de versões usadas no snapshot (Q-017-02 = Q-008-06). *(26/10/2026: `context_json`, sem chave nova.)*
3. Decidir o comportamento diante da remoção de fontes (Q-017-03). *(26/10/2026: DEC-42 D1–D3.)*
4. **Gate G-017**: para um resultado qualquer, reconstruir a cadeia completa ([quickstart.md](quickstart.md)). *(26/10/2026: cenários Q1–Q12.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-017-01 | Resolvido | Como preservar o estado anterior em tabelas únicas por análise (`performance`, `frequency`, `criteria_result`, `questionnaire_metric`, …): uma nova `analysis`/`processing` por atualização? O esquema sugere, mas a spec não declara. **Resolvido (26/10/2026):** sim; cada execução é uma `analysis` imutável (`quiz_initial`, `quiz_results`, `grade_performance`, `forum_analysis`, `frequency`, `criteria_check`, `section_synthesis`), como já aprovado nos planos 010–016; a atual é a última bem-sucedida por escopo. *(Tipo original: "Bloqueador".)* | §Regras; spec-002; planos 010–016 |
| Q-017-02 | Resolvido | Versões usadas registradas só em `context_json`, sem FK relacional para `material_version`. **Resolvido (26/10/2026):** o `context_json` registra os `material_version` usados e a disponibilidade (DEC-26); a spec exige o registro, não uma chave relacional; sem campo novo. CL-10 atualizado. *(Tipo original: "Aberto".)* | §Contratos; spec-008 |
| Q-017-03 | Resolvido | "Remoção de fonte não apaga histórico" × FKs para tabelas Moodle (`course_modules`, `question`, `forum_discussions`, `user`…). Também não há campo para marcar fonte removida (spec-021). **Resolvido por DEC-42 (26/10/2026):** fonte removida dentro do curso preserva o histórico e as referências; a remoção é derivada consultando se o registro Moodle ainda existe, sem campo novo; 021 exibe "fonte removida" (D3). Exceções: exclusão de dados pessoais (D1) e de curso (D2). A não imposição das chaves pelo banco é suposição a confirmar em CL-37. CL-15 fechado no nível funcional. *(Tipo original: "Contradição".)* | §Regras; spec-002, spec-021; DEC-42 |
| Q-017-04 | Resolvido | Elo "result → report": `report_version.snapshot_json` não referencia processings/analyses; o conteúdo do snapshot não está definido. **Resolvido no escopo da 017 (26/10/2026):** o `snapshot_json` registra os `analysis_id` e as versões de síntese usados; o restante do conteúdo fica para o plano 019 (CL-17). *(Tipo original: "Aberto".)* | §Contratos; spec-019 |
| Q-017-05 | Resolvido | O `spec.md` lista 002, 006, 007, 008, 010–016 (omite 009); o `specs/README.md` lista 002, 006–016 (inclui 009). Este plano usa o `spec.md`. **Resolvido (26/10/2026):** `specs/README.md` corrigido para seguir o `spec.md`, com o texto original preservado (CL-11). *(Tipo original: "Contradição".)* | §Dependências |
| Q-017-06 | Resolvido | Retenção/expurgo de histórico (não especificado). **Resolvido por DEC-42 D4 (26/10/2026):** sem expurgo automático nesta etapa; crescimento medido na implementação; retenção só com evidências e nova decisão. *(Tipo original: "Aberto".)* | DEC-42 |
| Q-017-07 | **Resolvida em 27/10/2026 por DEC-56** (verificação por busca do nome completo, nome e sobrenome nos textos preservados do curso; caso positivo: redação no lugar com `[removido]` e registro no log; implementação na T104) | *(Incluído em 26/10/2026; texto original da lacuna:)* DEC-42 D1 preserva agregados e textos gerados pela LLM só se não contiverem dados que identifiquem o estudante, sem presumir anonimato. Falta definir como verificar isso numa solicitação de exclusão (por exemplo, busca do nome do estudante nos textos do curso) e o que fazer quando houver dado identificável. Não bloqueia o planejamento; a verificação técnica está em CL-37, e o tratamento do caso positivo precisa de decisão antes das tasks do provider de privacidade. | DEC-42 D1; DEC-34 D3 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Estados do material preservados; snapshot com versão e resultado da disponibilidade [REQ spec-017 §Regras] | DEC-05, DEC-26 | Summary; Q-017-02 continua aberta (versões só em `context_json`, sem FK) | Incorporada *(26/10/2026: Q-017-02 resolvida)* |
| Histórico imutável e transição segura [REQ §Regras] | DEC-12, DEC-15 | Summary | Incorporada |
| Histórico da síntese por curso e seção, sem versão redundante [REQ §Regras] | DEC-04, DEC-13 | Summary; campos de versão da síntese no esquema por DEC-29 (DEC-14) | Incorporada *(estado original: "Incorporada; esquema bloqueado")* |
| Uma ocorrência = um processamento; sem duplicação [REQ §Regras] | DEC-08, DEC-16, DEC-22 | Summary; backend de bloqueio a validar (CL-02) | Incorporada; validação pendente *(04/11/2026, revisão dos planos: CL-02 FECHADA em 02/11/2026.)* |
| Novidade da síntese por fingerprint | DEC-19 | Normalização em CL-03 | Bloqueada *(26/10/2026: Incorporada — CL-03 fechado no nível funcional por DEC-41)* |
| `current_version` do material | DEC-20, DEC-29 | No esquema e na spec-017 desde 25/10/2026; validação em CL-07 | Incorporada; validação pendente *(estado original: "Depende de CL-07 \| Bloqueada")* *(04/11/2026, revisão dos planos: CL-07 Fechado (VT) em 02/11/2026.)* |
| Dados pessoais, exclusão de curso, fonte removida, retenção | DEC-42 | Q-017-03 e Q-017-06 resolvidas; CL-15 fechado no nível funcional; §7, §9 e §11 passam | Incorporada (26/10/2026); validação em CL-37 |

Continuam abertas: Q-017-01 (bloqueadora), as contradições Q-017-03 (CL-15) e Q-017-05 (CL-11), e Q-017-04 (CL-17). Nenhuma questão foi resolvida pela propagação, que acrescentou regras sem fechar as lacunas de esquema. *(26/10/2026: deixou de valer — Q-017-01 a 06 resolvidas; continua aberta só Q-017-07.)*

O `spec.md` desta feature não foi alterado: DEC-42 afeta só o plano. Propagações pendentes, a aplicar na revisão de cada plano: 002 (Q-002-06: chaves para fontes removidas, conforme DEC-42 D3), 019 (`snapshot_json` com `analysis_id` e versões de síntese; exclusão de versões de relatório com o curso), 021 ("fonte removida"), 023 (testes de privacidade e de exclusão de curso). *(Incluído em 26/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-42 feita: todos os planos-alvo (002, 019, 021, 023) citam a decisão nos artefatos de plano.)*

## Complexity Tracking

Nenhuma violação justificável.
