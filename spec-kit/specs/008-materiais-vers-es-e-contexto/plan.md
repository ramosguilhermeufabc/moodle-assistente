# Implementation Plan: spec-008 — Materiais, Versões e Contexto

**Branch**: `feature/sdd-ajustado` (feature dir `008-materiais-vers-es-e-contexto`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/008-materiais-vers-es-e-contexto/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026, com a validação técnica do §5 (CL-02) como pendência que não bloqueia o planejamento. A aprovação isolada não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Processar materiais PDF, texto e YouTube, gerar resumos de forma assíncrona e versionar o material e o contexto por seção [REQ spec-008 §Objetivo]. Regras [REQ §Regras]:
- o material só é elegível quando disponível aos estudantes;
- nova disponibilidade gera processamento assíncrono;
- qualquer alteração gera nova versão, a anterior permanece e a nova vira contexto corrente;
- material indisponível não fornece contexto futuro;
- somente materiais da mesma seção contextualizam atividade, questionário, fórum ou síntese pedagógica;
- não há reprocessamento retroativo;
- todos os materiais e versões usados são rastreáveis;
- detecção, processamento bem-sucedido e elegibilidade para análise são estados distintos; a detecção não torna a versão elegível (DEC-05);
- material disponível a pelo menos um estudante pode ser processado e resumido, mas só compõe o contexto de uma análise se estiver disponível a todos os estudantes relevantes dela; a disponibilidade é avaliada e registrada ao montar o snapshot (DEC-26);
- no máximo uma versão corrente por material, com troca atômica: a anterior só deixa de ser corrente quando a nova estiver registrada corretamente, e uma falha não apaga o histórico (DEC-12, DEC-15). A versão corrente é a referência única `current_version` do material, atualizada sob Lock API e em transação, depois de a nova versão estar persistida (DEC-20, DEC-29). *(01/11/2026, DEC-61 e DEC-62: a persistência tem três etapas independentes, cada uma com lock e transação própria (criar a versão `pending`; gravar o resumo e marcar `completed`; trocar o ponteiro), e a chamada à LLM fica entre a primeira e a segunda, fora de qualquer transação. Uma versão `completed` pode permanecer sem ser corrente se a troca falhar. O ponteiro nunca retrocede: só passa a apontar para versão `completed` de número estritamente maior que o da corrente.)*

*(Itens incluídos em 25/10/2026 conforme a spec-008 propagada em 22/10/2026. A regra "a nova vira contexto corrente" deve ser lida com eles: a nova versão só é elegível depois de processada com sucesso e disponível.)*

O contexto é um snapshot imutável por processing [REQ §Contratos]. Os arquivos são acessados pela File API [REQ §Conformidade].

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: Moodle File API [REQ §Conformidade]; readers de spec-004; fila e tasks (006/007); LLM para resumo (spec-009 §Regras).

**Storage**: `local_assistente_material` (único `course_id,cmid`), `local_assistente_material_version` (único `material_id,version`), `local_assistente_processing_context` (1:1 processing) [REQ §Contratos; spec-002].

**Testing**: versionamento e isolamento por seção [REQ spec-023 §Regras]. Runner em Q-023-01.

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, ingestão e contexto.

**Performance Goals**: N/A.

**Constraints**: snapshot imutável; versões preservadas; isolamento por seção [REQ].

**Scale/Scope**: tipos PDF, TEXT e YouTube [REQ §Objetivo; constituição §6]. Book e Folder fora do escopo (constituição §6).

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | File API, disponibilidade e eventos não verificados. *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, eventos de material em CL-02 (FECHADA em 02/11/2026; T066 [x]), disponibilidade por usuário em CL-36 (Fechado em 03/11/2026) e DEC-51/CL-48, File API em `assistente/classes/application/material/pdf_extractor.php` (T135 [x], PHPUnit OK no Moodle 5.2.4))* |
| §6 Escopo de materiais | BLOQUEADO | PASSA | A spec-008 passou a definir o caso parcial (DEC-26): Q-008-01 resolvida no nível funcional. A API de disponibilidade a validar no Moodle 5.2 fica no §5 (Q-004-01). *(Alterado em 25/10/2026; observação original: "A constituição exige que disponibilidade parcial não seja tratada como elegibilidade para a turma inteira; spec-008 não define o caso parcial (Q-008-01). Processamento × disponibilidade × elegibilidade estão separados no plano.")* |
| §7 Integridade/histórico | BLOQUEADO | PASSA | A spec-008 define que a versão anterior só deixa de ser corrente quando a nova estiver registrada corretamente, que a falha não apaga o histórico e que só versão processada com sucesso é elegível (DEC-05, DEC-15). Os valores de `summary_status` continuam em Q-008-05. *(Alterado em 25/10/2026; observação original: "Comportamento quando o resumo da nova versão falha (a nova versão vira corrente?) indefinido (Q-008-05).")* |
| §8 Idempotência | BLOQUEADO | PASSA | A spec define a referência única `current_version`, atualizada sob Lock API por material e em transação (DEC-20, DEC-29); Q-002-05 foi resolvida. A validação da concorrência fica no §5 (CL-07). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Corrida entre duas alterações do mesmo material (`is_current` sem único) (Q-002-05).")* |
| §9 Testabilidade | PASSA | PASSA | [quickstart.md](quickstart.md). |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | A ordem de entrega 008 ↔ 009 foi decidida por DEC-33 D1 (porta da 009 primeiro, depois a 008, e o adapter real por último; CL-13 fechado). Texto anterior: "BLOQUEADO \| A ordem de entrega 008 ↔ 009 (Q-008-10, `closure-register.md` CL-13) dependem de decisão humana." *(25/10/2026: a alteração de esquema de DEC-20 foi decidida por DEC-29.)* *(Alterado em 25/10/2026; observação original: "Bloqueadores sobre significado ou lacuna da spec dependem de decisão humana (ver Questões Bloqueador).")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | A LLM é usada só no resumo de materiais, que é interpretação linguística [REQ spec-009 §Regras]. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica; §11 passou a PASSA por DEC-33 em 25/10/2026) *(alterado em 25/10/2026; original: "§2, §5, §8, §11")*. Questões Bloqueador: nenhuma de significado; Q-008-01 resolvida no nível funcional, com validação técnica em Q-004-01. *(Alterado em 25/10/2026; texto original: "princípios BLOQUEADO: §2, §5, §6, §7, §8, §11. Questões Bloqueador: Q-008-01.")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa (CL-02, CL-36 Fechados; T135 [x]); nenhum princípio BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/008-materiais-vers-es-e-contexto/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/material-context-snapshot.md
```

### Source Code (repository root)

```text
assistente/
└── [serviços de material, versão e snapshot de contexto]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: serviços em `assistente/classes/application/material/` e `assistente/classes/application/context/context_snapshot_builder.php`)
```

## Dependências entre specs

- Upstream: spec-004, spec-007 [REQ §Dependências]. Dependência técnica não declarada na spec-008: spec-009 (os resumos de material usam a LLM, spec-009 §Regras). Ver Q-008-10.
- Downstream: 009, 010, 013, 016, 017.

## Fases de planejamento & gates

1. Aplicar a elegibilidade e a disponibilidade definidas na spec, inclusive o caso parcial (DEC-26), e validar a API de disponibilidade no Moodle 5.2 (Q-004-01). *(Alterado em 25/10/2026; texto original: "Definir elegibilidade e disponibilidade, inclusive o caso parcial (bloqueante, Q-008-01).")*
2. Aplicar o mapeamento de tipos da DEC-33 D2 e definir a extração de conteúdo de PDF, texto e YouTube (Q-008-03, vinculada a CL-19). *(Alterado em 25/10/2026; texto original: "Definir o mapeamento de tipos e a extração de conteúdo PDF/TEXT/YouTube (Q-008-02/03).")*
3. Aplicar o versionamento: "alteração" conforme DEC-33 D3 e troca atômica da referência `current_version` sob lock e transação (DEC-20, DEC-29; Q-003-02). *(Alterado em 25/10/2026; texto original: "Definir o versionamento: o que conta como "alteração" e a troca atômica de `is_current` (Q-008-04, Q-003-02)."; `is_current` foi removido por DEC-29.)* *(01/11/2026: a troca é a terceira etapa da DEC-61 e obedece à monotonicidade da DEC-62; implementação da DEC-62 em T144.)*
4. Definir o snapshot: formato do `context_json` com as versões usadas (Q-008-06).
5. **Gate G-008**: cenários do [quickstart.md](quickstart.md).

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-008-01 | Resolvido | Definição de "disponível aos estudantes" (spec-008 §Regras). O caso de disponibilidade para só parte da turma vem da regra de governança da constituição §6 e não do texto da spec-008, que não o define. **Resolvido no nível funcional (25/10/2026):** a spec-008 agora define estudantes relevantes, "disponível" (módulo visível e restrições de acesso atendidas), o caso parcial e o momento de avaliação (DEC-26). A API exata continua a validar no Moodle 5.2 (Q-004-01, `closure-register.md` CL-02). *(Tipo original: "Bloqueador".)* | spec-008 §Regras; DEC-26 |
| Q-008-02 | Resolvido | Quais recursos Moodle correspondem a PDF, texto e YouTube; valores de `material_type`. **Resolvido por DEC-33 D2 (25/10/2026):** `mod_resource` com PDF, `mod_page` e `mod_url` do YouTube; os valores literais de `material_type` ficam para o desenho. *(Tipo original: "Aberto".)* | §Objetivo; spec-002; DEC-33 |
| Q-008-03 | **Resolvida em 03/11/2026 por DEC-67** *(texto anterior preservado: "Aberto")* | Como extrair o conteúdo de PDF e de YouTube para resumo (YouTube não é arquivo da File API). *(25/10/2026: depende do fornecedor e das capacidades da LLM (`closure-register.md` CL-19) e de validação técnica (CL-02); não bloqueia o planejamento.)* *(31/10/2026, DEC-60: o fornecedor é o OpenRouter e o modelo é configuração administrativa; as capacidades de PDF e YouTube dependem do modelo configurado e não foram decididas. A Q-008-03 continua aberta; método de extração a decidir antes de T135.)* *(03/11/2026: Q-008-03 decidida pela DEC-67: PDF com smalot/pdfparser pela File API, sem OCR; YouTube com yt-dlp, sem API nem chave; ver T135.)* | §Objetivo, §Conformidade |
| Q-008-04 | Resolvido | O que é "qualquer alteração" (conteúdo, nome, disponibilidade, seção?). **Resolvido por DEC-33 D3 (25/10/2026):** só a mudança de conteúdo processável (arquivo, texto da página, URL) cria versão; nome, visibilidade, restrições e seção não criam. *(Tipo original: "Aberto".)* | §Regras; DEC-33 |
| Q-008-05 | Resolvido | Valores de `summary_status`; uma versão com resumo falho vira contexto corrente? **Parcial (25/10/2026):** a segunda parte foi resolvida pela spec, porque só a versão processada com sucesso é elegível e a falha não invalida o histórico (DEC-05, DEC-15). Os valores de `summary_status` continuam abertos. | §Regras; constituição §7; DEC-05, DEC-15 *(26/10/2026: DEC-50 define `pending`, `completed` e `failed`.)* |
| Q-008-06 | Resolvido | Estrutura do `context_json` e como registra os `material_version` usados (sem FK relacional). **Resolvido (31/10/2026, a partir das evidências):** versões usadas registradas só em `context_json`, sem FK (Q-017-02, resolvida em 26/10/2026); formato 1 implementado e documentado no docblock de `context_snapshot_builder` (T064): `materials` com `version_id`, `version` e `availability` (`coverage`, `available_students`, `total_students`), `eligible`, `reason`, `summary`, e `used_versions`; descrito em `contracts/material-context-snapshot.md`; testes de `context_snapshot_builder_test` (lista de materiais e versões, Q6), executados em 31/10/2026 na suíte completa. *(Tipo original: "Aberto".)* | §Contratos; spec-017 |
| Q-008-07 | Resolvido | Semântica de `availability` em `material` × `material_version`. *(26/10/2026: DEC-51 define os valores `available`, `unavailable` e `pending` e passa os campos a `char(20)`; o complemento de DEC-51 define o papel de cada campo: `material.availability` é a mais recente conhecida e atualizável, `material_version.availability` é histórica e imutável, e o snapshot avalia por estudante e contexto sem herdar os valores persistidos; o fluxo de reavaliação de `pending` continua aberto.)* **Atualização de 31/10/2026 — estado anterior superado:** a pendência descrita nesta nota foi resolvida pela complementação da DEC-51 e pela implementação da T058 (commit `6384411`). O fluxo de reavaliação e as transições de `pending` para `available` ou `unavailable` foram especificados, implementados e testados. Consultar o status atualizado de Q-008-07 nesta seção. *(31/10/2026, T058: fluxo especificado e implementado em `material_service` — a cada `material_changed` processado o estado atual do material é reavaliado com a regra por usuário do snapshot (pelo menos um estudante do curso = `available`; nenhum = `unavailable`; sem estudantes ou com falha, o valor permanece); a versão registra o valor observado na criação; nenhuma tabela, observer ou task nova. Limite aceito: mudanças sem evento do Moodle só são vistas no próximo evento. Fluxo ratificado em 31/10/2026 no complemento de DEC-51.)* **RESOLVIDO em 31/10/2026 pela DEC-51 e sua ratificação complementar, implementadas na T058 (commit 6384411). A disponibilidade atual do material é atualizável e representa o último estado conhecido; a disponibilidade da versão é histórica e imutável; o snapshot avalia a disponibilidade por estudante e contexto. O fluxo de reavaliação e as transições de `pending` para `available` ou `unavailable` foram especificados, implementados e testados.** **Ressalva:** `material.availability` é o último estado conhecido e atualizável, não uma garantia de disponibilidade em tempo real; `material_version.availability` é o estado histórico registrado na criação da versão, imutável; o snapshot é a avaliação independente por estudante e contexto e é a referência para decidir o que entra no contexto de análise. Mudanças que não geram `material_changed` podem deixar o indicador persistido desatualizado, o que não invalida a avaliação do snapshot. *(Tipo anterior: "Parcial".)* | spec-002 |
| Q-008-08 | Resolvido | Material movido para outra seção (`section_id` é NOT NULL). **Resolvido por DEC-33 D3 (25/10/2026):** atualiza `material.section_id` sem criar versão; o contexto usa a seção vigente no snapshot. *(Tipo original: "Aberto".)* | §Regras; DEC-33 |
| Q-008-09 | Resolvido | A perda de disponibilidade gera evento? Como é detectada? **Resolvido pela spec (DEC-26, 25/10/2026):** a disponibilidade é avaliada a cada montagem de snapshot, então um material que ficou indisponível sai do contexto sem precisar de evento. *(Tipo original: "Aberto".)* | §Regras; Q-006-07; DEC-26 |
| Q-008-10 | Resolvido | Dependência circular: a spec-008 gera resumos de material por LLM (spec-009 §Regras) e a spec-009 declara depender da spec-008 ("Upstream: spec-007, spec-008"). A spec-008 não declara depender da spec-009. Ordem de entrega e fronteira entre as duas indefinidas. **Resolvido por DEC-33 D1 (25/10/2026):** porta `evaluate()` da 009 com adapter simulado, depois a 008 e por fim o adapter real (CL-19). *(Tipo original: "Contradição".)* | spec-008 §Objetivo; spec-009 §Dependências, §Regras |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |
| R-G3 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | Marcador `citeturn…` não resolvível. *(04/11/2026, etapa 5a: Aceito como limitação, por decisão do responsável: as citações não são resolvíveis; os marcadores (`cite` e `turn` com caracteres Unicode de uso privado U+E200, U+E202 e U+E201, que `grep citeturn` não encontra) são mantidos nos 8 `spec.md` e no `research.md` porque o texto original é preservado; as regras dessas frases foram confirmadas por decisões e validações, não pelas citações.)* | §Conformidade |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Detecção, processamento e elegibilidade são estados distintos [REQ spec-008 §Regras] | DEC-05 | Summary; §7 passa; Q-008-05 parcial | Incorporada |
| Disponibilidade e elegibilidade para o contexto; estudantes relevantes; avaliação no snapshot [REQ §Regras] | DEC-26 | Q-008-01 resolvida; §6 passa; Q-008-06 (formato do `context_json`) deve registrar o resultado da disponibilidade | Incorporada; API a validar (Q-004-01) |
| Histórico imutável e no máximo uma versão corrente [REQ §Regras] | DEC-12 | Sem mudança de desenho | Incorporada |
| Troca atômica da versão corrente [REQ §Regras] | DEC-15 | §7 passa | Incorporada |
| Persistência da versão em três etapas independentes, cada uma com lock e transação; LLM fora da transação; versão `completed` pode ficar não corrente se a troca falhar [REQ §Regras; fluxo de `material_service`] | DEC-61 *(explicita DEC-15, DEC-20 e DEC-50; 01/11/2026)* | Sem mudança de desenho: o plano já descrevia a troca "depois de a nova versão estar persistida"; ajustados os textos derivados (T020, quickstart 002 Q7, plano 003). Validação em CL-07 | Incorporada; validação pendente *(04/11/2026, revisão dos planos: CL-07 Fechado (VT) em 02/11/2026.)* |
| O ponteiro `current_version` não retrocede: só aceita versão do material, `completed` e de número estritamente maior que o da corrente [contrato P-02] | DEC-62 *(01/11/2026)* | Regra nova sobre a troca (etapa c); a spec-008 passou a dizê-la expressamente em 01/11/2026 (§Contratos & Interfaces), sem contradizer P-02. Implementação em T144 | Decidida; implementação concluída e testada na T144 em 02/11/2026 *(texto anterior: "implementação pendente"; a revisão do responsável e o conteúdo da DEC-62 não foram alterados)* |
| Referência única `current_version` [REQ §Regras] | DEC-20, DEC-29 | §8 passa; esquema alterado em 25/10/2026; validação técnica em CL-07 *(original: "§8 continua BLOQUEADO: depende da alteração de esquema (`closure-register.md` CL-07, Q-002-05) \| Bloqueada")* | Incorporada; validação pendente |
| Contexto restrito à mesma seção; seção-alvo da síntese [REQ §Regras] | DEC-13 | Já refletido no Summary | Incorporada |

Continuam abertas: Q-008-02, 03, 04, 06, 07, 08, 09 e a contradição Q-008-10 (ordem de entrega 008 ↔ 009, `closure-register.md` CL-13). *(25/10/2026: Q-008-02, 04, 08, 09 e 10 foram resolvidas por DEC-33 e DEC-26. Continuam abertas Q-008-03 (CL-19), Q-008-05, 06 e 07 (desenho para as tasks).)*

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Tipos funcionais `mod_resource` (PDF), `mod_page` e `mod_url` (YouTube); "alteração" = conteúdo processável; seção vigente no snapshot [REQ spec-008 §Regras] | DEC-33 | Q-008-02, 04 e 08 resolvidas; fases 2 e 3 atualizadas; quickstart Q8–Q11 | Incorporada (25/10/2026) |
| Ordem de entrega: porta da 009, depois 008, depois adapter real | DEC-33 D1 | Q-008-10 resolvida; §11 passa; CL-13 fechado | Incorporada (25/10/2026) |

**Referência a DEC-46** *(incluída em 26/10/2026, na revisão do plano 021, com autorização do responsável; alteração somente referencial, sem mudança do conteúdo funcional deste plano aprovado)*: cada processamento que gere texto pela LLM (resumo de material) preserva no contexto da análise o idioma utilizado, que é o idioma forçado do curso ou, na falta dele, o idioma padrão do site; resumos existentes não são regenerados nem traduzidos quando o idioma muda. Ver `decision-register-DEC-01-25.md`, DEC-46.

## Complexity Tracking

Nenhuma violação justificável.
