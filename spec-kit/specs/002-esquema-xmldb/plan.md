# Implementation Plan: spec-002 — Esquema de Dados Base / XMLDB

**Branch**: `feature/sdd-ajustado` (feature dir `002-esquema-xmldb`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/002-esquema-xmldb/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, com os encaminhamentos documentais da revisão (inclusive `material_type` = `pdf`, `text`, `youtube` e timestamps Unix em segundos). Q-002-03 e Q-002-04 continuam parciais só nos pontos de Q-008-05 e Q-008-07, sem bloquear o planejamento. CL-07 e CL-37 continuam validações técnicas, sem resultado presumido. A aprovação isolada não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3, que define os 23 `specs/*/spec.md` como única fonte de contexto do projeto. Nenhum teste de compatibilidade foi executado nesta fase. O arquivo `install_auditado.xml` **não** foi lido nem alterado (PROMPT-3). A fonte usada é o §Inventário da spec.

**Legenda**: `[REQ spec-NNN §Seção]` = requisito explícito · `[ESCOLHA]` = escolha de planejamento · `NEEDS CLARIFICATION` / `Q-NNN-xx` = lacuna aberta.

## Summary

Materializar o esquema próprio do plugin em `local/assistente/db/install.xml` com as 21 tabelas, campos, chaves e índices exatamente como no §Inventário [REQ spec-002 §Objetivo, §Inventário]. O acesso aos dados é feito por DML, e mudanças estruturais passam por XMLDB/upgrade API [REQ §Contratos, §Regras]. Nenhum campo ou índice pode ser criado fora do inventário [REQ §Regras]. Lacunas de outras specs que exigiriam colunas novas ficam registradas aqui como divergências, sem ajuste no esquema (Q-002-07).

## Technical Context

**Language/Version**: XMLDB (`db/install.xml`) sobre Moodle 5.2 [REQ §Contratos, §Ambiente]. PHP conforme spec-001.

**Primary Dependencies**: Moodle XMLDB/DDL, DML e upgrade API [REQ §Contratos, §Conformidade].

**Storage**: 21 tabelas com prefixo `local_assistente_` [REQ §Objetivo, §Regras]. SGBD em Q-001-02.

**Testing**: testes de persistência/XMLDB [REQ spec-023 §Contratos]. O runner está NEEDS CLARIFICATION (Q-023-01) (04/11/2026: resolvido: DEC-48: PHPUnit e Behat do Moodle; executados em T013 [x] (CL-22)).

**Target Platform**: GitHub Codespaces + Moodle 5.2 [REQ §Ambiente].

**Project Type**: plugin local Moodle (`local_assistente`), camada de esquema.

**Performance Goals**: N/A, não especificado. Os índices são exatamente os do inventário.

**Constraints**: PK `id` sequencial; FKs para tabelas Moodle quando definidas; índices únicos exatamente onde definidos; `number` com `LENGTH`/`DECIMALS` separados; nenhum campo inventado [REQ §Regras].

**Scale/Scope**: 21 tabelas [REQ §Objetivo].

## Constitution Check

| Princípio | Pré-design | Pós-design | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | Esquema = inventário. As divergências estão em Q-002-07, sem alterar campos. |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | A validade do XMLDB no Moodle 5.2 não foi testada. O inventário foi conferido contra `install_auditado.xml` na auditoria §7.2 do registro (Q-002-01 resolvido); a validade do XMLDB no 5.2 continua sem teste. *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, XMLDB validado no Moodle 5.2 em CL-07 (Fechado (VT) em 02/11/2026, com ressalvas; T020 [x]) e CL-37 (Fechado (VT) em 28/10/2026, com limitações residuais))* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade/histórico | BLOQUEADO | PASSA | Q-002-05 foi resolvida por DEC-29 (`current_version` como referência única; `is_current` removido). O comportamento de FKs para fontes removidas continua indefinido (Q-002-06). *(26/10/2026: Pós passa a PASSA — Q-002-06 resolvida por DEC-42 D3: referências preservadas, remoção derivada na leitura, sem campo novo; exceções de DEC-42 D1/D2; a premissa de que as chaves `foreign` não são impostas pelo banco fica em CL-37. Pós anterior: "BLOQUEADO".)* *(Alterado em 25/10/2026; observação original: "A unicidade de `is_current` não é garantida pelos índices (não únicos) (Q-002-05). O comportamento de FKs para fontes removidas está indefinido (Q-002-06).")* |
| §8 Idempotência | N/A | N/A | Tratado em spec-006/007. |
| §9 Testabilidade | PASSA | PASSA | Ver [quickstart.md](quickstart.md). |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | Qualquer mudança exige XMLDB + upgrade [REQ §Regras]; antes da primeira instalação, a mudança aprovada é feita diretamente no `install.xml` (DEC-29). A alteração de 25/10/2026 foi aprovada pelo responsável. *(Alterado em 25/10/2026; observação original: "Qualquer mudança exige XMLDB + upgrade [REQ §Regras].")* |
| §12 Plataforma e arquitetura | — | PASSA | `install.xml` é a fonte primária do esquema e as mudanças seguem XMLDB (DEC-29). *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (XMLDB não validado no Moodle 5.2) e §7 (Q-002-06). *(26/10/2026: só §5, validação técnica em CL-07 e CL-37, que não bloqueia o planejamento; §7 passa com DEC-42; questões bloqueadoras: nenhuma.)* Q-002-01 e Q-002-05 resolvidos. O esquema foi alterado em 25/10/2026 por DEC-29, com aprovação do responsável. *(Alterado em 25/10/2026; texto original: "BLOQUEADO justificado (Q-002-05, 06; Q-002-01 resolvido, ver abaixo). Não houve alteração de esquema nesta fase.")* *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa (CL-07 e CL-37 Fechados); nenhum princípio BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/002-esquema-xmldb/
├── spec.md
├── plan.md
├── research.md
├── data-model.md    # mapa das 21 tabelas e relações
└── quickstart.md
```

`contracts/` não foi gerado: o esquema é interno. O modelo está em [data-model.md](data-model.md).

### Source Code (repository root)

```text
assistente/                 # → /var/www/html/public/local/assistente (spec-001; DEC-27, alterado em 25/10/2026; original: /var/www/html/local/assistente)
└── db/
    ├── install.xml         # fonte primária (spec-002 §Contratos)
    └── [mecanismo de upgrade]   # exigido para mudanças futuras (§Regras); arquivos NEEDS CLARIFICATION (Q-002-02) (04/11/2026: resolvido: Q-002-02 resolvida em 26/10/2026 (DEC-29); `assistente/version.php` e `assistente/db/upgrade.php` existem (T019 [x]))
```

**Structure Decision**: somente `db/install.xml` é citado pela spec. A versão do plugin e o arquivo de upgrade estão em Q-002-02.

## Dependências entre specs

- Upstream: spec-001 [REQ §Dependências].
- Downstream: todas as specs que persistem dados (003, 005–019).

## Fases de planejamento & gates de validação

1. ~~Conferência inventário × XML auditado~~ (Q-002-01): concluída na auditoria §7.2 do registro. Resta testar a validade do XMLDB no Moodle 5.2.
2. **Transcrição** das 21 tabelas para `db/install.xml`, sem acréscimos [REQ §Regras].
3. **Gate G-002**: instalação do plugin cria exatamente as 21 tabelas, com campos, chaves e índices idênticos ao inventário (cenários em [quickstart.md](quickstart.md)) [ESCOLHA: gate].

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-002-01 | Resolvido | *(25/10/2026: depois da DEC-29, 21 tabelas e 205 itens, idênticos por nome entre a spec-002 e o XML.)* Inventário × XML: idêntico (21 tabelas, 197 itens; NAME, TYPE, LENGTH, DECIMALS, NOTNULL, SEQUENCE, chaves e índices) em `install_auditado.xml` no commit `HEAD`, segundo a auditoria §7.2 do registro. Não comparados: `COMMENT`; o XML não tem `DEFAULT`. Validade no Moodle 5.2 não testada. Pendência original: O PROMPT-3 proíbe ler `install_auditado.xml`. Por isso a igualdade inventário × XML não foi verificada. A spec cita `/mnt/data/install_auditado.xml`, caminho que não existe no repositório. | §Contratos; registro §7.2 |
| Q-002-02 | Resolvido | Versão do plugin e artefatos do "mecanismo de upgrade correspondente" não especificados. **Parcial (25/10/2026):** até a primeira instalação não há upgrade, e as mudanças vão direto no `install.xml` (DEC-29). A versão do plugin e o `db/upgrade.php` posteriores continuam abertos. **Resolvido (26/10/2026):** segue a convenção do Moodle — `version.php` com a versão do plugin (formato `AAAAMMDDXX`, valor definido nas tasks) e, depois da primeira instalação, toda mudança estrutural em `db/upgrade.php` com savepoint de upgrade (DEC-29). Validação em CL-07. *(Tipo original: "Aberto"; depois "Parcial".)* | §Regras; DEC-29 |
| Q-002-03 | Resolvida *(tipo original: "Parcial")* | *(25/10/2026: `processing.status` e `processing_attempt.status` foram definidos por DEC-32 D4; os demais continuam abertos.)* Domínios de valores dos campos `char`: `processing.status`, `processing_attempt.status`, `material.material_type`, `material_version.summary_status`, `analysis.analysis_type`. Somente `event.status` é definido (spec-006). **Parcial (26/10/2026):** definidos `event.status` e `processing.status` (`pending`, `processing`, `processed`, `failure`; spec-006, DEC-32); `processing_attempt.status` (`processing`, `processed`, `failure`; DEC-32 D4); `analysis.analysis_type` (`quiz_initial`, `quiz_results`, `grade_performance`, `forum_analysis`, `frequency`, `criteria_check`, `section_synthesis`; planos 010–016, todos com até 20 caracteres); `material.material_type` (`pdf`, `text`, `youtube`, conforme os tipos de DEC-33 D2). **Continua aberto:** `material_version.summary_status`, que pertence a Q-008-05 (plano 008 aprovado, definido nas tasks). *(Tipo original: "Aberto".)* *(04/11/2026, etapa 5a: Resolvida; evidência: `material_version.summary_status` definido por DEC-50 (`pending`, `completed`, `failed`; `assistente/classes/domain/material/summary_status.php`), e os demais domínios já definidos nesta linha (DEC-32 D4, DEC-33 D2, planos 010–016); `assistente/classes/infrastructure/persistence/schema_domains.php`.)* | §Inventário; spec-006; DEC-32; DEC-33; planos 010–016 |
| Q-002-04 | Resolvida *(tipo original: "Parcial")* | Semântica dos campos `int(1)` (`enabled`, `availability`, `is_current`, `participated`, `*_status`, `forum_criterion`) não declarada. *(25/10/2026: `is_current` foi removido por DEC-29.)* **Parcial (26/10/2026):** `config.enabled` 0/1 (DEC-45 D3); `frequency_activity.participated` 0/1 (DEC-39 D1); `criteria.forum_criterion` 0 = desativado, 1 = participação obrigatória (DEC-40 D3); `criteria_result.*_status` 1 = atende, 0 = abaixo, 2 = `no_data` (DEC-40). **Continua aberto:** `material.availability` × `material_version.availability`, que pertence a Q-008-07 (plano 008 aprovado, definido nas tasks; a disponibilidade usada no contexto é a avaliada no snapshot, DEC-26). *(Tipo original: "Aberto".)* *(04/11/2026, etapa 5a: Resolvida; evidência: `material.availability` e `material_version.availability` definidos por DEC-51 (`available`, `unavailable`, `pending`; `char(20)`; `assistente/classes/domain/material/material_availability.php`); os demais campos já definidos nesta linha (DEC-39 D1, DEC-40, DEC-45 D3).)* | §Inventário; DEC-39; DEC-40; DEC-45; Q-008-07 | *(26/10/2026: `availability` passou a `char(20)` por DEC-51; deixa de ser `int(1)`.)*
| Q-002-05 | Resolvido | Os índices `material_id-is_current` e `report_id-is_current` não são únicos. A garantia de uma única versão corrente fica para a aplicação/transação (spec-017), que não é especificada. **Resolvido (25/10/2026, DEC-29):** `material` e `report` passaram a ter a referência única `current_version` (chave para a versão), e `is_current` e os índices foram removidos. A atualização sob Lock API e transação está na spec-008 e na spec-019. A validação continua em CL-07. *(Tipo original: "Risco".)* | §Inventário; DEC-20, DEC-23, DEC-29 |
| Q-002-06 | Resolvido | FKs para `course`, `course_sections`, `course_modules`, `question`, `forum_discussions` e `user` versus "remoção de fonte não apaga histórico" (spec-017): comportamento indefinido. **Resolvido por DEC-42 (26/10/2026):** remoção de fonte dentro do curso preserva o histórico e as referências; "fonte removida" é derivada na leitura, sem campo novo (D3); exclusão de dados pessoais pela Privacy API (D1) e exclusão do curso (D2) são as únicas remoções. A premissa de que as chaves `foreign` do XMLDB não são impostas pelo banco é suposição a confirmar em CL-37. *(Tipo original: "Aberto".)* | §Inventário; spec-017; DEC-42 |
| Q-002-07 | Resolvido | Outras specs exigem dados sem coluna no inventário: resumo do fórum (spec-013), indicação de fonte removida (spec-021), turma (spec-018), vínculo relacional processing↔material_version (só via `context_json`, spec-008/017), vínculo report↔processing (spec-017). spec-002 proíbe inventar campos. A divergência precisa de decisão fora do Plan. **Resolvido sem alteração de esquema (26/10/2026):** resumo do fórum em `analysis.summary` (`forum_analysis`, plano 013, DEC-38); fonte removida derivada na leitura (DEC-42 D3); turma = grupo do Moodle lido na consulta (DEC-43 D1); versões de material no `processing_context.context_json` (plano 017); relatório ↔ processamento pelos `analysis_id` do manifesto em `snapshot_json` (DEC-44 D1). CL-10 fechado. *(Tipo original: "Contradição".)* | §Regras; spec-013/017/018/021; DEC-38, DEC-42, DEC-43, DEC-44 |
| Q-002-08 | Resolvido | Unidade dos timestamps `int(10)` não declarada. **Resolvido (26/10/2026):** timestamp Unix em segundos, convenção do Moodle para campos `int(10)` de data. *(Tipo original: "Aberto".)* | §Inventário |
| Q-016-08 | Resolvido | *(26/10/2026: resolvida no nível funcional no plano 016 aprovado, DEC-41 D3; validação técnica em CL-16. Tipo anterior: "Aberto".)* `pedagogical_synthesis.section_id` será NOT NULL por decisão conceitual aprovada (opção A, DEC-13). A decisão funcional de 21/10/2026 define o percurso: seção derivada de `cmid` quando a relação for inequívoca (regra da spec-004); caso contrário, `section_id` explícito no contexto do processamento; validação de que a seção existe e pertence ao curso; sem seção válida, a síntese por seção não é criada e a condição fica como pendência de processamento. Falta: a regra de `cmid` → seção (Q-004-07), a definição da origem e da validação do contexto explícito, e os eventos e a política de resultados ausentes (Q-006-07). `analysis.cmid` aceita nulo e `analysis` não tem `section_id`. A alteração do XMLDB e o upgrade continuam fora de escopo. O contrato funcional de resolução de seção da spec-004 está aprovado (Q-004-07); a validação técnica no Moodle 5.2 segue pendente. | spec-016 §Regras; spec-004; DEC-13 |
| R-G1/R-G2 | Resolvido | Constituição em proposta; IDs dos 66 requisitos ausentes. **Resolvido em 25/10/2026:** constituição 1.1.0 ratificada; o §2 aceita a matriz. *(Tipo original: "Risco".)* | constitution.md |
| R-G3 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | Marcadores `citeturn…` não resolvíveis. *(04/11/2026, etapa 5a: Aceito como limitação, por decisão do responsável: as citações não são resolvíveis; os marcadores (`cite` e `turn` com caracteres Unicode de uso privado U+E200, U+E202 e U+E201, que `grep citeturn` não encontra) são mantidos nos 8 `spec.md` e no `research.md` porque o texto original é preservado; as regras dessas frases foram confirmadas por decisões e validações, não pelas citações.)* | §Contratos, §Conformidade |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Nada foi testado no Moodle 5.2. *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

### Decisões conceituais aprovadas (sem autorização de implementação)

- `pedagogical_synthesis.section_id` será NOT NULL (opção A, interpretação literal de DEC-13); não há síntese no nível do curso sem seção nesta modelagem.
- A primeira versão será `1`; o incremento será `max(version) + 1` por `(course_id, section_id)`, protegido pela Lock API (registro §4, P-03).
- Isto não autoriza alterar `install_auditado.xml`, `db/upgrade.php` nem a versão do plugin. Pendentes: regra de resolução da seção (Q-016-08), `input_fingerprint`, mecanismo de upgrade e validação no Moodle 5.2.

## Revisão pós-propagação (25/10/2026)

A spec-002 e o `install_auditado.xml` foram alterados em 25/10/2026 por DEC-29, com aprovação do responsável, para aplicar DEC-07, DEC-14, DEC-19, DEC-20 e DEC-23:

- `material`: `current_version` com chave para `material_version.id`;
- `material_version` e `report_version`: `is_current` e índices removidos;
- `pedagogical_synthesis`: `course_id`, `section_id`, `version`, `input_fingerprint` e índice único `course_id,section_id,version`;
- `report`: datas de agendamento anuláveis, `final_completed_at` e `current_version`.

Q-002-05 resolvida; Q-002-02 parcial. Continuam abertas Q-002-03, 04, 06, 07 e 08. As chaves circulares (material ↔ versão, relatório ↔ versão) e a instalação precisam de validação no Moodle 5.2 (§5, `closure-register.md` CL-07). Nada foi executado. *(26/10/2026: deixou de valer — ver a revisão abaixo.)*

## Revisão do plano 002 (26/10/2026)

Conferência do `install_auditado.xml` depois da DEC-29: 21 tabelas, 127 campos, 60 chaves e 18 índices (205 itens); nenhum `is_current`; `material.current_version` → `material_version.id` e `report.current_version` → `report_version.id`. Nada foi executado no Moodle.

| Item | Decisão aplicada | Efeito no plano | Estado |
|---|---|---|---|
| Chaves para fontes Moodle removidas | DEC-42 D1–D3 | Q-002-06 resolvida; §7 passa | Incorporada; premissa sobre as chaves em CL-37 |
| Dados sem coluna | DEC-38, DEC-42, DEC-43, DEC-44; plano 017 | Q-002-07 resolvida, sem campo novo | Incorporada |
| Domínios `char` | DEC-32, DEC-33; planos 010–016 | Q-002-03 parcial (falta `summary_status`, Q-008-05) | Incorporada |
| Semântica `int(1)` | DEC-39, DEC-40, DEC-45 | Q-002-04: `availability` deixou de ser `int(1)` e passou a `char(20)` por DEC-51 (26/10/2026) | Incorporada |
| Versão e upgrade | DEC-29; convenção do Moodle | Q-002-02 resolvida | Incorporada; validação em CL-07 |
| Timestamps | convenção do Moodle | Q-002-08 resolvida | Incorporada |
| Resolução de seção da síntese | DEC-41 D3 | Q-016-08 resolvida no nível funcional | Incorporada; validação em CL-16 |

O `spec.md` e o `install_auditado.xml` não foram alterados nesta revisão. Continuam pendentes, sem bloquear o planejamento: `summary_status` e `availability` (Q-008-05, Q-008-07, nas tasks do plano 008) e a validação técnica em CL-07 (XMLDB, chaves circulares, transação, Lock API) e CL-37 (chaves não impostas pelo banco). *(04/11/2026, revisão dos planos: Q-008-05 (DEC-50) e Q-008-07 (DEC-51) resolvidas; CL-07 Fechado (VT) em 02/11/2026; CL-37 Fechado (VT) em 28/10/2026.)*

## Complexity Tracking

Nenhuma violação justificável. Os bloqueios estão acima.
