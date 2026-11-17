# Implementation Plan: spec-015 — Aplicação dos Critérios

**Branch**: `feature/sdd-ajustado` (feature dir `015-aplica-o-dos-crit-rios`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/015-aplica-o-dos-crit-rios/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026, conforme DEC-40. Sem princípio bloqueado e sem validação técnica própria (postagens e datas de fórum em CL-35 e CL-36). Nenhum cenário do quickstart foi executado. A aprovação isolada não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Identificar estudantes abaixo dos critérios do curso aplicando, de forma determinística e sem LLM, os critérios de atividade (numérico), fórum (binário) e frequência (critério configurado) sobre os resultados de atividade, de fórum e de frequência [REQ spec-015 §Objetivo, §Contratos, §Regras, §Conformidade]. Não há limiares implícitos. O resultado vai para `local_assistente_criteria_result` e fica disponível nas consultas e relatórios [REQ §Contratos, §Regras].

*(Incluído em 25/10/2026 por DEC-40 e pelos tratamentos da revisão.)* Os critérios numéricos são percentuais de 0 a 100 e de mínimo (D1; DEC-11). Atividade: total final do curso do estudante (DEC-37 D3) contra `activity_criterion` (D2). Fórum: com `forum_criterion = 1`, o estudante atende se postou pelo menos uma vez em cada fórum elegível cuja data de referência passou (DEC-39), a partir das postagens do Moodle (D3). Frequência: percentual global `Σ participating / Σ total × 100` dos registros da spec-014, truncado (D4). Status por dimensão `1 = atende`, `0 = abaixo`, `2 = no_data`.

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: critérios (spec-005); resultados de 011, 012, 013 e 014. *(25/10/2026: na prática, 012 (total do curso no snapshot, DEC-37 D3), 014 (registros de frequência) e as postagens de fórum do Moodle com a elegibilidade e as datas de DEC-39; a análise de dificuldades da 013 não é usada (DEC-40 D3); a 011 não entra no critério de atividade (DEC-40 D2).)*

**Storage**: `local_assistente_criteria_result` (`activity_status`, `forum_status`, `frequency_status` int(1); único `analysis_id,student_id`) [spec-002]. *(25/10/2026: valores 1/0/2; `analysis.analysis_type = criteria_check`; sem campo novo.)*

**Testing**: regras determinísticas [spec-023]. Runner em Q-023-01. *(25/10/2026: fronteiras, unidade, fórum binário, frequência global e estados.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, regra determinística.

**Performance Goals**: N/A.

**Constraints**: sem limiares implícitos; sem LLM [REQ]. Ausência de dados nunca significa "abaixo" (DEC-24; DEC-40). *(25/10/2026: incluída a segunda frase.)*

**Scale/Scope**: por estudante do curso.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | Nenhum limiar foi criado. *(25/10/2026: os limites vêm só de `criteria`; a semântica do fórum binário foi decidida explicitamente pelo responsável, DEC-40 D3.)* |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | N/A | N/A | Sem API Moodle direta. *(25/10/2026: as postagens e as datas de fórum usadas por D3 já estão validadas em CL-35 e CL-36; nenhuma validação nova.)* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | BLOQUEADO | PASSA | Cada execução é uma `analysis` `criteria_check` imutável; a atual é a última bem-sucedida. A política geral segue em Q-017-01. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Q-017-01.")* |
| §8 Idempotência | PASSA | PASSA | Único `analysis_id,student_id`. |
| §9 Testabilidade | BLOQUEADO | PASSA | Agregação da atividade, fórum por estudante, frequência consolidada e semântica de `*_status` definidas (DEC-40 e tratamentos); cenários Q1–Q13 do [quickstart.md](quickstart.md). *(Alterado em 25/10/2026; texto anterior: "BLOQUEADO \| Agregação da atividade, origem do resultado de fórum por estudante e semântica de `*_status` indefinidas (Q-015-02…05). O operador "abaixo" foi resolvido pela spec (DEC-11)."; texto original: "Operador "abaixo", agregação da atividade, origem do resultado de fórum por estudante e semântica de `*_status` indefinidos (Q-015-01…05).")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | Q-015-02 a 04 decididas pelo responsável (DEC-40); CL-05 e CL-12 fechados. *(Alterado em 25/10/2026; texto anterior: "BLOQUEADO \| Q-015-02 depende de decisão humana sobre o significado da spec."; texto original: "Q-015-01 e Q-015-02 dependem de decisão humana sobre o significado da spec.")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | Regra determinística, sem LLM [REQ spec-015]. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: nenhum. Questões Bloqueador: nenhuma. *(Alterado em 25/10/2026 por DEC-40; texto anterior: "princípios BLOQUEADO: §7, §9, §11. Questões Bloqueador: Q-015-02.")* *(Alterado em 25/10/2026; texto original: "Questões Bloqueador: Q-015-01, Q-015-02.")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: nenhuma linha da tabela tem a coluna Pós em BLOQUEADO; nenhum princípio BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/015-aplica-o-dos-crit-rios/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/criteria-application.md
```

### Source Code (repository root)

```text
assistente/
└── [avaliador de critérios]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: Q-003-04 resolvida na seção "Layout das classes" de specs/003-*/plan.md (aprovada em 26/10/2026); código em assistente/classes/domain/criteria/criteria_evaluator.php.)
```

## Dependências entre specs

- Upstream: spec-005, 011, 012, 013, 014 [REQ §Dependências]. *(25/10/2026: dependências efetivas por DEC-40 em Primary Dependencies.)*
- Downstream: 016, 018, 019. *(25/10/2026: também 020 (formulário em percentual, DEC-40 D1) e 021 (apresentação dos estados 1/0/2).)*

## Fases de planejamento & gates

1. Aplicar a comparação definida na spec para os critérios numéricos (DEC-11) e fechar o restante da semântica (bloqueante, Q-015-02…05). *(Alterado em 25/10/2026; texto original: "Fechar a semântica de comparação para os três critérios (bloqueante, Q-015-01…05).")* *(25/10/2026: fechada por DEC-40.)*
2. Definir o contrato ([contracts/criteria-application.md](contracts/criteria-application.md)). *(25/10/2026: contrato atualizado.)*
3. **Gate G-015**: testes de fronteira (valor = critério, logo abaixo, logo acima) com a semântica aprovada. *(25/10/2026: cenários Q1–Q13.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-015-01 | Resolvido | Operador de "abaixo": estrito (<)? Igual ao critério conta como atendido? **Resolvido pela spec (25/10/2026):** mínimo atende se valor >= limite e fica abaixo se < limite; máximo atende se valor <= limite e ultrapassa se > limite (DEC-11). *(Tipo original: "Bloqueador".)* | §Objetivo; DEC-11 |
| Q-015-02 | Resolvido | Critério de atividade: compara cada nota ou a média do estudante (spec-012)? `activity_status` é único por estudante. **Resolvido por DEC-40 D1/D2 (25/10/2026):** total final do curso por estudante (DEC-37 D3), em percentual, contra `activity_criterion` (percentual 0–100, mínimo). *(Tipo original: "Bloqueador".)* | §Contratos; spec-002; DEC-40 |
| Q-015-03 | Resolvido | Fórum binário exige um "resultado de fórum" por estudante, mas spec-013 produz dificuldades por discussão, não por estudante (Q-013-07). **Resolvido por DEC-40 D3 (25/10/2026):** o resultado por estudante vem das postagens do Moodle: com `forum_criterion = 1`, atende quem postou pelo menos uma vez em cada fórum elegível vencido (DEC-39); `0` desativa; sem fórum vencido, `no_data`. Não usa a análise da 013 nem LLM. CL-12 fechado. *(Tipo original: "Contradição".)* | §Contratos; spec-013; DEC-40 |
| Q-015-04 | Resolvido | Frequência: qual percentual comparar (por seção ou consolidado)? `frequency` é por estudante+seção e `criteria_result` é por estudante. **Resolvido por DEC-40 D4 (25/10/2026):** percentual global `Σ participating_activities / Σ total_activities × 100`, truncado; denominador total zero = `no_data`. *(Tipo original: "Aberto".)* | §Regras; spec-014; DEC-40 |
| Q-015-05 | Resolvido | Semântica de `activity_status`/`forum_status`/`frequency_status` (1 = abaixo? atende?). **Resolvido (25/10/2026):** `1 = atende`, `0 = abaixo`, `2 = no_data`, no `int(1)` existente; falha de processamento = `error`. *(Tipo original: "Aberto".)* | spec-002; DEC-24; P-06 |
| Q-015-06 | Resolvido | Gatilho do recálculo. **Resolvido (25/10/2026):** após análise bem-sucedida de notas (012) ou de frequência (014) do estudante e após evento de postagem em fórum; a Scheduled Task trata a passagem das datas de referência dos fóruns. *(Tipo original: "Aberto".)* | DEC-37 D1; DEC-39 D2 |
| Q-015-07 | Resolvido | Estudante sem dados em alguma dimensão. **Resolvido (25/10/2026):** a dimensão recebe `2 = no_data`; nunca é tratada como "abaixo". *(Tipo original: "Aberto".)* | DEC-24; DEC-40 |
| Q-015-08 | Resolvido | "Frequência = critério configurado" (015) × "frequência: numérico" (005): mesma coisa? (provável, não declarado). **Resolvido (25/10/2026):** sim; ambas se referem a `frequency_criterion` (numérico, percentual, mínimo). *(Tipo original: "Aberto".)* | §Regras; spec-005; spec-002 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Comparação de mínimo (`>=` atende, `<` abaixo) e de máximo (`<=` atende, `>` ultrapassa) [REQ spec-015 §Regras] | DEC-11 | Q-015-01 resolvida; gate G-015 com fronteira: igual, imediatamente abaixo e imediatamente acima | Incorporada |
| Pontuação igual: ordem alfabética crescente do nome [REQ §Regras] | DEC-02 | O contrato ([contracts/criteria-application.md](contracts/criteria-application.md)) deve ordenar a saída listada | Incorporada |
| Frequência: presença × denominador [REQ spec-015 §Regras] | DEC-25 | Propagada em 25/10/2026: o critério de frequência compara o percentual da spec-014, sem o acesso ao curso no denominador. Q-015-04 (percentual por seção ou consolidado) continua aberta | Incorporada *(estado original: "Não propagada (`closure-register.md` CL-04) \| Bloqueada")* |
| Unidade, atividade, fórum binário e frequência global | DEC-40 | Q-015-02 a 08 resolvidas; CL-05 e CL-12 fechados; §7, §9 e §11 passam | Incorporada (25/10/2026) |
| Total do curso (DEC-37 D3); elegibilidade e datas de fórum, truncamento (DEC-39) | DEC-37, DEC-39 | Entradas do critério de atividade, de fórum e de frequência | Incorporada (25/10/2026) |

Continuam abertas: Q-015-02 (bloqueadora), a contradição Q-015-03 (fórum por estudante, CL-12) e Q-015-04 a 08. *(25/10/2026: deixou de valer — todas resolvidas por DEC-40 e pelos tratamentos da revisão.)*

O `spec.md` desta feature não foi alterado: DEC-40 afeta só o plano. Propagações pendentes, a aplicar na revisão de cada plano: 016 (uso dos status na síntese), 018, 019 e 021 (apresentação dos estados 1/0/2 e da lista de abaixo dos critérios, com DEC-02), 020 (formulário dos critérios em percentual 0–100). No plano 005 aprovado, só foram acrescentadas referências em Q-005-05 e Q-005-06. *(Incluído em 25/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-40 aos planos-alvo 019 não feita; superada pela implementação e validação: 019: T115 [x] e T116 [x] (bloco "abaixo dos critérios" no `report_collector` e no `report_builder`), CL-39 FECHADA em 28/10/2026; os planos-alvo não foram reabertos. Planos-alvo com citação: 016, 018, 021, 020.)*

**Referência a DEC-45 D2** *(incluída em 26/10/2026, na revisão do plano 020, com autorização do responsável; alteração somente referencial, sem mudança do conteúdo funcional deste plano aprovado)*: cada execução de `criteria_check` deve preservar no seu contexto (`processing_context`) os limites efetivamente utilizados (`activity_criterion`, `forum_criterion`, `frequency_criterion`). Assim, uma correção administrativa dos critérios, que vale só para novas avaliações, não altera a interpretação das execuções anteriores. Ver `decision-register-DEC-01-25.md`, DEC-45.

## Complexity Tracking

Nenhuma violação justificável.
