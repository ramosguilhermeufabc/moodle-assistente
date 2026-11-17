# Research — spec-010 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: coverage por questionário e complexity por questão, ambos 0.0–1.0 com passo 0.1. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: entrada = questão (id+versão), enunciado, alternativas, resposta do professor e resumos dos materiais elegíveis da mesma seção. **Rationale**: [REQ §Contratos]. **Alternatives considered**: n/a.
- **Decision**: sem classificação de tipo de raciocínio. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 25/10/2026)*
- **Decision**: questões resolvíveis na publicação, qualquer tipo, exceto `description`; fixas na versão vigente; aleatórias com as candidatas do filtro. **Rationale**: DEC-35 D1. **Alternatives considered**: só posições fixas; lista restrita de tipos.
- **Decision**: sem material elegível, complexidade normal e `coverage = 0.0` determinístico, estado derivado do snapshot. **Rationale**: DEC-35 D2; P-06 (sem campo novo se o estado é recuperável). **Alternatives considered**: abortar sem resultado; aguardar materiais.

## Decididos no plano *(incluído em 25/10/2026)*
- **Decision**: valor fora de faixa/passo = `invalid_response`, sem normalizar. **Rationale**: contrato da porta (plano 009 aprovado); DEC-32. **Alternatives considered**: normalizar (descartado: a porta não corrige valores).
- **Decision**: gravação tudo-ou-nada, LLM sem transação aberta. **Rationale**: spec-007; constituição §7. **Alternatives considered**: gravação parcial por questão.
- **Decision**: `analysis_type = quiz_initial`; questionário = `mod_quiz`; seção não resolvida = erro técnico. **Rationale**: spec-002; spec-010 exige `question_id/version` e tipo de questão (banco de questões); spec-004; P-06. **Alternatives considered**: n/a.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-010-01 Tipos de questão | RESOLVIDO — DEC-35 D1 *(original: "ABERTO")* | Decisão do responsável | ver acima |
| Q-010-02 Sem materiais | RESOLVIDO — DEC-35 D2 *(original: "ABERTO")* | Decisão do responsável | ver acima |
| Q-010-03 Fora de faixa | RESOLVIDO — `invalid_response` *(original: "ABERTO — decisão humana")* | Contrato aprovado da spec-009 | rejeitar; normalizar |
| Q-010-04 Publicação | RESOLVIDO — DEC-31 *(original: "ABERTO")* | Herda Q-006 | — |
| Q-010-05 Alterações pós-publicação | RESOLVIDO — DEC-31 *(original: "ABERTO")* | Sem novo evento nem nova análise | — |
| Q-010-06 `analysis_type` | RESOLVIDO — `quiz_initial` *(original: "ABERTO")* | spec-002 `char(20)` | — |
