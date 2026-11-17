# Contract — Análise inicial de questionário (spec-010)

**Gatilho**: evento "questionário publicado" (exatamente um; spec-006). Questionário = `mod_quiz`; publicação conforme DEC-31. *(25/10/2026: incluída a segunda frase.)*

**Questões analisadas** *(incluído em 25/10/2026, DEC-35 D1)*: todas as resolvíveis no momento da publicação, de qualquer tipo, exceto `description`. Posição fixa: a versão vigente na publicação. Posição aleatória: as questões candidatas do filtro naquele momento. Uma questão que apareça em mais de uma posição gera uma única linha por `question_id, question_version` (índice único da spec-002).

**Entrada (por questão)** [REQ §Contratos]: `question_id`, `question_version`, enunciado, alternativas, resposta indicada pelo professor. **Contexto**: resumos dos materiais elegíveis da mesma seção (snapshot de spec-008). *(25/10/2026: o enunciado é sempre enviado; alternativas e resposta do professor, quando o tipo as tiver (DEC-35 D1). O `context` não contém tentativas, respostas nem identificadores de estudantes (DEC-34 D3).)*

**Seção** *(incluído em 25/10/2026)*: derivada do `cmid` do questionário pelo contrato da spec-004. Seção não resolvida ⇒ erro técnico, a tentativa falha (P-06); não é tratada como "sem materiais".

**Chamadas à porta LLM** *(incluído em 25/10/2026)*: uma `quiz_coverage` por questionário e uma `question_complexity` por questão (contrato da spec-009). Sem material elegível no snapshot: nenhuma chamada `quiz_coverage`; `coverage = 0.0` determinístico (DEC-35 D2).

**Saída** [REQ §Contratos, §Regras]:
| Campo | Nível | Domínio |
|---|---|---|
| `coverage` | questionário | 0.0–1.0, passo 0.1 |
| `complexity` | questão | 0.0–1.0, passo 0.1 |

**Validação**: antes de persistir; resultado inválido ⇒ nada persistido (tratamento: Q-010-03). *(25/10/2026: Q-010-03 resolvida — valor fora da faixa ou do passo é `invalid_response`, a tentativa falha e nada é normalizado.)*

**Persistência** *(incluído em 25/10/2026)*: chamadas à LLM sem transação aberta (spec-007); `analysis` (`analysis_type = quiz_initial`), `questionnaire_analysis` e todas as `questionnaire_question` gravadas numa única transação, só se todos os resultados forem válidos. Qualquer falha ⇒ nenhum resultado parcial; a retentativa refaz a análise no mesmo processamento (spec-007, DEC-16, DEC-32).

**Estado "sem materiais"** *(incluído em 25/10/2026, DEC-35 D2)*: derivado do snapshot de contexto (`processing_context`) sem materiais; sem campo novo. As specs 018, 019 e 021 o distinguem de uma cobertura real igual a zero.
