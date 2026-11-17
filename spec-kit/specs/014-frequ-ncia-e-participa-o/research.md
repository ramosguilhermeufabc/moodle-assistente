# Research — spec-014 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: presença = participou ≥ 1 vez. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: percentual = participating / total × 100. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: referência temporal = data final/devida da atividade. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: persistir apenas resultados derivados. **Rationale**: [REQ §Conformidade]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 25/10/2026, DEC-39)*
- **D1**: módulos de atividade das quatro categorias, disponíveis ao estudante; recursos fora; participação por submissão, tentativa/nota, conclusão ou postagem. **Alternatives considered**: incluir recursos com conclusão ("visualizado").
- **D2**: data de referência com `course.enddate` como alternativa; entrada no denominador quando a data passa ou com participação antecipada. **Alternatives considered**: excluir atividades sem data; só atividades vencidas.
- **D3**: acesso ao curso no snapshot, apresentado como evidência complementar. **Alternatives considered**: registrar sem apresentar.
- **Arredondamento**: truncamento, como DEC-36 D2 e DEC-37 D2. **Alternatives considered**: arredondar.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-014-01 Acesso ao curso | PARCIAL (DEC-25, 25/10/2026): evidência de presença, fora do denominador; registro do acesso aberto *(original: "ABERTO")* *(25/10/2026: RESOLVIDO — DEC-39 D3)* | Não definido (até 25/10/2026) | — |
| Q-014-02 Sem data | RESOLVIDO — DEC-39 D2 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-014-03 Total por seção | PARCIAL: categorias da spec-014 §Contratos (avaliadas, conclusão, entrega, fóruns), uma vez por atividade; mapeamento no Moodle aberto *(original: "ABERTO")* *(25/10/2026: RESOLVIDO — DEC-39 D1)* | Não definido (até 25/10/2026) | — |
| Q-014-04 "Participou" | RESOLVIDO — DEC-39 D1 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-014-05 Arredondamento | RESOLVIDO — truncamento (DEC-39) *(original: "ABERTO — decisão humana")* | Padrão de DEC-36/37 | truncar; arredondar |
| Q-014-06 Gatilho | RESOLVIDO — DEC-39 D2 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-014-07 Tópico = seção | RESOLVIDO pelo esquema: frequência por estudante e seção (spec-002) *(original: "ABERTO")* | Não declarado na spec-014 | — |
