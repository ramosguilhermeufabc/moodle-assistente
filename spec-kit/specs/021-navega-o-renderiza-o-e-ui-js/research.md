# Research — spec-021 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: pages/controllers + Output API + renderables + Mustache; AMD só quando necessário. **Rationale**: [REQ §Contratos]. **Alternatives considered**: n/a.
- **Decision**: sem chat em linguagem natural; versão atual + histórico; fontes removidas indicadas. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 26/10/2026, DEC-46)*
- **D1**: conteúdo gerado no idioma forçado do curso, senão no padrão do site, registrado na análise, sem regeneração. **Alternatives considered**: sempre `pt_br`.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-021-01 Navegação | RESOLVIDO no nível funcional — navegação secundária do curso; mecanismo em CL-41 *(original: "ABERTO — verificar no 5.2")* | §Conformidade | — |
| Q-021-02 Mapa de páginas | RESOLVIDO — abas por consulta, relatório e configuração *(original: "ABERTO")* | spec-021; plano 018 | — |
| Q-021-03 JS | RESOLVIDO — nenhum obrigatório *(original: "ABERTO")* | spec-021 | — |
| Q-021-04 Fontes removidas | RESOLVIDO — DEC-42 D3 *(original: "ABERTO")* | Derivado na leitura | — |
| Q-021-05 Idioma | RESOLVIDO — `lang/en` + `lang/pt_br`; DEC-46 *(original: "ABERTO")* | Prática do Moodle; decisão do responsável | — |
