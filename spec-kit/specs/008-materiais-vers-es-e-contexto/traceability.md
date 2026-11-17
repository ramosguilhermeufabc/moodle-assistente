# Rastreabilidade — spec-008

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-032 | Processar materiais PDF, texto e YouTube | spec-008; decisão SDD aprovada | Confirmado (DEC-33 D2) |
| AT-033 | Gerar resumos de materiais de forma assíncrona | spec-008; Etapa 3 §3.2 | Confirmado |
| AT-034 | Nova disponibilidade de material gera processamento assíncrono | spec-008 | Confirmado. *(31/10/2026: leitura aprovada, sem alterar o enunciado do AT nem as tarefas T063 e T065: a inclusão de um novo material elegível inicia o processamento assíncrono; uma alteração só da disponibilidade atualiza o estado de disponibilidade, sem nova versão e sem extração ou resumo (DEC-51, T058). Texto da spec-008 ajustado nesse sentido.)* *(03/11/2026: "elegível", nessa leitura, designa o material funcional cujo conteúdo pode ser processado, não a elegibilidade para o contexto; ver a nota correspondente em `spec.md` e a seção "Decisões A e B" abaixo.)* |
| AT-035 | Cada alteração de material gera nova versão preservando a anterior | spec-008; spec-017 | Confirmado (DEC-33 D3) |
| AT-036 | Versão nova torna-se o contexto corrente | spec-008 | Confirmado. *(01/11/2026: lida com DEC-61 e DEC-62, sem alterar o enunciado: a versão nova só vira corrente quando está `completed` e tem número maior que o da corrente; até lá a anterior continua corrente. Testes: Q15 (T020, CL-07) e Q16 a Q18 (T144), pendentes, nada executado.)* *(Atualização de 02/11/2026: a afirmação anterior, "pendentes, nada executado", era o registro de 01/11/2026 e está preservada como histórico. Q16, Q17 e Q18 foram executados e aprovados na T144, com os limites de cobertura documentados na seção "Evidências de Q16, Q17 e Q18" de `specs/008-materiais-vers-es-e-contexto/quickstart.md`. Esta atualização não altera o enunciado, a classificação nem a rastreabilidade normativa de AT-036 ou dos demais identificadores, e não trata de Q15.)* |
| AT-037 | Material indisponível não fornece contexto futuro | spec-008 | Confirmado |
| AT-038 | Contexto de atividade/questionário/fórum usa materiais da mesma seção | spec-008; spec-023 | Confirmado |
| AT-039 | Criar snapshot imutável do contexto por processamento | spec-008; spec-017 | Confirmado |
| AT-040 | Material processável se visível a pelo menos um aluno, mas contexto geral só se visível a todos os alunos relevantes | Decisão SDD aprovada em conversa; confirmada em 22/10/2026 como DEC-26; spec-008 | Confirmado por decisão aprovada (DEC-26) |
| AT-041 | Não reprocessar análises históricas retroativamente após mudança de material | spec-008 | Confirmado |
| AT-042 | Rastrear todos os materiais e versões efetivamente usados | spec-008; spec-017 | Confirmado |
| AT-063 | Preservar estados anteriores e histórico quando fonte é atualizada/removida | spec-008; spec-017; Constituição §7 *(1.1.0; referência original: §10 da constituição do pacote modular, ajustada em 25/10/2026)* | Confirmado |

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-04 | Histórico e rastreabilidade |
| DEC-05 | Histórico e rastreabilidade; Materiais e contexto |
| DEC-08 | Histórico e rastreabilidade |
| DEC-12 | Histórico e rastreabilidade; Materiais e contexto |
| DEC-15 | Materiais e contexto |
| DEC-19 | Histórico e rastreabilidade |
| DEC-20 | Histórico e rastreabilidade; Materiais e contexto |
| DEC-21 | Histórico e rastreabilidade |
| DEC-22 | Histórico e rastreabilidade |
| DEC-26 | Materiais e contexto (confirmada em 22/10/2026) |
| DEC-33 | Materiais: tipos, alteração e entrega 008 ↔ 009 (incluída em 25/10/2026; correção de omissão na rastreabilidade, sem mudança no plano aprovado) |
| DEC-61 | Materiais e contexto: etapas da persistência da versão (incluída em 01/11/2026; explicita DEC-15, DEC-20 e DEC-50; AT-035, AT-036; quickstart Q15; validação em CL-07/T020) |
| DEC-62 | Materiais e contexto: monotonicidade de `current_version` (incluída em 01/11/2026; AT-036; quickstart Q16 a Q18; T144; a CL-07 não depende dela) |

## Decisões A e B *(incluída em 03/11/2026, auditoria documental de propagação; sem decisão nova)*

Mapa das duas decisões congeladas para as regras já registradas. Nenhum texto normativo foi alterado por esta seção; os nomes de testes são os que as tarefas T058 a T066 e o `context_snapshot_builder_test.php` registram, e **não foram reexecutados** nesta auditoria.

| Regra | Decisão e requisito | Texto normativo | Tarefas | Cenários | Testes existentes (nomes) |
|---|---|---|---|---|---|
| **A.** Só PDF, TEXT e YouTube são material funcional | DEC-33 D2; constituição §6; AT-032 | `spec.md` (tipos funcionais) | T059, T065, T066 | Q11 | `material_classifier_test`, `material_observer_test`, `cl02_material_events_test` |
| **A.** Book e Folder, rótulos e outros arquivos estão fora | DEC-33 D2; constituição §6; CL-30 | `spec.md` (tipos funcionais); `plan.md` (Scale/Scope) | T059, T065 | Q11 | `material_classifier_test`, `material_observer_test` |
| **B.** Disponível a pelo menos um estudante: pode ser detectado, processado e resumido | DEC-26 (1); AT-033, AT-034 (leitura de 31/10/2026), AT-040 | `spec.md` (regras de disponibilidade) | T063 | Q1, Q7, Q12, Q13 | `material_service_test` |
| **B.** Contexto da análise exige disponibilidade a todos os estudantes relevantes; parcial fica fora | DEC-26 (2), (3), (4); AT-037, AT-038, AT-040 | `spec.md`; `data-model.md` (elegibilidade); `contracts/material-context-snapshot.md` | T064 | Q3, Q4, Q7, Q19 | `test_unavailable_material_is_left_out`, `test_partially_available_material_is_not_universal`, `test_availability_is_evaluated_for_the_students_of_the_analysis`, `test_snapshot_lists_materials_versions_and_language`, `test_no_students_means_nothing_eligible` |
| **B.** A disponibilidade é avaliada no snapshot da análise e registrada nele | DEC-26 (5), (6); DEC-51; AT-039 | `spec.md`; `contracts/material-context-snapshot.md` | T058, T064 | Q5, Q8, Q19 | `test_persisted_availability_does_not_decide_nor_change`, `test_saved_snapshot_is_not_replaced_by_later_changes`, `test_material_availability_changes_without_altering_version_history` |
| **B.** Alteração só da disponibilidade não cria versão nem dispara extração e resumo | DEC-33 D3; DEC-51; AT-034 (leitura de 31/10/2026) | `spec.md` (regras de disponibilidade e de alteração) | T058, T063, T065 | Q8, Q12 | `material_observer_test` |

**Pendências desta auditoria** (sem decisão nova; registradas em `closure-register.md`, CL-53): as decisões técnicas **DT-11** e **DT-28**, o glossário e o componente **`context_builder`** não existem na árvore desta branch; o componente que monta o contexto aqui é o `context_snapshot_builder` (T064). Não se afirma que sejam o mesmo item.

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md). Nada foi executado.
