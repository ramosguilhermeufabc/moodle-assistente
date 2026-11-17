# Data Model — spec-013

```text
analysis (course_id, processing_id, analysis_type=? , cmid=fórum, summary=? [Q-013-01])
  └─1:N─ forum_difficulty (discussion_id → forum_discussions, description text NOT NULL)
```
*(25/10/2026: `analysis_type = forum_analysis`; `summary` = resumo do fórum — Q-013-01 resolvida, sem campo novo.)*

Regras:
- Nova análise por postagem ou interação no fórum: o histórico é preservado [REQ §Regras; DEC-03]. Mecanismo de versão: Q-017-01. *(Alterado em 25/10/2026; texto original: "Nova análise por atualização relevante".)*
- Contexto: materiais elegíveis da mesma seção (spec-008).
- *(25/10/2026, DEC-38)* Cada `analysis` é um snapshot completo do fórum: resumo + 0..N `forum_difficulty` por discussão, inclusive as copiadas sem alteração das discussões não afetadas. Gravação atômica.
- *(25/10/2026)* Snapshot atual = `analysis` `forum_analysis` bem-sucedida mais recente do fórum. Dificuldades removidas do atual permanecem nas análises antigas.
- *(25/10/2026)* Discussão excluída: some dos snapshots seguintes (reconstrução, DEC-38 D2); o tratamento das linhas históricas que a referenciam fica em CL-15.
