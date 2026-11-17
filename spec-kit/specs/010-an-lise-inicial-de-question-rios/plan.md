# Implementation Plan: spec-010 — Análise Inicial de Questionários

**Branch**: `feature/sdd-ajustado` (feature dir `010-an-lise-inicial-de-question-rios`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/010-an-lise-inicial-de-question-rios/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026. A validação técnica do §5 (CL-02, evento de publicação; CL-33, banco de questões e slots) continua pendente e não bloqueia o planejamento; nenhum cenário do quickstart foi executado nem validado no Moodle 5.2. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-02 FECHADA 02/11/2026; CL-33 Fechado (VT) 03/11/2026; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Ao publicar um questionário (um único evento), analisar suas questões com os resumos dos materiais elegíveis da mesma seção e produzir: **coverage** do questionário e **complexity** por questão, ambos na faixa 0.0–1.0 com passo 0.1 [REQ spec-010 §Objetivo, §Contratos, §Regras]. Entradas: `question_id/version`, enunciado, alternativas, resposta indicada pelo professor e resumos dos materiais [REQ §Contratos]. Não existe requisito de tipo de raciocínio. O resultado da LLM é validado antes de persistir [REQ §Regras, §Conformidade].

*(Incluído em 25/10/2026 por DEC-35 e pelos tratamentos da revisão.)* O questionário é o `mod_quiz`. São analisadas todas as questões que possam ser resolvidas no momento da publicação, de qualquer tipo, exceto `description`: posições fixas na versão vigente na publicação e, nas posições aleatórias, as questões candidatas do filtro naquele momento (DEC-35 D1). Sem material elegível no snapshot, a complexidade é avaliada normalmente e `coverage = 0.0` é definido sem chamar a LLM (DEC-35 D2). As chamadas à LLM ocorrem sem transação aberta, e os resultados são gravados numa única transação só se todos forem válidos.

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: QuestionnaireReader (spec-004) sobre `mod_quiz` e o banco de questões; snapshot de contexto (spec-008); porta LLM (spec-009), usos `quiz_coverage` e `question_complexity`; fila/tasks (006/007). *(Alterado em 25/10/2026; texto original: "QuestionnaireReader (spec-004); snapshot de contexto (spec-008); porta LLM (spec-009); fila/tasks (006/007).")*

**Storage**: `local_assistente_analysis` (`analysis_type = quiz_initial`) + `local_assistente_questionnaire_analysis` (`coverage` number(2,1)) + `local_assistente_questionnaire_question` (`complexity` number(2,1); único `questionnaire_analysis_id,question_id,question_version`) [spec-002]. Sem campo novo (DEC-35 D2). *(Alterado em 25/10/2026; incluídos o valor de `analysis_type` e a ausência de campo novo.)*

**Testing**: LLM mockada (adapter simulado da spec-009, DEC-34 D1); validação de faixa e passo [spec-023]; ausência de dados de estudantes no `context` (DEC-34 D3). Runner em Q-023-01. *(Alterado em 25/10/2026; texto original: "LLM mockada; validação de faixa e passo [spec-023]. Runner em Q-023-01.")*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, análise assistida por LLM.

**Performance Goals**: N/A. Ver R-010-01 (pools aleatórios grandes).

**Constraints**: um evento por publicação; somente materiais da mesma seção; validação antes de persistir [REQ]. Sem transação aberta durante as chamadas à LLM [spec-007]; gravação tudo-ou-nada; o `context` contém só questões, resposta do professor e resumos de materiais, nunca tentativas ou respostas de estudantes (DEC-34 D3). *(Alterado em 25/10/2026; incluída a segunda frase.)*

**Scale/Scope**: por questionário publicado.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | Nenhum "tipo de raciocínio" foi adicionado [REQ]. |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento: estrutura de slots, referências de questão e de conjunto aleatório, versões e extração por tipo no Moodle 5.2 (`closure-register.md` CL-33; Q-004-01/05); evento de publicação em CL-02. *(Alterado em 25/10/2026; observação original: "Leitura de questões e evento de publicação não verificados (Q-010-04, Q-004-05).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-02 (Fechada em 02/11/2026) e CL-33 (Fechado em 03/11/2026 com limitações aceitas; T069 [x], T075 [x]))* |
| §6 Materiais | BLOQUEADO | PASSA | Q-008-01 foi resolvida no nível funcional pela spec-008 (DEC-26). A API de disponibilidade fica no §5 (Q-004-01). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Herda Q-008-01 (elegibilidade parcial).")* |
| §7 Integridade | PASSA | PASSA | O resultado inválido não é persistido [REQ]. Gravação tudo-ou-nada numa transação; falha não deixa resultado parcial. *(25/10/2026: incluída a segunda frase.)* |
| §8 Idempotência | BLOQUEADO | PASSA | Q-006-04 resolvida no nível funcional (DEC-31): um único evento de publicação por questionário; validação em CL-02. A retentativa refaz a análise no mesmo processamento (spec-007, DEC-16). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Herda Q-006-04."; última frase incluída no mesmo dia.)* |
| §9 Testabilidade | PASSA | PASSA | Faixa e passo verificáveis. Cenários Q1–Q12 do [quickstart.md](quickstart.md). *(25/10/2026: incluída a segunda frase.)* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | Q-010-01 e Q-010-02 decididas pelo responsável (DEC-35). *(25/10/2026: observação original "—".)* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | Cobertura e complexidade são interpretação linguística via porta LLM (spec-009). Sem material elegível, `coverage = 0.0` é determinístico, sem LLM (DEC-35 D2). Nenhum dado de estudante é enviado (DEC-34 D3). *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0; duas últimas frases incluídas no mesmo dia.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-02 e CL-33; não bloqueia o planejamento). Questões Bloqueador: nenhuma; Q-010-04 resolvida por DEC-31, com validação técnica em Q-006-01/CL-02. *(Alterado em 25/10/2026; texto anterior: "princípios BLOQUEADO: §5, §8. Questões Bloqueador: Q-010-04.")* *(Alterado em 25/10/2026; texto original: "§2, §5, §6, §8".)* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: passam todos os princípios, inclusive §5 Compatibilidade Moodle, validado em CL-02 (Fechada em 02/11/2026) e CL-33 (Fechado em 03/11/2026 com limitações aceitas; T069 [x], T075 [x]); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/010-an-lise-inicial-de-question-rios/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/questionnaire-initial-analysis.md
```

### Source Code (repository root)

```text
assistente/
└── [handler de análise inicial de questionário]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: Q-003-04 resolvida na seção "Layout das classes" de specs/003-*/plan.md (aprovada em 26/10/2026); código em assistente/classes/application/quiz/initial_analysis_handler.php.)
```

## Dependências entre specs

- Upstream: spec-004, spec-008, spec-009 [REQ §Dependências].
- Downstream: 011 (`question_result` referencia `questionnaire_question`), 017, 018. 018, 019 e 021 distinguem o `coverage = 0.0` "sem materiais" da cobertura real zero (DEC-35 D2). *(25/10/2026: incluída a última frase.)*

## Fases de planejamento & gates

1. Resolver os tipos de questão suportados (Q-010-01). O evento de publicação está definido por DEC-31 (spec-006). *(Alterado em 25/10/2026; texto original: "Resolver os tipos de questão suportados e o evento de publicação (Q-010-01, Q-010-04).")* *(25/10/2026: Q-010-01 resolvida por DEC-35 D1.)*
2. Definir o contrato de entrada/saída ([contracts/questionnaire-initial-analysis.md](contracts/questionnaire-initial-analysis.md)) e a validação de faixa e passo (Q-010-03). *(25/10/2026: contrato atualizado; Q-010-03 resolvida pelo contrato da porta da spec-009.)*
3. **Gate G-010**: cenários do [quickstart.md](quickstart.md). *(25/10/2026: Q1–Q12, testáveis com o adapter simulado.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-010-01 | Resolvido | Tipos de questão suportados. "Alternativas" e "resposta do professor" podem não existir em todos os tipos. **Resolvido por DEC-35 D1 (25/10/2026):** todas as questões resolvíveis na publicação, de qualquer tipo, exceto `description`; posições fixas na versão vigente; posições aleatórias com as candidatas do filtro naquele momento; alternativas e resposta do professor quando o tipo as tiver. Validação técnica em CL-33. *(Tipo original: "Aberto".)* | §Contratos, §Conformidade; DEC-35 |
| Q-010-02 | Resolvido | Comportamento sem materiais elegíveis na seção (coverage = ? / análise abortada?). **Resolvido por DEC-35 D2 (25/10/2026):** a complexidade é avaliada normalmente; `coverage = 0.0` determinístico, sem LLM; estado "sem materiais" derivado do snapshot sem materiais, sem campo novo. *(Tipo original: "Aberto".)* | §Contratos; DEC-35 |
| Q-010-03 | Resolvido | Valor fora da faixa ou do passo: rejeitar (falha/retry) ou normalizar? A spec só diz "validar". **Resolvido (25/10/2026) pelo contrato aprovado da porta (plano 009):** `invalid_response`; a tentativa falha (spec-007, DEC-32); nada é normalizado nem persistido. *(Tipo original: "Aberto".)* | §Regras, §Conformidade; plano 009 |
| Q-010-04 | Resolvido | O que é "publicação" e qual evento Moodle a sinaliza (herda Q-006-01/04). **Resolvido por DEC-31 (25/10/2026):** publicação = o questionário passa a ficar visível aos estudantes pela primeira vez, seja criado já visível, seja tornado visível depois; eventos candidatos `course_module_created`/`course_module_updated`; verificação no Moodle 5.2 em Q-006-01/CL-02. *(Tipo original: "Bloqueador".)* | §Regras; DEC-31 |
| Q-010-05 | Resolvido | Alterações no questionário após a publicação: sem novo evento ("um único evento")? **Resolvido por DEC-31 (25/10/2026):** alterações depois da publicação não geram novo evento nem nova análise inicial. *(Tipo original: "Aberto".)* | §Regras; DEC-31 |
| Q-010-06 | Resolvido | Valor de `analysis_type` para esta análise. **Resolvido (25/10/2026):** `quiz_initial` (cabe em `char(20)`). *(Tipo original: "Aberto".)* | spec-002 |
| Q-010-07 | Resolvido | Derivação da seção a partir do `cmid` do questionário pela regra da spec-004 (Q-004-07) e comportamento quando a derivação não é inequívoca. **Resolvido no nível funcional (25/10/2026):** o questionário tem um único `cmid`; a seção vem do contrato da spec-004; se não puder ser resolvida, é erro técnico e a tentativa falha, e não "sem materiais" (P-06). A API continua em validação (Q-004-07). *(Tipo original: "Aberto".)* | spec-010 §Regras; spec-004; P-06 |
| R-010-01 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | *(Incluído em 25/10/2026.)* Posições aleatórias com muitas candidatas aumentam o número de chamadas à LLM e a duração do processamento; o ciclo conclui o evento em andamento (DEC-32 D5). Nenhum limite novo foi criado; medir nas tasks. *(04/11/2026, etapa 5a: Aceito como limitação (04/11/2026); evidência: decisão do responsável: volume, custo ou falha repetida, casos de exceção não tratados.)* | DEC-35 D1; DEC-32 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas às specs (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Seção da análise derivada do `cmid` pela regra da spec-004 [REQ spec-010 §Regras] | DEC-13 | Já refletida em Q-010-07 (plano revisto em 22/10/2026) | Incorporada |
| Elegibilidade de material para o contexto (spec-008) | DEC-26 | §6 passa; Q-010-02 (sem materiais elegíveis) continua aberta | Incorporada |
| Identidade de evento e idempotência (spec-006) | DEC-08, DEC-21 | Q-010-04 e §8 continuam bloqueados por Q-006-01/Q-006-04 | Validação pendente *(04/11/2026, revisão dos planos: CL-02 FECHADA em 02/11/2026.)* |
| Publicação de questionário = primeira visibilidade aos estudantes; alterações posteriores sem novo evento nem nova análise [REQ spec-010 §Regras] | DEC-31 | Q-010-04 e Q-010-05 resolvidas; §8 passa | Incorporada (25/10/2026); validação em CL-02 |
| Questões analisadas; ausência de materiais | DEC-35 | Q-010-01 e Q-010-02 resolvidas; contrato, data-model e quickstart Q5–Q12 | Incorporada (25/10/2026); validação em CL-33 |
| Adapter simulado; nenhum dado de estudante na requisição | DEC-34 D1, D3 | Testing, Constraints, §13; quickstart Q11 | Incorporada (25/10/2026) |
| Faixa e passo inválidos = `invalid_response` (contrato da porta) | — (plano 009 aprovado) | Q-010-03 resolvida | Incorporada (25/10/2026) |

O `spec.md` desta feature não foi alterado: DEC-35 afeta só o plano. Propagações pendentes, a aplicar na revisão de cada plano: 004 (Q-004-05: questionário = `mod_quiz`), 011 (vínculo dos resultados às questões analisadas), 018, 019 e 021 (apresentação do estado "sem materiais"). *(Incluído em 25/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-35 feita: todos os planos-alvo (004, 011, 018, 019, 021) citam a decisão nos artefatos de plano.)*

## Complexity Tracking

Nenhuma violação justificável.
