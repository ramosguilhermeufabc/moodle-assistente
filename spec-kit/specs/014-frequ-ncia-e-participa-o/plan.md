# Implementation Plan: spec-014 — Frequência e Participação

**Branch**: `feature/sdd-ajustado` (feature dir `014-frequ-ncia-e-participa-o`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/014-frequ-ncia-e-participa-o/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026, conforme DEC-39 (D1-A, D2-A, D3-A, truncamento, estados e histórico). A validação técnica do §5 (CL-36) continua pendente e R-014-01 fica como risco documentado; nenhum dos dois bloqueia o planejamento. Nenhum cenário do quickstart foi executado nem validado no Moodle 5.2. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-36 Fechado (VT) 03/11/2026; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Calcular a frequência por tópico/atividades a partir de acesso ao curso, atividades avaliadas/conclusão/entrega e participação em fóruns [REQ spec-014 §Objetivo, §Contratos]. Regras [REQ §Regras]:
- presença = participou pelo menos uma vez;
- percentual = `participating_activities / total_activities * 100` (ex.: 1/3 = 33,33%);
- referência temporal = data final/devida da atividade.

Persistir apenas os resultados derivados em `local_assistente_frequency` e `local_assistente_frequency_activity` [REQ §Contratos, §Conformidade], sem LLM (spec-009).

*(Incluído em 25/10/2026 por DEC-39 e pelos tratamentos da revisão; DEC-25 não muda.)* Entram no denominador os módulos de atividade (não recursos) das quatro categorias, disponíveis ao estudante (DEC-26); participação = submissão, tentativa concluída ou nota, conclusão completa/aprovada/reprovada, ou postagem no fórum (D1). A data de referência é a de entrega/encerramento, senão `course.enddate`; sem nenhuma, a atividade fica fora. A atividade entra no denominador do estudante quando a data passa ou quando ele já participou (D2). O acesso ao curso fica no snapshot como evidência complementar (D3). Percentual truncado a três casas, exibido a duas.

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: APIs Moodle de completion/atividade/fórum, com DML quando necessário [REQ §Conformidade]; ParticipationReader (spec-004); tasks (spec-007). *(25/10/2026: eventos de participação na fila e detecção de datas vencidas pela Scheduled Task, DEC-39 D2; disponibilidade por usuário conforme DEC-26.)*

**Storage**: `frequency` (único `analysis_id,student_id,section_id`), `frequency_activity` (único `frequency_id,activity_id`; `reference_date`) [spec-002]. *(25/10/2026: `analysis.analysis_type = frequency`; acesso ao curso no snapshot de contexto; sem campo novo.)*

**Testing**: cálculos [spec-023]. Runner em Q-023-01. *(25/10/2026: também elegibilidade, recursos excluídos, disponibilidade, datas de referência, participação antecipada, truncamento e estados.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, cálculo determinístico.

**Performance Goals**: N/A.

**Constraints**: somente resultados derivados persistidos; sem LLM [REQ]. Percentual nunca acima de 100; truncamento, nunca arredondamento (DEC-39). *(25/10/2026: incluída a segunda frase.)*

**Scale/Scope**: estudante × seção × atividade.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — *(25/10/2026: só as quatro categorias da spec-014; DEC-25 sem alteração.)* |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-36): conclusão e estados, submissão, atividade × recurso, disponibilidade por usuário, campos de data, postagens, último acesso e eventos no Moodle 5.2. *(Alterado em 25/10/2026; observação original: "APIs de completion e participação não verificadas.")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-36 (Fechado em 03/11/2026 com limitações aceitas; T090 [x]))* |
| §6 Materiais | N/A | N/A | — *(25/10/2026: recursos didáticos ficam fora do denominador, DEC-39 D1.)* |
| §7 Integridade | BLOQUEADO | PASSA | Cada execução é uma `analysis` `frequency` imutável; recalcular cria outra e preserva as anteriores; a atual é a última bem-sucedida. A política geral segue em Q-017-01. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Recalcular × preservar (Q-017-01).")* |
| §8 Idempotência | PASSA | PASSA | Únicos do esquema. |
| §9 Testabilidade | BLOQUEADO | PASSA | Q-014-01 a 06 resolvidas por DEC-39 e pelos tratamentos; cenários Q1–Q14 do [quickstart.md](quickstart.md). *(Alterado em 25/10/2026; texto anterior: "BLOQUEADO \| O papel do acesso ao curso no percentual e as categorias elegíveis estão definidos (DEC-25, spec-014). Continuam indefinidos o registro do acesso como evidência (Q-014-01), as atividades sem data (Q-014-02), o mapeamento das categorias (Q-014-03), o critério de "participou" (Q-014-04) e o arredondamento (Q-014-05)."; observação original: "Papel do "acesso ao curso", atividades sem data e arredondamento indefinidos (Q-014-01/02/05).")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | Decisões humanas tomadas (DEC-39); CL-04 fechado no nível funcional. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Bloqueadores sobre significado ou lacuna da spec dependem de decisão humana (ver Questões Bloqueador).")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | Cálculo determinístico, sem LLM [REQ spec-009 §Regras]. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-36; não bloqueia o planejamento). Questões Bloqueador: nenhuma. Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Alterado em 25/10/2026 por DEC-39; texto original: "princípios BLOQUEADO: §5, §7, §9, §11. Questões Bloqueador: Q-014-01, Q-014-02, Q-014-05.")* *(Reavaliado em 04/11/2026: passam todos os princípios, inclusive §5 Compatibilidade Moodle, validado em CL-36 (Fechado em 03/11/2026 com limitações aceitas; T090 [x]); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/014-frequ-ncia-e-participa-o/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/frequency.md
```

### Source Code (repository root)

```text
assistente/
└── [calculadora de frequência]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: Q-003-04 resolvida na seção "Layout das classes" de specs/003-*/plan.md (aprovada em 26/10/2026); código em assistente/classes/domain/frequency/frequency_calculator.php.)
```

## Dependências entre specs

- Upstream: spec-004, spec-007 [REQ §Dependências].
- Downstream: 015, 016, 018. *(25/10/2026: também 019 e 021, que apresentam o acesso ao curso como evidência complementar, os estados e a exibição a duas casas; 022 registra os eventos de participação.)*

## Fases de planejamento & gates

1. Fechar as definições: participação por tipo, papel do acesso ao curso, atividades sem data (Q-014-01/02/04). *(25/10/2026: DEC-39 D1–D3.)*
2. Fechar o arredondamento e a escala (Q-014-05). *(25/10/2026: truncamento, DEC-39.)*
3. **Gate G-014**: 1/3 ⇒ 33,33% (forma persistida: Q-014-05); presença com ≥ 1 participação. *(25/10/2026: persistido `33.333`; cenários Q1–Q14.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-014-01 | Resolvido | Como "acesso ao curso" entra num percentual calculado por atividades. **Parcial (25/10/2026):** o acesso ao curso é evidência de presença e não entra em `total_activities` (DEC-25, propagada à spec-014). Isso é coerente com o esquema: `frequency_activity.activity_id` referencia `course_modules`, e o acesso ao curso não é módulo. **Continua aberto:** onde e como o acesso ao curso é registrado ou apresentado como evidência de presença, porque `frequency` e `frequency_activity` não têm campo para isso. **Resolvido por DEC-39 D3 (25/10/2026):** o snapshot registra, por estudante, se acessou o curso e a data do último acesso até o cálculo; 018, 019 e 021 o apresentam como evidência complementar. Sem campo novo. *(Tipo original: "Bloqueador".)* | §Contratos, §Regras; DEC-25; DEC-39; spec-002 |
| Q-014-02 | Resolvido | Atividades sem data final/devida (`reference_date` NOT NULL). **Resolvido por DEC-39 D2 (25/10/2026):** sem data de entrega ou encerramento, usa-se `course.enddate`; sem nenhuma, a atividade fica fora e isso consta no contexto do processamento. *(Tipo original: "Bloqueador".)* | §Regras; spec-002; DEC-39 |
| Q-014-03 | Resolvido | Quais atividades compõem `total_activities` por seção. **Parcial (25/10/2026):** categorias explicitamente elegíveis pela spec-014 §Contratos: atividades avaliadas, atividades com conclusão, atividades com entrega e participação em fóruns. Cada atividade é um `course_modules` e conta uma vez por estudante e seção (índice único `frequency_id,activity_id`, spec-002; DEC-25). **Continua aberto:** o mapeamento dessas categorias para módulos e configurações do Moodle 5.2 (por exemplo, se recursos com conclusão contam). **Resolvido por DEC-39 D1 (25/10/2026):** módulos de atividade das quatro categorias, disponíveis ao estudante (DEC-26); recursos fora, mesmo com conclusão. *(Tipo original: "Aberto"; depois "Parcial".)* | §Contratos, §Regras; DEC-25; DEC-39; spec-002 |
| Q-014-04 | Resolvido | O que é "participou" para cada tipo (entrega, conclusão, post). **Resolvido por DEC-39 D1 (25/10/2026):** submissão enviada; tentativa concluída ou nota atribuída; conclusão completa, aprovada ou reprovada; ao menos uma postagem no fórum — basta um. *(Tipo original: "Aberto".)* | §Contratos; DEC-39 |
| Q-014-05 | Resolvido | (Contradição) O exemplo "33,33%" (2 casas, truncamento) × `percentage number(10,3)` (3 casas) — arredondamento indefinido. **Parcial (25/10/2026):** a spec define que `total_activities` zero resulta em 0 com estado `no_data`, que zero com denominador positivo é `valid` e que falha técnica é `error` (DEC-01, DEC-24). O arredondamento continua aberto. **Resolvido por DEC-39 (25/10/2026):** truncado a três casas no armazenamento e a duas na exibição, como DEC-36 D2 e DEC-37 D2 (1/3 → `33.333` → "33,33%"). *(Tipo original: "Bloqueador".)* | §Regras; spec-002; DEC-01, DEC-24, DEC-39 |
| Q-014-06 | Resolvido | Gatilho e periodicidade do cálculo; atividades antes do prazo. **Resolvido por DEC-39 D2 (25/10/2026):** eventos de participação recalculam o estudante no curso; a Scheduled Task detecta datas de referência vencidas e recalcula o curso; a atividade entra no denominador quando a data passa ou quando o estudante já participou; nunca acima de 100%. *(Tipo original: "Aberto".)* | §Regras; DEC-39 |
| Q-014-07 | Resolvido | "Tópico" = seção (`section_id`)? **Resolvido pelo esquema (25/10/2026):** `local_assistente_frequency` é a "Frequencia consolidada por estudante e secao", com unicidade `analysis_id,student_id,section_id` (spec-002, inventário). *(Tipo original: "Aberto".)* | §Objetivo; spec-002 |
| R-014-01 | Mitigado (comportamento da DEC-39 D2) *(tipo original: "Risco")* | *(Incluído em 25/10/2026.)* Em curso sem `course.enddate`, atividades sem prazo próprio (frequentemente fóruns) ficam fora do cálculo, o que pode reduzir a categoria "participação em fóruns". Comportamento decidido (DEC-39 D2); a condição consta no contexto do processamento para transparência. *(04/11/2026, etapa 5a: Mitigado (comportamento da DEC-39 D2); evidência: `activities_without_date` no contexto do processamento (`assistente/classes/application/frequency/frequency_handler.php`).)* | DEC-39 D2 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| `total_activities` zero → 0 com `no_data`; zero válido; erro técnico como `error` [REQ spec-014 §Regras] | DEC-01, DEC-24 | Q-014-05 parcial (arredondamento aberto) | Incorporada |
| Acesso ao curso como evidência de presença, separado do denominador; categorias elegíveis da spec-014; sem duplicar atividade [REQ spec-014 §Contratos, §Regras] | DEC-25 | Propagada em 25/10/2026, restrita às categorias que a spec-014 já lista; Q-014-01 e Q-014-03 parciais; Q-014-07 resolvida pelo esquema. Q-014-02, Q-014-04 e Q-014-06 continuam abertas | Incorporada *(estado original: "**Não propagada à spec** (`closure-register.md` CL-04: categorias elegíveis abertas). Q-014-01 e Q-014-02 continuam bloqueadoras \| Bloqueada")* |
| Mapeamento, participação, datas, acesso ao curso e arredondamento | DEC-39 | Q-014-01 a 06 resolvidas; CL-04 fechado no nível funcional; §7, §9 e §11 passam | Incorporada (25/10/2026); validação em CL-36 |

§9 continua BLOQUEADO (Q-014-01, Q-014-02 e o arredondamento de Q-014-05). *(25/10/2026: deixou de valer — resolvidas por DEC-39; §9 passa.)*

O `spec.md` desta feature não foi alterado: DEC-39 afeta só o plano e não altera DEC-25. Propagações pendentes, a aplicar na revisão de cada plano: 015 (percentual de frequência comparado ao critério; estados), 016 (frequência por seção na síntese), 018, 019 e 021 (acesso ao curso como evidência complementar, estados, exibição a duas casas), 022 (eventos de participação). *(Incluído em 25/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-39 feita: todos os planos-alvo (015, 016, 018, 019, 021, 022) citam a decisão nos artefatos de plano.)*

## Complexity Tracking

Nenhuma violação justificável.
