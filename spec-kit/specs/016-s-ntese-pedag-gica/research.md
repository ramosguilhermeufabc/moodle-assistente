# Research — spec-016 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: síntese via LLM só quando houver nova informação relevante, refletindo a mais recente. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: associada ao processing que a gerou. **Rationale**: [REQ §Conformidade]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 25/10/2026, DEC-41)*
- **D1**: estatísticas por atividade e de frequência da seção; sem índice único. **Alternatives considered**: média simples das atividades (ponderação implícita).
- **D2**: conjunto comparável = conjunto enviado, com o texto do resumo do fórum. **Alternatives considered**: só as dificuldades do fórum.
- **D3**: eventos por (curso, seção) após cada análise a montante; sem espera; síntese só com resultado pedagógico. **Alternatives considered**: permitir síntese só com materiais.

## Decididos no plano *(incluído em 25/10/2026)*
- Critérios fora; fingerprint SHA-256 sobre JSON canônico; `max + 1` sob Lock API; falha não cria versão; `section_synthesis`; texto simples escapado.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-016-01 Relevância | RESOLVIDO — DEC-41 D2 *(original: "ABERTO — decisão humana")* | Decisão do responsável | — |
| Q-016-02 Escopo | RESOLVIDO — curso + seção (DEC-13) *(original: "ABERTO")* | spec-016 | curso; seção; atividade |
| Q-016-03 Formato | RESOLVIDO — texto simples *(original: "ABERTO")* | Segurança | — |
| Q-016-04 Falha LLM | RESOLVIDO — sem versão; vigente permanece *(original: "ABERTO")* | spec-016 | — |
| Q-016-05 Privacidade | RESOLVIDO — DEC-34 D3, DEC-41 D1 *(original: "ABERTO")* | Só agregados | — |
