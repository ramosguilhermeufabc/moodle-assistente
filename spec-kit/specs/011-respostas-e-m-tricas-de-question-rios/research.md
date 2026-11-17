# Research — spec-011 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: `partial_result = correct_items_answered / total_correct_items`. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: agregados mean, desvio-padrão populacional, mínimo e máximo; no fechamento consideram todas as respostas. **Rationale**: [REQ §Contratos, §Regras]. **Alternatives considered**: n/a.
- **Decision**: sem LLM. **Rationale**: [REQ §Regras; spec-009]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 25/10/2026, DEC-36)*
- **D1**: só respostas com nota; média da fração; contagens por fração integral e zero; `partial_result` só para múltiplos itens corretos. **Alternatives considered**: parcial como incorreta; acerto com fração ≥ 0.5.
- **D2**: percentual 0–100, truncado a três casas, exibido a duas. **Alternatives considered**: fração 0–1; percentual arredondado (não reproduzem o exemplo da spec).
- **D3**: resultado final por estudante pelo método de avaliação; só tentativas concluídas. **Alternatives considered**: por tentativa.
- **D4**: recalcular após submissão, correção e reavaliação; fechamento por `timeclose` detectado pela Scheduled Task. **Alternatives considered**: calcular só no fechamento.
- **D5**: nova versão liga-se à linha analisada da mesma entrada do banco; questão sem linha só no agregado. **Alternatives considered**: excluir toda versão sem linha exata; tornar `questionnaire_question_id` anulável.

## Decididos no plano *(incluído em 25/10/2026)*
- `analysis` própria `quiz_results` por execução, imutável; estados por ausência de linha (P-06); pré-visualizações excluídas.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-011-01 Definições por questão | RESOLVIDO — DEC-36 D1 *(original: "ABERTO")* | Decisão do responsável | ver acima |
| Q-011-02 Fração × percentual; arredondamento | RESOLVIDO — DEC-36 D2 *(original: "ABERTO — decisão humana")* | Reproduz o exemplo | fração; percentual; truncar; arredondar |
| Q-011-03 Gatilhos | RESOLVIDO — DEC-36 D4 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-011-04 Unidade do agregado | RESOLVIDO — DEC-36 D3 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-011-05 Tentativas não finalizadas | RESOLVIDO — DEC-36 D3 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-011-06 Relação analysis 010/011 | RESOLVIDO — no plano *(original: "ABERTO")* | spec-002; §7 | — |
| Q-011-07 Tipos com múltiplos itens corretos | RESOLVIDO em 26/10/2026 (DEC-52); validação de páginas reais em CL-33 | Múltipla escolha com várias respostas; dados da tentativa no Moodle 5.2.4 | — |
