# Quickstart (validação) — spec-010

> Nada foi executado. LLM mockada (adapter simulado da spec-009), exceto Q12, que é validação técnica no Moodle 5.2 (CL-33).

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Publicar um questionário com 3 questões | 1 análise: coverage + 3 complexities | §Contratos |
| Q2 | Mock devolve 0.35 ou 1.2 | Rejeitado e não persistido | §Regras |
| Q3 | Materiais em outra seção | Não aparecem no contexto | §Objetivo; spec-008 |
| Q4 | Duas versões da mesma questão | Linhas distintas por `question_version` | spec-004 §Regras |
| Q5 | Questionário com uma questão `description` e duas avaliáveis | 2 linhas de `questionnaire_question`; `description` fora | DEC-35 D1 |
| Q6 | Posição aleatória cujo filtro tem 4 candidatas, uma delas também em posição fixa | Uma linha por questão e versão (4 no total do pool, sem duplicata) | DEC-35 D1; spec-002 |
| Q7 | Questão de tipo sem alternativas nem resposta do professor (ex.: dissertativa) | Analisada só com o enunciado | DEC-35 D1 |
| Q8 | Seção sem material elegível | Complexidades avaliadas; `coverage = 0.0`; nenhuma chamada `quiz_coverage`; snapshot sem materiais | DEC-35 D2 |
| Q9 | Mock falha na 3ª de 4 chamadas `question_complexity` | Nenhuma linha gravada; tentativa falha; a retentativa refaz tudo | spec-007; DEC-16 |
| Q10 | Seção do `cmid` não resolvida | Erro técnico, tentativa falha; não vira `coverage = 0.0` | spec-004; P-06 |
| Q11 | Inspecionar o `context` enviado | Sem tentativas, respostas nem identificadores de estudantes | DEC-34 D3 |
| Q12 | Moodle 5.2: questionário com posições fixas e aleatórias, várias versões | Reader resolve versões vigentes e candidatas do filtro; extrai enunciado, alternativas e resposta por tipo | CL-33; Q-004-01/05 |

*(Q5–Q12 incluídos em 25/10/2026, depois de DEC-34, DEC-35 e dos tratamentos da revisão.)*
