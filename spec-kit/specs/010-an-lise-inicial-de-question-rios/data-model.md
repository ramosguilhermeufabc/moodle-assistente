# Data Model — spec-010

```text
analysis (course_id, processing_id, analysis_type=quiz_initial, cmid=questionário)
  └─1:1─ questionnaire_analysis (coverage number(2,1))
            └─1:N─ questionnaire_question (question_id, question_version, complexity number(2,1))
```
*(25/10/2026: `analysis_type=? [Q-010-06]` substituído por `quiz_initial`.)*
- `coverage`, `complexity` ∈ {0.0, 0.1, …, 1.0} [REQ §Regras].
- Único: `questionnaire_analysis_id, question_id, question_version` [spec-002].
- Contexto usado: `processing_context` do processing (spec-008).
- *(25/10/2026, DEC-35)* Linhas de `questionnaire_question`: questões resolvíveis na publicação, exceto `description`; posições aleatórias contribuem com as candidatas do filtro naquele momento.
- *(25/10/2026, DEC-35 D2)* Sem material elegível: `coverage = 0.0` sem LLM; o estado "sem materiais" é derivado do `processing_context` sem materiais. Nenhum campo novo.
- *(25/10/2026)* Gravação das três tabelas numa única transação, tudo-ou-nada.
