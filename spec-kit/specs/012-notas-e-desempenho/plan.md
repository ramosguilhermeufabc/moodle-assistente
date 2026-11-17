# Implementation Plan: spec-012 — Notas e Desempenho

**Branch**: `feature/sdd-ajustado` (feature dir `012-notas-e-desempenho`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/012-notas-e-desempenho/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026. A validação técnica do §5 (CL-34) continua pendente e R-012-01 fica como risco; nenhum dos dois bloqueia o planejamento. Nenhum cenário do quickstart foi executado nem validado no Moodle 5.2. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-34 Fechado (VT) 03/11/2026; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Consultar a nota final do gradebook do Moodle por estudante/atividade e persistir o desempenho em `local_assistente_performance` (`student_id + activity/cmid + grade`), sem pesos próprios e respeitando a configuração de avaliação do Moodle. A média do estudante deriva dessas notas conforme a configuração do Moodle [REQ spec-012 §Objetivo, §Contratos, §Regras, §Conformidade].

*(Incluído em 25/10/2026 por DEC-37 e pelos tratamentos da revisão.)* Os eventos de nota do Moodle (atribuição, alteração, inclusive substituição, e remoção) entram na fila; cada evento recalcula o desempenho do estudante no curso, com todas as suas atividades avaliadas (D1). A nota é gravada como percentual do intervalo do item, truncado a três casas e exibido a duas (D2). A média do estudante é o total final do curso do gradebook, registrado no snapshot de contexto da análise (D3). Cada execução é uma `analysis` imutável `grade_performance`; a atual é a última bem-sucedida.

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: GradebookReader (spec-004) sobre a API/estrutura oficial de gradebook [REQ §Conformidade]; tasks (spec-007). Eventos de nota na fila (spec-006, Q-006-07); nomes concretos em CL-34 e no plano 022. *(25/10/2026: incluída a última frase.)*

**Storage**: `local_assistente_performance` (único `analysis_id,student_id,activity_id`; `grade` number(10,3) NOT NULL) [spec-002]. `analysis.analysis_type = grade_performance`; total do curso no snapshot de contexto; sem campo novo (DEC-37 D3). *(25/10/2026: incluída a última frase.)*

**Testing**: cálculos e respeito à configuração de avaliação [spec-023]; conversão para percentual, truncamento, escalas, estados `valid`/`no_data`/`error`. Runner em Q-023-01. *(25/10/2026: incluídos os itens depois do primeiro ponto e vírgula.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, leitura e persistência derivada.

**Performance Goals**: N/A.

**Constraints**: sem pesos próprios [REQ §Regras]. Sem média própria e sem LLM; truncamento, nunca arredondamento (DEC-37 D2, D3). *(25/10/2026: incluída a segunda frase.)*

**Scale/Scope**: estudantes × atividades avaliadas do curso. *(25/10/2026: por evento, um estudante × todas as suas atividades avaliadas.)*

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-34): nota final, substituições, escalas, papéis avaliados, eventos de nota e reavaliação do total do curso no Moodle 5.2. *(Alterado em 25/10/2026; observação original: "API de gradebook não verificada.")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-34 (Fechado em 03/11/2026 com limitações aceitas; T080 [x]))* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | BLOQUEADO | PASSA | Cada execução é uma `analysis` `grade_performance` imutável; recalcular cria outra e preserva as anteriores; a atual é a última bem-sucedida. A política geral de histórico segue em Q-017-01. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Atualização de nota × preservação de histórico (Q-017-01).")* |
| §8 Idempotência | PASSA | PASSA | Único do esquema. |
| §9 Testabilidade | BLOQUEADO | PASSA | Gatilho, unidade, nota ausente e média definidos (DEC-37 e tratamentos); cenários Q1–Q11 do [quickstart.md](quickstart.md). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Gatilho e nota ausente indefinidos (Q-012-01/02).")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | Q-012-01, 03 e 05 decididas pelo responsável (DEC-37); CL-21 fechado. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Bloqueadores sobre significado ou lacuna da spec dependem de decisão humana (ver Questões Bloqueador).")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | Cálculo determinístico, sem LLM [REQ spec-009 §Regras]. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-34; não bloqueia o planejamento). Questões Bloqueador: nenhuma. Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Alterado em 25/10/2026 por DEC-37; texto original: "princípios BLOQUEADO: §5, §7, §9, §11. Questões Bloqueador: Q-012-01, Q-012-02.")* *(Reavaliado em 04/11/2026: passam todos os princípios, inclusive §5 Compatibilidade Moodle, validado em CL-34 (Fechado em 03/11/2026 com limitações aceitas; T080 [x]); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/012-notas-e-desempenho/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/performance.md
```

### Source Code (repository root)

```text
assistente/
└── [serviço de desempenho]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: Q-003-04 resolvida na seção "Layout das classes" de specs/003-*/plan.md (aprovada em 26/10/2026); código em assistente/classes/application/grade/performance_handler.php.)
```

## Dependências entre specs

- Upstream: spec-004, spec-007 [REQ §Dependências].
- Downstream: 015, 016, 018. *(25/10/2026: também 019 e 021 (estados e exibição a duas casas). A spec-016 exige desempenho por seção, que o gradebook do Moodle não totaliza: a definição fica para a revisão do plano 016, sem antecipação aqui. A unidade do critério de desempenho segue em CL-27 (Q-005-05, Q-015-02).)*

## Fases de planejamento & gates

1. Definir o gatilho (evento ou ciclo) e o escopo de atividades (Q-012-01/04). *(25/10/2026: DEC-37 D1 e tratamento de Q-012-04.)*
2. Definir o tratamento de nota ausente (Q-012-02) e da média do estudante (Q-012-03). *(25/10/2026: tratamento de Q-012-02 e DEC-37 D3.)*
3. **Gate G-012**: as notas persistidas são iguais às do gradebook, inclusive em configurações não padrão. *(25/10/2026: "iguais" = mesma nota final do Moodle convertida pela fórmula de D2; cenários Q1–Q11.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-012-01 | Resolvido | Gatilho da atualização: evento de nota (não listado em spec-006) ou ciclo periódico? **Resolvido por DEC-37 D1 (25/10/2026):** eventos de atribuição, alteração e remoção de nota na fila; cada evento recalcula o estudante no curso, com todas as suas atividades avaliadas; reavaliações do Moodle propagam mudanças de peso e agregação (CL-34). *(Tipo original: "Bloqueador".)* | §Objetivo; Q-006-07; DEC-37 |
| Q-012-02 | Resolvido | Atividade sem nota: `grade` é NOT NULL. Omitir a linha? **Resolvido (25/10/2026):** sim; sem nota atribuída não há linha, e o resultado é `no_data`. Nota zero é `valid`; falha de processamento é `error`, nunca zero (DEC-24, P-06, mesmo padrão de DEC-36). Item com intervalo nulo (`max = min`) também não gera linha. *(Tipo original: "Bloqueador".)* | spec-002; DEC-24; P-06 |
| Q-012-03 | Resolvido | Onde a "média de notas do estudante" é calculada ou persistida (não há campo) e qual valor do gradebook ela usa. **Resolvido por DEC-37 D3 (25/10/2026):** total final do curso do gradebook, que aplica pesos, agregação e notas vazias conforme o professor; registrado no snapshot de contexto da análise, em percentual (D2); sem campo novo; o plugin não calcula média. *(Tipo original: "Aberto".)* | §Regras; DEC-37 |
| Q-012-04 | Resolvido | Quais atividades entram (todas as avaliadas?). **Resolvido (25/10/2026):** itens do gradebook vinculados a módulo de atividade (`cmid`), com nota numérica ou escala. Fora: itens de categoria, itens manuais sem `cmid`, o total do curso como atividade, itens sem tipo de nota e notas só textuais. Estudantes: usuários dos papéis avaliados do gradebook. *(Tipo original: "Aberto".)* | §Contratos; spec-002 |
| Q-012-05 | Resolvido | Escala da nota (bruta × normalizada) para comparação com o critério de spec-015 (Q-005-05). **Resolvido por DEC-37 D2 (25/10/2026):** percentual do intervalo do item, `(grade − min) / (max − min) × 100`, truncado a três casas e exibido a duas, inclusive para escalas. A unidade do critério continua em CL-27 (Q-005-05, Q-015-02). *(Tipo original: "Aberto".)* | §Regras; spec-015; DEC-37 |
| R-012-01 | Mitigado *(tipo original: "Risco")* | *(Incluído em 25/10/2026.)* O Moodle recalcula o total do curso de forma tardia (itens marcados para reavaliação); ler o total antes da reavaliação pode registrar valor desatualizado no snapshot. Comportamento e forma de leitura em CL-34. *(04/11/2026, etapa 5a: Mitigado; evidência: `assistente/classes/infrastructure/moodle/gradebook_reader.php` chama `grade_regrade_final_grades()` (reavaliação nativa do Moodle) quando o curso precisa dela; `assistente/tests/infrastructure/moodle/gradebook_reader_test.php` (`test_course_total_after_regrade`); CL-34 Fechado em 03/11/2026 (o caminho preguiçoso `needsupdate` não foi observado no Moodle real, só em PHPUnit).)* | DEC-37 D1, D3 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

A propagação de 22/10/2026 não alterou o `spec.md` desta feature, e nenhuma DEC trata desta spec. O plano continua válido como está. Continuam bloqueadoras Q-012-01 (gatilho) e Q-012-02 (atividade sem nota), que dependem de decisão humana (`closure-register.md` CL-21). Nada foi executado nem testado. *(25/10/2026: deixou de valer — Q-012-01 a 05 resolvidas por DEC-37 e pelos tratamentos da revisão; CL-21 fechado. Ver a tabela abaixo.)*

| Item | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Gatilho por eventos de nota, recálculo por estudante | DEC-37 D1 | Q-012-01 resolvida; §11 passa | Incorporada (25/10/2026); validação em CL-34 |
| Percentual do intervalo do item, truncado | DEC-37 D2; DEC-36 D2 (padrão) | Q-012-05 resolvida | Incorporada (25/10/2026) |
| Média = total do curso do gradebook, no snapshot | DEC-37 D3 | Q-012-03 resolvida | Incorporada (25/10/2026); validação em CL-34 |
| Estados `valid`/`no_data`/`error` sem campo novo | DEC-24; P-06; DEC-36 (padrão) | Q-012-02 resolvida; §9 passa | Incorporada (25/10/2026) |
| Ausência de materiais × cobertura zero | DEC-35 D2 | Não se aplica: a spec-012 não tem cobertura | — |

O `spec.md` desta feature não foi alterado: DEC-37 afeta só o plano. Propagações pendentes, a aplicar na revisão de cada plano: 015 (consumo da nota em percentual; unidade do critério em CL-27), 016 (desempenho por seção, a decidir), 018, 019 e 021 (estados e exibição a duas casas), 022 (registro dos eventos de nota). *(Incluído em 25/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-37 aos planos-alvo 019 não feita; superada pela implementação e validação: 019: T115 [x] e T116 [x] (truncamento a duas casas e estados), CL-39 FECHADA em 28/10/2026; os planos-alvo não foram reabertos. Planos-alvo com citação: 015, 016, 018, 021, 022.)*

## Complexity Tracking

Nenhuma violação justificável.
