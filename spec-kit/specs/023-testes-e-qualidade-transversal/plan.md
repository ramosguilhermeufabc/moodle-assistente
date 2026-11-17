# Implementation Plan: spec-023 — Testes e Qualidade Transversal

**Branch**: `feature/sdd-ajustado` (feature dir `023-testes-e-qualidade-transversal`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/023-testes-e-qualidade-transversal/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, conforme DEC-48. A validação técnica dos runners no ambiente (CL-22) continua pendente e não bloqueia o planejamento; os cenários dos planos 001–004 na matriz permanecem provisórios até a revisão de cada um. Nenhum teste foi executado. A aprovação isolada não autoriza a geração de tasks. *(26/10/2026: os planos 001–004 foram aprovados, e os cenários deles deixaram de ser provisórios na matriz do [quickstart.md](quickstart.md). A geração de tasks foi autorizada com os 23 planos aprovados (`closure-register.md` §8.1); a matriz completa cenário → tarefa está em [tasks.md](tasks.md).)* *(08/11/2026: CL-22 foi fechado em 04/11/2026, validação técnica; a pendência afirmada acima não está mais aberta. Texto anterior preservado, conforme a constituição §11.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). **Nenhum teste foi executado nesta fase.**

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Definir a estratégia de qualidade que valida as specs 001–022 antes de a implementação ser considerada concluída [REQ spec-023 §Objetivo].

- **Tipos de teste**: unitários; integração; persistência/XMLDB; eventos/tasks; API LLM mockada; regras determinísticas; acesso; histórico; UI quando aplicável [REQ §Contratos].
- **Ênfases**: máximo de 3 tentativas totais por processamento, incluindo a inicial; idempotência (recepção duplicada, duas ocorrências legítimas, entrega concorrente, recuperação sem novo processamento); versionamento; versão corrente única de material com transição atômica; síntese sem versão redundante e isolada por seção; isolamento por seção; elegibilidade de material para o contexto; escopo Professor/Admin; cálculos; critérios no limite e imediatamente acima e abaixo; desempate alfabético; zero com `no_data`, zero `valid` e `error`; falha da API LLM; atualização final de relatório e casos de agendamento (sem data final, mudança de data, coincidência, falha sem reinício das 72 horas) [REQ §Regras; DEC-01, 02, 03, 04, 05, 06, 07, 08, 09, 11, 12, 13, 15, 16, 17, 18, 19, 21, 22, 24, 26]. *(Alterado em 25/10/2026, conforme a spec-023 propagada em 22/10/2026; texto original: "máximo de 3 retries; idempotência; versionamento; isolamento por seção; escopo Professor/Admin; cálculos; falha da API LLM; atualização final de relatório [REQ §Regras].")* *(26/10/2026: a matriz do [quickstart.md](quickstart.md) inclui também as ênfases de DEC-31 a DEC-47.)*
- **Metodologia**: testes unitários por entrega e verificação de usabilidade pelo pesquisador, sem professores ou estudantes como participantes [REQ §Regras].
- **Execução**: no ambiente de spec-001, com ferramentas compatíveis com Moodle 5.2 [REQ §Conformidade].

*(Incluído em 26/10/2026 por DEC-48 e pelos tratamentos da revisão.)* Runners oficiais do Moodle: PHPUnit (unitário, integração, persistência/XMLDB, eventos e tarefas, privacidade, regras) e Behat (fluxos essenciais de UI: permissões, curso desabilitado, escape do conteúdo da LLM, filtros por grupo); LLM sempre pelo adaptador simulado do plano 009 (D1). O critério de aprovação é a rastreabilidade de cada cenário de quickstart para um teste automatizado, uma verificação do pesquisador ou uma validação técnica em CL; a cobertura de linhas é medida e reportada, sem ser critério (D2). Entrega = tarefa que altera código, com seus testes unitários; checklist de usabilidade do pesquisador depois de cada entrega que afete a UI (D3). Validação técnica em CL nunca conta como teste aprovado.

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: test runners compatíveis com Moodle 5.2. Nomes **NEEDS CLARIFICATION** (04/11/2026: resolvido: Q-023-01 por DEC-48 D1; PHPUnit e Behat em uso, `assistente/phpunit.xml`, `assistente/tests/phpunit.xml`, `assistente/tests/behat/*.feature`; a validação técnica dos runners no ambiente segue aberta em CL-22) *(04/11/2026, T140: superado: CL-22 Fechado (VT) em 04/11/2026; texto anterior preservado.)* (Q-023-01). *(26/10/2026: Q-023-01 resolvida por DEC-48 D1 — PHPUnit e Behat integrados ao Moodle; geradores de dados do Moodle; adaptador LLM simulado. Dependências de desenvolvimento no ambiente a tratar no plano 001.)*

**Storage**: banco de teste do ambiente spec-001 (Q-001-02).

**Testing**: o próprio objeto desta spec.

**Target Platform**: Codespaces/container de spec-001 [REQ §Conformidade].

**Project Type**: estratégia transversal de qualidade.

**Performance Goals**: N/A.

**Constraints**: LLM sempre mockada nos testes [REQ §Contratos]; sem participantes humanos além do pesquisador [REQ §Regras]. Validação técnica pendente (CL) não é contabilizada como teste aprovado (constituição §10; DEC-48). *(26/10/2026: incluída a última frase.)*

**Scale/Scope**: specs 001–022.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2. Evidências por requisito exigem os IDs dos 66 requisitos.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-22): PHPUnit e Behat no ambiente do Moodle 5.2, inclusive dependências de desenvolvimento e navegador para o Behat (plano 001). *(Alterado em 26/10/2026; observação original: "Runners não definidos nem verificados (Q-023-01).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: BLOQUEADO, CL-22 continua Aberto (VT): fechada só no nível funcional por DEC-48 D1; falta instalar e executar os runners (PHPUnit e Behat, dependências de desenvolvimento e navegador do Behat) no ambiente do Moodle 5.2 como validação própria do CL-22.)* *(Reavaliado em 04/11/2026, T140, texto anterior preservado acima: §5 Compatibilidade Moodle: **PASSA**; CL-22 Fechado (VT) em 04/11/2026 (PHPUnit e Behat executados no ambiente reproduzido; T007 e T008 [x]; T139: suítes completas aprovadas, `validation/t139/run.md`). O estado "BLOQUEADO" da coluna Pós vale só como histórico.)* |
| §6 Materiais | PASSA | PASSA | Isolamento por seção coberto pela matriz. |
| §7 Integridade | PASSA | PASSA | Versionamento e histórico cobertos. |
| §8 Idempotência | PASSA | PASSA | Retries e idempotência cobertos. |
| §9 Testabilidade | BLOQUEADO | PASSA | Cada plano aprovado tem cenários verificáveis; a matriz associa cada cenário a teste, verificação do pesquisador ou CL (DEC-48 D2); testes unitários por entrega (D3). *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| Muitas specs não têm critérios de aceitação mensuráveis (ver os Q-* de cada spec); cobertura mínima indefinida (Q-023-04).")* |
| §10 Transparência | PASSA | PASSA | Teste planejado ≠ teste executado. *(26/10/2026: e validação técnica em CL ≠ teste aprovado.)* |
| §11 Governança | PASSA | PASSA | — *(26/10/2026: runners, cobertura, entrega e protocolo decididos pelo responsável, DEC-48.)* |
| §12 Plataforma e arquitetura | — | PASSA | Os testes rodam no ambiente da spec-001 com runners compatíveis com o Moodle 5.2 (Q-023-01). *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* *(26/10/2026: PHPUnit e Behat do Moodle.)* |
| §13 Uso de LLM | — | PASSA | LLM sempre mockada nos testes [REQ spec-023 §Contratos]. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | PASSA | Testes de escopo professor/administrador planejados [REQ spec-023 §Regras]; o critério é o da DEC-30, e os cenários estão no quickstart da spec-005 (Q1–Q3, Q7–Q9). *(Reavaliado em 25/10/2026: DEC-30; Pós anterior: "BLOQUEADO", "herdam Q-005-01".)* *(26/10/2026: também `manage`, grupos e matrícula, DEC-43, 45, 47.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-22; não bloqueia o planejamento). Questões Bloqueador: nenhuma. *(Alterado em 26/10/2026 por DEC-48; texto anterior: "princípios BLOQUEADO: §5, §9 (§14 passou a PASSA por DEC-30 em 25/10/2026). Questões Bloqueador: Q-023-01.")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: nenhum princípio passa a PASSA nesta reavaliação; §5 Compatibilidade Moodle continua BLOQUEADO (CL-22 continua Aberto (VT): fechada só no nível funcional por DEC-48 D1; falta instalar e executar os runners (PHPUnit e Behat, dependências de desenvolvimento e navegador do Behat) no ambiente do Moodle 5.2 como validação própria do CL-22); texto anterior preservado acima.)* *(Reavaliado em 04/11/2026, T140: §5 Compatibilidade Moodle passa a **PASSA**, porque o CL-22 está Fechado (VT) em 04/11/2026 e as suítes completas da T139 passaram; nenhum princípio BLOQUEADO resta. Os textos acima ficam como histórico.)*

## Project Structure

### Documentation (this feature)

```text
specs/023-testes-e-qualidade-transversal/
├── plan.md · research.md · data-model.md · quickstart.md   # quickstart = matriz de validação
```

`contracts/` não foi gerado: estratégia interna, sem interface exposta.

### Source Code (repository root)

```text
assistente/
└── [diretórios de testes]   # layout NEEDS CLARIFICATION (04/11/2026: resolvido: layout real em `assistente/tests/` (`application/`, `observer/`, `task/`, `form/`, `privacy/`, `db/`, `generator/`, `behat/`), `assistente/tests/phpunit.xml`) (dependem do runner, Q-023-01)
```
*(26/10/2026: layout segue as convenções do PHPUnit e do Behat do Moodle para plugins; caminhos exatos nas tasks e em Q-003-04.)*

## Dependências entre specs

- Upstream: spec-001 até spec-022 [REQ §Dependências].
- Downstream: conclusão da implementação.

## Fases de planejamento & gates

1. Runners compatíveis com Moodle 5.2: PHPUnit e Behat do Moodle (Q-023-01, resolvida por DEC-48 D1); verificação no ambiente em CL-22, dependente do plano 001. *(Alterado em 26/10/2026; texto original: "Escolher os runners compatíveis com Moodle 5.2 (bloqueante, Q-023-01).")*
2. Critérios de aceitação: os cenários dos quickstarts dos planos aprovados (Q-023-02, resolvida). *(Alterado em 26/10/2026; texto original: "Fechar os critérios de aceitação pendentes em cada spec (os bloqueadores Q-*).")*
3. Montar o mock da porta LLM (spec-009). *(26/10/2026: adaptador simulado do plano 009, entregue antes da spec-008, DEC-33 D1.)*
4. **Gate G-023**: matriz de [quickstart.md](quickstart.md) executada com evidência por entrega; verificação de usabilidade registrada pelo pesquisador. *(26/10/2026: cada cenário rastreado para teste automatizado, verificação do pesquisador ou CL; cobertura de linhas reportada sem ser critério.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-023-01 | Resolvido | Ferramentas/test runners (unitário, integração, UI) compatíveis com Moodle 5.2. **Resolvido por DEC-48 D1 (26/10/2026):** PHPUnit integrado ao Moodle e Behat para os fluxos essenciais de UI; LLM pelo adaptador simulado. Verificação no ambiente em CL-22; dependências de desenvolvimento no plano 001. *(Tipo original: "Bloqueador".)* | §Conformidade; DEC-48 |
| Q-023-02 | Resolvido | Critérios de aceitação mensuráveis ausentes em várias specs (ver Q-* de 005, 011, 014, 015, 016, 019). **Resolvido (26/10/2026):** as bloqueadoras citadas foram fechadas por DEC-36, 39, 40, 41, 44 e 45; todos os planos aprovados têm cenários verificáveis, reunidos na matriz. *(Tipo original: "Aberto".)* | §Objetivo; planos 005–022 |
| Q-023-03 | Resolvido | Protocolo e registro da "verificação de usabilidade pelo pesquisador". **Resolvido por DEC-48 D3 (26/10/2026):** checklist derivada dos cenários do plano 021, depois de cada entrega que afete a UI; registro com data, versão do Moodle, cenários e resultados; local definido nas tasks. *(Tipo original: "Aberto".)* | §Regras; DEC-48 |
| Q-023-04 | Resolvido | Cobertura mínima exigida. **Resolvido por DEC-48 D2 (26/10/2026):** sem percentual mínimo; rastreabilidade obrigatória de cada cenário; cobertura de linhas medida e reportada, não critério. *(Tipo original: "Aberto".)* | §Regras; DEC-48 |
| Q-023-05 | Resolvido | Dados/fixtures de teste (curso, estudantes, questionários, fóruns). **Resolvido (26/10/2026):** geradores de dados do Moodle (curso, usuários, papéis, grupos, questionário, fórum, notas, conclusão) e um gerador do plugin para as tabelas próprias. *(Tipo original: "Aberto".)* | — |
| Q-023-06 | Resolvido | Definição de "entrega" para "testes unitários por entrega". **Resolvido por DEC-48 D3 (26/10/2026):** cada tarefa de `tasks.md` que altere código, com seus testes unitários (constituição §9). *(Tipo original: "Aberto".)* | §Regras; DEC-48 |
| Q-023-07 | Resolvido | "Máximo de 3 retries" (spec-023) × "máximo de 3 tentativas" (spec-007): 3 retries equivaleriam a 4 execuções. O teste de limite depende de qual texto vale. Ver Q-007-09. O registro de decisões traz DEC-09 (três tentativas totais); o texto da spec-023 ainda não foi atualizado. **Resolvido (25/10/2026):** a spec-023 foi alterada em 22/10/2026 para "3 tentativas totais por processamento, incluindo a tentativa inicial" (DEC-09), igual à spec-007. *(Tipo original: "Contradição".)* | spec-023 §Regras; spec-007 §Regras; DEC-09 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

- A §Regras da spec-023 ganhou cenários derivados de DEC-01, 02, 03, 04, 05, 06, 07, 08, 09, 11, 12, 13, 15, 16, 17, 18, 19, 21, 22, 24 e 26. As "Ênfases" do Summary e a matriz do [quickstart.md](quickstart.md) foram atualizadas.
- Q-023-07 resolvida (DEC-09).
- Q-023-02 continua aberta, com escopo menor: as specs 011, 014, 015, 016 e 019 ganharam critérios verificáveis pelas DEC, mas ainda têm questões bloqueadoras próprias (Q-011-01/02, Q-014-01/02/05, Q-015-02, Q-016-01, Q-019-01). *(26/10/2026: deixou de valer — essas bloqueadoras foram resolvidas por DEC-36, 39, 40, 41 e 44; Q-023-02 resolvida.)*
- DEC-14, DEC-20 e DEC-23 foram propagadas em 25/10/2026 depois da DEC-29 (esquema), e a spec-023 ganhou os cenários correspondentes. DEC-25 foi propagada em 25/10/2026, restrita às categorias que a spec-014 lista, e a spec-023 ganhou o cenário de frequência. *(Texto do mesmo dia substituído: "DEC-25 continua sem propagação (`closure-register.md` CL-04); os cenários dela não entram nesta matriz.")* *(Alterado em 25/10/2026; texto original: "DEC-14, DEC-20, DEC-23 e DEC-25 não foram propagadas (`closure-register.md` CL-07 e CL-04). Os cenários correspondentes não entram nesta matriz até a propagação.")*
- Q-023-01 (runners) continua bloqueadora (`closure-register.md` CL-22). A verificação no ambiente depende de CL-01 *(31/10/2026: CL-01 Fechado com ressalvas; a pré-condição de ambiente está satisfeita no que o CL-01 comprova, e a validação própria dos runners e das dependências de desenvolvimento não foi alterada por esse encerramento)* e, com o baseline atual (DEC-28), também da instalação das dependências de desenvolvimento (o Dockerfile usa `composer install --no-dev`), questão a tratar junto com Q-001-05. *(26/10/2026: Q-023-01 resolvida por DEC-48 D1; CL-22 fechado no nível funcional; a verificação no ambiente e as dependências de desenvolvimento seguem como validação técnica e como item da revisão do plano 001, sem presumir que o ambiente esteja preparado.)* *(04/11/2026, revisão dos planos: DEC-49 D1 remove `--no-dev` da instalação do Composer no ambiente de desenvolvimento; CL-22 continua Aberto (VT).)*

| Item (26/10/2026) | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Runners, cobertura, entrega e usabilidade | DEC-48 | Q-023-01, 03, 04, 06 resolvidas; §9 passa; CL-22 fechado no nível funcional | Incorporada; validação em CL-22 |
| Cenários de DEC-31 a DEC-47 | DEC-31 a DEC-47 | Matriz reconstruída por plano, com os intervalos completos dos quickstarts e as novas ênfases | Incorporada |
| Rastreabilidade de DEC-26 a DEC-47 | — | `traceability.md` atualizado | Incorporada |

O `spec.md` desta feature não foi alterado: DEC-48 afeta só o plano. Pendência: os cenários dos planos 001–004, ainda não aprovados nesta rodada, entram na matriz como provisórios e serão confirmados na revisão de cada um. *(Incluído em 26/10/2026.)* *(26/10/2026: pendência encerrada — planos 001–004 aprovados; cenários confirmados na matriz.)*

## Complexity Tracking

Nenhuma violação justificável.
