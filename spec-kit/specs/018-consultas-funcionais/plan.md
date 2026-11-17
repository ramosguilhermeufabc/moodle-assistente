# Implementation Plan: spec-018 — Consultas Funcionais

**Branch**: `feature/sdd-ajustado` (feature dir `018-consultas-funcionais`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/018-consultas-funcionais/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, conforme DEC-43. A validação técnica do §5 (CL-38) continua pendente e não bloqueia o planejamento. Nenhum cenário do quickstart foi executado. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-38 Fechado (VT) 03/11/2026; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Disponibilizar no Moodle consultas predefinidas, sem perguntas livres em linguagem natural, por curso/turma e função: notas, métricas de questionários, frequência, fóruns, alunos abaixo dos critérios, resumos de materiais, síntese e histórico [REQ spec-018 §Objetivo, §Contratos]. Cada consulta mostra só os dados definidos pela spec correspondente, organizados por estudante quando aplicável, respeitando o escopo de professor e administrador e sem indicadores adicionais [REQ §Regras]. A UI fica para as specs 020/021 [REQ §Conformidade].

*(Incluído em 26/10/2026 por DEC-43 e pelos tratamentos da revisão.)* Turma = grupo do Moodle: as consultas individuais (notas, frequência, abaixo dos critérios) podem ser filtradas por grupo, e as agregadas ficam no nível de curso ou seção (D1). Em grupos separados, o professor sem a capability de ver todos os grupos vê só os estudantes dos seus grupos nas consultas individuais (D2). Cada consulta lê a execução atual (última bem-sucedida) e aplica as regras de apresentação das decisões aprovadas: percentuais truncados a duas casas, estados `valid`/`no_data`/`error`, "sem materiais", acesso ao curso como evidência, snapshot atual do fórum, síntese vigente por seção e "fonte removida".

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: readers/gateways (spec-003); autorização (spec-005); resultados 011–017. *(26/10/2026: também 008 e 010; API de grupos do Moodle para D1/D2, CL-38.)*

**Storage**: somente leitura das tabelas de spec-002. *(26/10/2026: grupos lidos do Moodle no momento da consulta; sem campo novo.)*

**Testing**: acesso e escopo [spec-023]. Runner em Q-023-01. *(26/10/2026: também filtro por grupo, grupos separados, estados, truncamento e "fonte removida".)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, camada de consulta (application services de leitura).

**Performance Goals**: N/A.

**Constraints**: sem linguagem natural livre; nenhum indicador inventado [REQ]. Nenhum dado é enviado para fora do sistema por estas consultas. *(26/10/2026: incluída a segunda frase.)*

**Scale/Scope**: 8 consultas [REQ §Contratos].

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | Consultas = lista fechada da spec. |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | Consulta × UI. |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-38): API de grupos, grupos separados, capability de ver todos os grupos, associação a grupos e paginação no Moodle 5.2. *(Alterado em 26/10/2026; observação original: ""Turma" não tem correspondente definido (Q-018-01).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-38 (Fechado (VT) em 03/11/2026, com limitações aceitas; commit 6d0bda6).)* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | N/A | N/A | Somente leitura. |
| §8 Idempotência | N/A | N/A | — |
| §9 Testabilidade | BLOQUEADO | PASSA | As definições de 011–017 foram fechadas (DEC-35 a DEC-42); cenários Q1–Q13 do [quickstart.md](quickstart.md). *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| Herda indefinições de 011–017.")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | — *(26/10/2026: Q-018-01 e Q-018-05 decididas pelo responsável, DEC-43.)* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | PASSA | As consultas respeitam o escopo de professor e administrador [REQ spec-018 §Regras]; o critério operacional é o da DEC-30 (spec-005). *(Reavaliado em 25/10/2026: DEC-30; Pós anterior: "BLOQUEADO", "herda Q-005-01".)* A lista de estudantes das consultas individuais segue o modelo de grupos do Moodle (DEC-43 D2). *(26/10/2026.)* |
| §15 Interfaces externas | — | PASSA | As consultas são predefinidas e internas ao Moodle, sem API pública. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-38; não bloqueia o planejamento). Questões Bloqueador: nenhuma. *(Alterado em 26/10/2026 por DEC-43; texto anterior: "princípios BLOQUEADO: §5, §9 (§14 passou a PASSA por DEC-30 em 25/10/2026). Questões Bloqueador: nenhum.")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa a PASSA (validado em CL-38 (Fechado (VT) em 03/11/2026, com limitações aceitas; commit 6d0bda6)); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/018-consultas-funcionais/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/functional-queries.md
```

### Source Code (repository root)

```text
assistente/
└── [serviços de consulta]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: layout de Q-003-04 em 003/plan.md; os serviços de consulta existem em `assistente/classes/application/query/` (`aggregate_query.php`, `below_criteria_query.php`, `forums_query.php`, `grades_query.php`, `history_query.php` e outros) e `student_scope.php`)
```

## Dependências entre specs

- Upstream: spec-005, 011, 012, 013, 014, 015, 016, 017 [REQ §Dependências].
- Downstream: 019, 020, 021. *(26/10/2026: 020 define os filtros, inclusive o de grupo; 021 apresenta os estados e as indicações.)*

## Fases de planejamento & gates

1. Definir "turma" e "função" (Q-018-01/02). *(26/10/2026: DEC-43 D1; "função" = papel, DEC-30.)*
2. Definir o contrato de cada uma das 8 consultas ([contracts/functional-queries.md](contracts/functional-queries.md)). *(26/10/2026: contrato atualizado.)*
3. **Gate G-018**: escopo por papel testado; nenhum campo fora das specs de origem. *(26/10/2026: cenários Q1–Q13.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-018-01 | Resolvido | "Por curso/turma": o esquema não tem campo de turma/grupo, e nenhuma outra spec define turma. **Resolvido por DEC-43 D1 (26/10/2026):** turma = grupo do Moodle; filtro por grupo nas consultas individuais, com associação lida na consulta; agregadas no nível de curso ou seção; sem entidade nem campo próprio. CL-10 fechado. *(Tipo original: "Contradição".)* | §Contratos; spec-002; DEC-43 |
| Q-018-02 | Resolvido | Significado de "e função" (função de consulta? papel do usuário?). **Resolvido (26/10/2026):** papel do usuário (professor ou administrador), que define o escopo de acesso pela DEC-30; o conteúdo das consultas não muda por papel. *(Tipo original: "Aberto".)* | §Contratos; DEC-30 |
| Q-018-03 | Resolvido | Filtros, ordenação e paginação de cada consulta (filtros: spec-020). **Parcial (25/10/2026):** a spec define o desempate (pontuação igual em ordem alfabética crescente do nome, DEC-02). Filtros, ordenação principal e paginação continuam abertos. **Resolvido (26/10/2026):** ordenação padrão por nome A–Z; ordenação pelo valor exibido com desempate por nome (DEC-02); filtros no plano 020; paginação nativa do Moodle. *(Tipo original: "Aberto".)* | §Contratos; spec-020; DEC-02 |
| Q-018-04 | Resolvido | Consulta "histórico": quais entidades e versões. **Resolvido (26/10/2026):** execuções anteriores de cada consulta (análises imutáveis com data), versões de material, de síntese e de relatório; por padrão, a última execução bem-sucedida (DEC-42; plano 017). *(Tipo original: "Aberto".)* | §Contratos; spec-017; DEC-42 |
| Q-018-05 | Resolvido | O professor vê todos os estudantes do curso? (restrições adicionais não definidas). **Resolvido por DEC-43 D2 (26/10/2026):** em grupos separados, sem a capability de ver todos os grupos, só os estudantes dos grupos que pode ver, nas consultas individuais; senão, todos. Agregadas sem restrição por grupo. DEC-30 inalterada. *(Tipo original: "Aberto".)* | §Regras; DEC-43 |
| Q-018-06 | Resolvido | "Alunos abaixo dos critérios" depende de Q-015-01…05. **Resolvido por DEC-40 (26/10/2026):** lista estudantes com `0` em qualquer dimensão e mostra os três estados; `2` aparece como "sem dados". *(Tipo original: "Aberto".)* | spec-015; DEC-40 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Pontuação igual: ordem alfabética crescente do nome [REQ spec-018 §Regras] | DEC-02 | Q-018-03 parcial; o contrato das consultas deve aplicar o desempate | Incorporada |
| Comparação dos critérios (spec-015) | DEC-11 | Q-018-06 depende agora só de Q-015-02…05 (Q-015-01 resolvida) | Incorporada |
| Turma = grupo; estudantes visíveis pelo modelo de grupos | DEC-43 | Q-018-01 e Q-018-05 resolvidas; CL-10 fechado | Incorporada (26/10/2026); validação em CL-38 |
| "Sem materiais" × cobertura zero | DEC-35 D2 | Consulta de questionários | Incorporada (26/10/2026) |
| Estados e truncamento a duas casas | DEC-24, DEC-36, DEC-37, DEC-39 | Todas as consultas | Incorporada (26/10/2026) |
| Acesso ao curso como evidência complementar | DEC-39 D3 | Consulta de frequência | Incorporada (26/10/2026) |
| Snapshot atual do fórum × histórico | DEC-38 | Consultas de fóruns e histórico | Incorporada (26/10/2026) |
| Estados 1/0/2 dos critérios | DEC-40 | Q-018-06 resolvida | Incorporada (26/10/2026) |
| Síntese vigente por curso e seção, exibida escapada | DEC-41 | Consulta de síntese | Incorporada (26/10/2026) |
| Histórico e "fonte removida" | DEC-42 | Q-018-04 resolvida; indicação em todas as consultas | Incorporada (26/10/2026) |

Continua aberta a contradição Q-018-01 (turma sem campo, `closure-register.md` CL-10). *(26/10/2026: deixou de valer — resolvida por DEC-43; CL-10 fechado.)*

O `spec.md` desta feature não foi alterado: DEC-43 afeta só o plano. Propagações pendentes, a aplicar na revisão de cada plano: 019 (relatórios: mesmas regras de apresentação; filtro por grupo nas partes individuais, se houver), 020 (filtro de grupo nos formulários), 021 (exibição dos estados, "sem dados", "sem materiais", "fonte removida"), 023 (testes de grupos separados). *(Incluído em 26/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-43 feita: todos os planos-alvo (019, 020, 021, 023) citam a decisão nos artefatos de plano.)*

**Referência a DEC-45 D3** *(incluída em 26/10/2026, na revisão do plano 021, com autorização do responsável; alteração somente referencial, sem mudança do conteúdo funcional deste plano aprovado)*: em curso sem configuração ou com o assistente desabilitado, as consultas não exibem resultados e informam "assistente não habilitado". Ver `decision-register-DEC-01-25.md`, DEC-45.

**Referência a DEC-47** *(incluída em 26/10/2026, na revisão do plano 022, com autorização do responsável; alteração somente referencial, sem mudança do conteúdo funcional deste plano aprovado)*: as listas por estudante são filtradas no momento da consulta, considerando a matrícula ativa e o papel contemplado pelo livro de notas. Um estudante recém-matriculado sem resultados aparece como "sem dados"; um estudante removido do curso deixa de aparecer nas listas, com o histórico preservado (DEC-42). Ver `decision-register-DEC-01-25.md`, DEC-47.

## Complexity Tracking

Nenhuma violação justificável.
