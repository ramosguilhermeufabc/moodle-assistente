# Data Model — spec-016

```text
processing ─1:N─ analysis (analysis_type=?) ─1:N─ pedagogical_synthesis (content text, created_at)
```
*(25/10/2026: `analysis_type = section_synthesis`; `pedagogical_synthesis` tem também `course_id`, `section_id`, `version`, `input_fingerprint` char(64), único curso+seção+versão — DEC-29.)*
- A associação ao processing é feita via `analysis.processing_id` [spec-002; REQ §Conformidade].
- Mais recente = maior `created_at`? [ESCOLHA de leitura, confirmar em Q-016-01/Q-017-01]. *(25/10/2026: a vigente é a de maior `version` por curso e seção, conforme a spec-016.)*
- Cardinalidade: `analysis ─1:N─ pedagogical_synthesis`, como em `specs/002-esquema-xmldb/data-model.md`. Cada síntese tem uma `analysis` de origem (decisão funcional de 21/10/2026: cada síntese é produzida em uma `analysis` própria), mas o esquema atual não impõe unicidade de `pedagogical_synthesis.analysis_id` (a chave é `foreign`, não `foreign-unique`).
- Pendência técnica: a intenção funcional de `analysis` própria e a cardinalidade que o esquema permite diferem. Se a implementação precisar garantir a exclusividade, isso deve ser tratado e aprovado à parte. Nenhuma restrição foi introduzida nem aprovada. *(25/10/2026: o fluxo grava uma `analysis` por versão, na mesma transação; nenhuma restrição nova no esquema.)*
- *(25/10/2026, DEC-41)* `input_fingerprint` = SHA-256 do conjunto comparável canônico; `content` em texto simples.
