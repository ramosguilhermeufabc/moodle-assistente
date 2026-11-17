# Implementation Plan: spec-005 — Acesso, Configuração e Critérios

**Branch**: `feature/sdd-ajustado` (feature dir `005-acesso-configura-o-e-crit-rios`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/005-acesso-configura-o-e-crit-rios/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026. A aprovação isolada não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Controlar o acesso por curso (o professor vê somente os cursos que possui, o administrador vê todos) com Access API e contexts no backend, sem confiar só na UI [REQ spec-005 §Regras, §Conformidade]. Persistir a configuração e três critérios separados em `local_assistente_config`/`local_assistente_criteria`: atividades numérico, fórum binário, frequência numérico. Os critérios são definidos na criação/configuração do curso, imutáveis depois e sem reprocessamento retroativo [REQ §Contratos, §Regras]. Os formulários ficam para spec-020.

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: Moodle Access API e contexts de curso [REQ §Contratos, §Conformidade]; persistência via spec-003.

**Storage**: `local_assistente_config` (1:1 curso), `local_assistente_criteria` (1:1 config) [REQ §Contratos; spec-002].

**Testing**: testes de acesso (escopo Professor/Admin) [REQ spec-023 §Regras]. Runner em Q-023-01.

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, autorização e configuração.

**Performance Goals**: N/A.

**Constraints**: autorização verificada no backend [REQ §Conformidade]; critérios imutáveis [REQ §Regras].

**Scale/Scope**: uma configuração por curso.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | Form adiado para spec-020. |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | O critério está definido por DEC-30 (capability `local/assistente:view`, contextos de curso e de sistema, arquétipos padrão). Falta verificar no Moodle 5.2 a definição da capability e dos arquétipos (`closure-register.md` CL-26). *(Alterado em 25/10/2026; observação original: "Capabilities e definição de "possui" (Q-005-01/02).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-26 (Fechado em 03/11/2026; T041 [x]) e CL-40 (Fechado (VT) em 03/11/2026))* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | PASSA | PASSA | Imutabilidade preserva a base histórica. |
| §8 Idempotência | N/A | N/A | — |
| §9 Testabilidade | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Faixas de valores dos critérios indefinidas (Q-005-05/06). *(Reavaliado em 04/11/2026: §9 Testabilidade: PASSA, as faixas foram definidas por DEC-40 D1 e D3 (percentual 0–100 e fórum 0/1; CL-05 e CL-27 Fechados no nível funcional) e implementadas e testadas em T039 [x] (`assistente/classes/domain/criteria/criterion_percentage.php`, `assistente/tests/domain/criteria/criterion_percentage_test.php`, `assistente/tests/infrastructure/persistence/config_repository_test.php`))* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | — |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | PASSA | Professor nos próprios cursos e administrador em todos, com controle no backend [REQ spec-005]. O critério operacional foi definido por DEC-30: capability `local/assistente:view` no contexto do curso (padrão `editingteacher`) e no de sistema (padrão `manager`), verificada no backend. A validação técnica está no §5 (CL-26). *(Reavaliado em 25/10/2026; Pós anterior: "BLOQUEADO", com a observação "O critério Moodle de "cursos que o professor possui" e as capabilities estão indefinidos (Q-005-01).")* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5, §9. Questões Bloqueador: nenhuma; Q-005-01 resolvida por DEC-30. *(Alterado em 25/10/2026; texto anterior: "princípios BLOQUEADO: §5, §9, §14. Questões Bloqueador: Q-005-01.")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa (CL-26 e CL-40 Fechados) e §9 Testabilidade passa (DEC-40, T039 [x]); nenhum princípio BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/005-acesso-configura-o-e-crit-rios/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/access-and-config.md
```

### Source Code (repository root)

```text
assistente/
└── [classes: serviço de autorização e de configuração]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: classes em `assistente/classes/application/access_service.php` e `assistente/classes/application/course_config_service.php`)
    [definição de capabilities: local/assistente:view]  # DEC-30 (original: NEEDS CLARIFICATION, Q-005-01) (04/11/2026: resolvido: DEC-30; `assistente/db/access.php` existe (T037 [x]))
```

## Dependências entre specs

- Upstream: spec-003, spec-004 [REQ §Dependências].
- Downstream: 006, 015, 018, 020.

## Fases de planejamento & gates

1. Aplicar o modelo de autorização da DEC-30 (capability `local/assistente:view` em contexto de curso e de sistema) e validá-lo no Moodle 5.2 (CL-26). *(Alterado em 25/10/2026; texto original: "Definir o modelo de autorização: "possui" e administrador (bloqueante, Q-005-01/02).")*
2. Definir o serviço de configuração com regra de imutabilidade ([contracts/access-and-config.md](contracts/access-and-config.md)).
3. **Gate G-005**: testes de escopo Professor/Admin e de imutabilidade passam ([quickstart.md](quickstart.md)).

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-005-01 | Resolvido | Critério Moodle que define "cursos que [o professor] possui" e quais capabilities usar. **Resolvido por DEC-30 (25/10/2026):** o professor possui o curso quando tem a capability `local/assistente:view` no contexto do curso, atribuída por padrão ao arquétipo `editingteacher` (não ao `teacher`); a verificação é no backend, por capability. *(Tipo original: "Bloqueador".)* | §Regras; DEC-30 |
| Q-005-02 | Resolvido | Definição de "Administrador" no Moodle. **Resolvido por DEC-30 (25/10/2026):** quem tem `local/assistente:view` no contexto de sistema, atribuída por padrão ao arquétipo `manager`; o administrador do site passa na verificação pelo modelo do Moodle. *(Tipo original: "Aberto".)* | §Regras; DEC-30 |
| Q-005-03 | Resolvida *(tipo original: "Aberto")* | Fluxo para cursos que já existem quando o assistente é habilitado; semântica de `config.enabled` (pode ser desabilitado?). *(26/10/2026: decidida por DEC-45 D3 na revisão do plano 020 — desabilitado por padrão; habilitar dispara sincronização inicial do estado atual; desabilitar interrompe novos eventos e preserva o histórico. Referência incluída sem reabrir este plano aprovado.)* *(04/11/2026, etapa 5a: Resolvida; evidência: DEC-45 D3 (desabilitado por padrão; sincronização inicial); `assistente/tests/form/course_config_form_test.php`, `assistente/tests/access_test.php` e `assistente/tests/observer/catalog_test.php` (Q4: `test_a_course_not_enabled_creates_no_event`); T122 [x] (CL-40, Fechado em 03/11/2026).)* | §Regras; spec-002 |
| Q-005-04 | Resolvida *(tipo original: "Aberto")* | Alcance da imutabilidade: nem o administrador pode alterar? E correção de erro de digitação? *(26/10/2026: decidida por DEC-45 D2 na revisão do plano 020 — somente leitura para professores; correção por administrador, válida só para novas avaliações, com os limites usados preservados no contexto de cada `criteria_check`. Referência incluída sem reabrir este plano aprovado.)* *(04/11/2026, etapa 5a: Resolvida; evidência: DEC-45 D2 (critérios somente leitura para o professor; correção administrativa só para novas avaliações); `assistente/tests/form/course_config_form_test.php` e `assistente/tests/access_test.php`; T122 [x] (CL-40).)* | §Regras |
| Q-005-05 | Resolvida *(tipo original: "Aberto")* | Unidade/faixa de `activity_criterion` e `frequency_criterion` (`number(10,3)`): escala de nota? percentual? *(25/10/2026: decidida por DEC-40 D1 na revisão do plano 015 — percentuais de 0 a 100; ver `decision-register-DEC-01-25.md`. Referência incluída sem reabrir este plano aprovado.)* *(04/11/2026, etapa 5a: Resolvida; evidência: DEC-40 D1 (percentual de 0 a 100); `assistente/classes/domain/criteria/criteria_evaluator.php` e `assistente/tests/form/course_config_form_test.php` (`test_invalid_percentages_are_rejected`).)* | §Regras; spec-002 |
| Q-005-06 | Resolvida *(tipo original: "Aberto")* | Significado do critério binário de fórum (`forum_criterion` = 1 ⇒ ?). *(25/10/2026: decidida por DEC-40 D3 na revisão do plano 015 — 1 = participação obrigatória em cada fórum elegível vencido; 0 = desativado. Referência incluída sem reabrir este plano aprovado.)* *(04/11/2026, etapa 5a: Resolvida; evidência: DEC-40 D3 (fórum binário: 1 = participação obrigatória, 0 = desativado); `assistente/classes/domain/criteria/criteria_evaluator.php` e `assistente/tests/form/course_config_form_test.php`.)* | §Regras |
| Q-005-07 | Resolvida *(tipo original: "Aberto")* | Comportamento para curso sem configuração/critérios. *(26/10/2026: decidida por DEC-45 D3 na revisão do plano 020 — sem configuração ou desabilitado, nenhum processamento, e as consultas informam "assistente não habilitado"; sem critérios, não há `criteria_result` (DEC-40). Referência incluída sem reabrir este plano aprovado.)* *(04/11/2026, etapa 5a: Resolvida; evidência: DEC-45 D3 (sem configuração ou desabilitado: nenhum processamento); `assistente/tests/form/course_config_form_test.php`, `assistente/tests/access_test.php` e `assistente/tests/observer/catalog_test.php` (Q4); T122 [x] (CL-40).)* | — |
| Q-005-08 | Resolvida *(tipo original: "Aberto")* | *(Incluída em 25/10/2026.)* Autorização para definir os critérios e configurar o assistente no curso (spec-020: "validação de autorização antes de persistir"). A DEC-30 trata só do acesso; a capability ou papel autorizado a gravar a configuração não está definido. *(26/10/2026: decidida por DEC-45 D1 na revisão do plano 020 — capability `local/assistente:manage` no contexto do curso, padrão `editingteacher` e `manager`, verificada no backend. Referência incluída sem reabrir este plano aprovado.)* *(04/11/2026, etapa 5a: Resolvida; evidência: DEC-45 D1 (capability `local/assistente:manage`, verificada no backend); `assistente/tests/access_test.php` e `assistente/tests/form/course_config_form_test.php`; T122 [x] (CL-40, Fechado em 03/11/2026).)* | §Regras; spec-020; DEC-30 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). As regras abaixo agora estão na spec e servem de fonte ao plano. Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Critério de mínimo: atende se valor >= limite, abaixo se < limite. Critério de máximo: atende se valor <= limite, ultrapassa se > limite [REQ spec-005 §Regras] | DEC-11 | A comparação dos critérios numéricos (atividades, frequência) fica definida; a aplicação é da spec-015. A unidade dos critérios continua em Q-005-05 | Incorporada |
| Semântica do critério binário de fórum continua em aberto [REQ spec-005 §Regras] *(04/11/2026, revisão dos planos: Q-005-06 resolvida pela DEC-40 D3; CL-05 Fechado em 25/10/2026.)* | — | Q-005-06 permanece aberta (`closure-register.md` CL-05) | Aberta |

Nenhuma questão deste plano foi resolvida pela propagação. O plano não tinha texto contrário às decisões.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Acesso pela capability `local/assistente:view`: curso (padrão `editingteacher`, sem `teacher`) e sistema (padrão `manager`); backend; por capability [REQ spec-005 §Regras] | DEC-30 | Q-005-01 e Q-005-02 resolvidas; §14 passa; contrato e quickstart atualizados; validação no Moodle 5.2 em CL-26; Q-005-08 incluída | Incorporada (25/10/2026) |

Continuam pendentes e rastreadas (`closure-register.md` CL-27): Q-005-03, 04, 05, 07 e 08; Q-005-06 está em CL-05. *(04/11/2026, revisão dos planos: DEC-45; CL-27 Fechado no nível funcional em 26/10/2026 (validação técnica em CL-40, Fechado em 03/11/2026).)*

## Complexity Tracking

Nenhuma violação justificável.
