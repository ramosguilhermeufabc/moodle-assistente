# Implementation Plan: spec-011 — Respostas e Métricas de Questionários

**Branch**: `feature/sdd-ajustado` (feature dir `011-respostas-e-m-tricas-de-question-rios`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/011-respostas-e-m-tricas-de-question-rios/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026. A validação técnica do §5 (CL-33) e a Q-011-07 continuam pendentes e não bloqueiam o planejamento; R-011-01 fica como risco a avaliar na implementação, sem limite de histórico definido. Nenhum cenário do quickstart foi executado nem validado no Moodle 5.2. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-33 Fechado (VT) 03/11/2026; Q-011-07 resolvida pela DEC-52; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Calcular, de forma determinística e sem LLM, as métricas das respostas processadas [REQ spec-011 §Objetivo, §Regras]:
- **por questão**: `average_correctness`, `correct_count`, `incorrect_count` e `partial_result = correct_items_answered / total_correct_items` (ex.: 2/3 = 66,66%);
- **agregado**: `mean`, `population_standard_deviation`, `minimum` e `maximum`, que no fechamento do questionário consideram todas as respostas [REQ §Contratos, §Regras].

*(Incluído em 25/10/2026 por DEC-36 e pelos tratamentos da revisão.)* As métricas por questão usam só respostas com nota atribuída (D1); os valores são percentuais 0–100 truncados a três casas e exibidos truncados a duas (D2); os agregados usam um resultado final por estudante segundo o método de avaliação do questionário, só com tentativas concluídas (D3). A recalculação ocorre após submissão, correção manual e reavaliação, e uma última vez quando a Scheduled Task detecta que `timeclose` passou (D4). Versões novas de questões analisadas ligam-se à linha analisada da mesma entrada do banco; questões sem linha entram só nos agregados (D5). Cada execução é uma `analysis` imutável `quiz_results`; a atual é a última bem-sucedida.

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: QuestionnaireReader (spec-004) sobre `mod_quiz`, tentativas e banco de questões; `questionnaire_question` (spec-010); tasks (spec-007), inclusive a detecção do fechamento; eventos de submissão, correção manual e reavaliação na fila (spec-006, Q-006-07). *(Alterado em 25/10/2026; texto original: "QuestionnaireReader (spec-004); `questionnaire_question` (spec-010); tasks (spec-007).")*

**Storage**: `local_assistente_question_result` (único `analysis_id,question_id,question_version`), `local_assistente_questionnaire_metric` (1:1 analysis) [REQ §Contratos; spec-002]. `analysis.analysis_type = quiz_results`; sem campo novo (DEC-36 D5; P-06). *(25/10/2026: incluída a última frase.)*

**Testing**: cálculos [REQ spec-023 §Regras]; valores conhecidos, truncamento, método de avaliação, estados `valid`/`no_data`/`error`, versões sem linha analisada. Runner em Q-023-01. *(Alterado em 25/10/2026; texto original: "cálculos [REQ spec-023 §Regras]. Runner em Q-023-01.")*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, cálculo determinístico.

**Performance Goals**: N/A. Ver R-011-01.

**Constraints**: sem LLM; desvio-padrão **populacional** [REQ §Contratos]. Truncamento, nunca arredondamento (DEC-36 D2); tentativas de pré-visualização do professor sempre excluídas. *(25/10/2026: incluída a segunda frase.)*

**Scale/Scope**: por questionário.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | Fórmula de partial copiada da spec. O exemplo 2/3 = 66,66% é reproduzido por D2. *(25/10/2026: incluída a segunda frase.)* |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-33): tentativas, estados e frações, métodos de avaliação, correção manual, reavaliação, entrada do banco de questões e eventos do questionário no Moodle 5.2. *(Alterado em 25/10/2026; observação original: "Leitura de tentativas/respostas e evento de fechamento não verificados.")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-33 (Fechado em 03/11/2026 com limitações aceitas; T075 [x]))* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | BLOQUEADO | PASSA | Cada execução é uma `analysis` `quiz_results` imutável; recalcular cria outra e preserva as anteriores; a atual é a última bem-sucedida. A política geral de histórico segue em Q-017-01. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Preservação do resultado anterior ao recalcular no fechamento (Q-017-01).")* |
| §8 Idempotência | PASSA | PASSA | Cálculo determinístico + únicos do esquema. |
| §9 Testabilidade | BLOQUEADO | PASSA | Definições, escala, truncamento e unidade fixados por DEC-36; cenários Q1–Q14 do [quickstart.md](quickstart.md). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Definições de `average_correctness`, contagens e arredondamento ausentes (Q-011-01/02).")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | Q-011-01 a Q-011-05 decididas pelo responsável (DEC-36); CL-20 fechado. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Bloqueadores sobre significado ou lacuna da spec dependem de decisão humana (ver Questões Bloqueador).")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | Cálculo determinístico, sem LLM [REQ spec-011 §Regras]. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-33; não bloqueia o planejamento). Questões Bloqueador: nenhuma. Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Alterado em 25/10/2026 por DEC-36; texto original: "princípios BLOQUEADO: §5, §7, §9, §11. Questões Bloqueador: Q-011-01, Q-011-02.")* *(Reavaliado em 04/11/2026: passam todos os princípios, inclusive §5 Compatibilidade Moodle, validado em CL-33 (Fechado em 03/11/2026 com limitações aceitas; T075 [x]); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/011-respostas-e-m-tricas-de-question-rios/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/questionnaire-metrics.md
```

### Source Code (repository root)

```text
assistente/
└── [calculadora de métricas determinísticas]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: Q-003-04 resolvida na seção "Layout das classes" de specs/003-*/plan.md (aprovada em 26/10/2026); código em assistente/classes/domain/quiz/metrics_calculator.php.)
```

## Dependências entre specs

- Upstream: spec-007, spec-010 [REQ §Dependências].
- Downstream: 015, 016, 018. *(25/10/2026: também 019 e 021, que apresentam os estados `valid`/`no_data`/`error` e o truncamento a duas casas (DEC-36 D2).)*
- *(25/10/2026)* Eventos de origem (submissão, correção manual, reavaliação) e o evento sintético de fechamento: a lista é decisão desta spec (Q-006-07); os nomes concretos dos eventos Moodle ficam na validação (CL-33) e no plano 022.

## Fases de planejamento & gates

1. Fechar as definições matemáticas (Q-011-01/02/04) e os gatilhos (Q-011-03). *(25/10/2026: fechadas por DEC-36.)*
2. Definir o contrato ([contracts/questionnaire-metrics.md](contracts/questionnaire-metrics.md)). *(25/10/2026: contrato atualizado.)*
3. **Gate G-011**: testes com valores conhecidos (ex.: 2/3) e conferência de desvio populacional. *(25/10/2026: cenários Q1–Q14, incluindo truncamento, método de avaliação, estados e versões sem linha analisada.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-011-01 | Resolvido | Definições de `average_correctness`, `correct_count` e `incorrect_count` (uma resposta parcial conta como correta, incorreta ou nenhuma das duas?). **Resolvido por DEC-36 D1 (25/10/2026):** só respostas com nota; média da fração; contagens por fração integral e zero; parcial em nenhuma contagem; `partial_result` só para múltiplos itens corretos, `NULL` nos demais; correção manual pendente fora. *(Tipo original: "Bloqueador".)* | §Contratos; DEC-36 |
| Q-011-02 | Resolvido | (Contradição) Exemplo "2/3 = 66,66%" (truncamento, percentual) × `partial_result number(10,3)`. Não está definido se o valor é fração (0.667/0.666) ou percentual (66.666/66.667) nem se arredonda ou trunca. **Parcial (25/10/2026):** a spec define que denominador zero resulta em 0 com estado `no_data`, que zero com denominador positivo é `valid` e que falha técnica é `error` (DEC-01, DEC-24). Fração × percentual e arredondamento continuam abertos (`closure-register.md` CL-20). **Resolvido por DEC-36 D2 (25/10/2026):** percentual 0–100, truncado a três casas no armazenamento e a duas na exibição (2/3 → `66.666` → "66,66%"). *(Tipo original: "Bloqueador".)* | §Regras; spec-002; DEC-01, DEC-24, DEC-36 |
| Q-011-03 | Resolvido | Gatilhos: "respostas processadas" e "fechamento do questionário" não têm evento definido em spec-006 (Q-006-07). Há métricas parciais antes do fechamento? **Resolvido por DEC-36 D4 (25/10/2026):** recalcular após submissão, correção manual e reavaliação (eventos na fila); fechamento = `timeclose` passou, detectado pela Scheduled Task, com recalculação final; sem data de fechamento, agregados parciais. Nomes dos eventos em CL-33. *(Tipo original: "Aberto".)* | §Objetivo, §Regras; DEC-36 |
| Q-011-04 | Resolvido | Unidade do agregado (nota por tentativa? por estudante?) e tratamento de múltiplas tentativas. **Resolvido por DEC-36 D3 (25/10/2026):** um resultado final por estudante, conforme o método de avaliação do questionário, em percentual da nota máxima. *(Tipo original: "Aberto".)* | §Contratos; DEC-36 |
| Q-011-05 | Resolvido | "Todas as respostas" inclui tentativas não finalizadas? **Resolvido por DEC-36 D3 (25/10/2026):** não; só tentativas concluídas, também nas métricas por questão; pré-visualizações do professor excluídas. *(Tipo original: "Aberto".)* | §Regras; DEC-36 |
| Q-011-06 | Resolvido | Relação entre a `analysis` da 011 e a da 010. Evidência no `install_auditado.xml`: `question_result` tem `analysis_id` e `questionnaire_question_id`; `questionnaire_question` leva à `analysis` da 010 por `questionnaire_analysis`; `questionnaire_metric.analysis_id` é único. Nenhuma restrição obriga que as duas `analysis` sejam a mesma. A spec-011 só declara depender da 010. Não decidido. **Resolvido (25/10/2026):** cada execução cria uma `analysis` própria `quiz_results`, imutável, ligada à da 010 por `questionnaire_question_id`; a atual é a última bem-sucedida. Questões sem linha analisada seguem DEC-36 D5. *(Tipo original: "Aberto".)* | spec-002; spec-011; DEC-36 |
| Q-011-07 | Resolvido | *(Resolvido em 26/10/2026 por DEC-52: `partial_result` = alternativas corretas selecionadas sobre o total de corretas; 0 com qualquer incorreta; sem resposta registrada fica fora da média; independente da nota. Tipo coberto: múltipla escolha com várias respostas, `qtype_multichoice_multi_question`. Original:)* Quais tipos de questão do Moodle 5.2 têm "múltiplos itens corretos" (no mínimo, múltipla escolha com várias respostas) e como obter `total_correct_items` e `correct_items_answered` em cada um. Não bloqueia: validação em CL-33. | DEC-36 D1 |
| R-011-01 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | *(Incluído em 25/10/2026.)* Cada submissão, correção ou reavaliação cria uma nova `analysis` `quiz_results`; em turmas grandes o histórico cresce. Nenhum limite ou expurgo foi definido; medir nas tasks e tratar em spec-017, se necessário. *(04/11/2026, etapa 5a: Aceito como limitação (04/11/2026); evidência: decisão do responsável: volume, custo ou falha repetida, casos de exceção não tratados.)* | DEC-36 D4; §7 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Denominador zero → 0 com `no_data`; zero com denominador positivo é `valid`; falha técnica é `error`, nunca zero; o estado acompanha o valor no contrato [REQ spec-011 §Regras] | DEC-01, DEC-24 | Q-011-02 parcial; o contrato ([contracts/questionnaire-metrics.md](contracts/questionnaire-metrics.md)) deve levar o estado junto com o valor | Incorporada |
| Definições, escala, unidade, gatilhos e questões sem linha analisada | DEC-36 | Q-011-01 a 05 resolvidas; CL-20 fechado; §7, §9 e §11 passam | Incorporada (25/10/2026); validação em CL-33 |
| Questões e versões sem linha de análise (propagação de DEC-35 D1, sem nova análise inicial por DEC-31) | DEC-35, DEC-36 D5 | Contrato e data-model; quickstart Q11–Q12 | Incorporada (25/10/2026) |

Continuam bloqueadoras Q-011-01 (definições de acerto) e a parte aberta de Q-011-02 (fração × percentual, arredondamento), que dependem de decisão humana (`closure-register.md` CL-20). §9 continua BLOQUEADO. *(25/10/2026: deixaram de valer — Q-011-01 e Q-011-02 resolvidas por DEC-36; §9 passa.)*

O `spec.md` desta feature não foi alterado: DEC-36 afeta só o plano. Propagações pendentes, a aplicar na revisão de cada plano: 015 e 016 (consumo das métricas em percentual e dos estados), 018, 019 e 021 (estados `valid`/`no_data`/`error` e exibição truncada a duas casas), 022 (registro dos eventos de submissão, correção manual e reavaliação). *(Incluído em 25/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-36 aos planos-alvo 015, 016, 019 não feita; superada pela implementação e validação: 015: T091 [x] e T093 [x] (percentual e "sem dados → 2" no avaliador de critérios), CL-20 Fechado; 016: T095 [x] e T097 [x] (conjunto comparável com `valid`/`no_data` e métricas de questionário); 019: T115 [x] e T116 [x] (truncamento a duas casas e estados), CL-39 FECHADA em 28/10/2026; os planos-alvo não foram reabertos. Planos-alvo com citação: 018, 021, 022.)*

## Complexity Tracking

Nenhuma violação justificável.
