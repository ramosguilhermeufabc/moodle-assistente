# Research — spec-022 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: Events API para observar; Hooks somente quando houver hook apropriado e necessário. **Rationale**: [REQ §Contratos, §Conformidade]. **Alternatives considered**: n/a.
- **Decision**: observer/hook fino, delega para application services; sem LLM. **Rationale**: [REQ §Contratos, §Regras]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 26/10/2026, DEC-47)*
- **D1**: sem observer de matrícula ou papel; listas filtradas na leitura. **Alternatives considered**: observar matrícula e papéis e recalcular frequência e critérios.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-022-01 Eventos 5.2 | RESOLVIDO no nível funcional — catálogo consolidado; nomes nas validações técnicas *(original: "ABERTO — verificar")* | §Regras; decisões aprovadas | — |
| Q-022-02 Hooks | RESOLVIDO — navegação e, se confirmado, exclusão de curso *(original: "ABERTO")* | Nenhum outro necessário | — |
| Q-022-03 Dependências divergentes | RESOLVIDO — README corrigido *(original: "ABERTO")* | spec.md prevalece | — |
| Q-022-04 Filtro por curso | RESOLVIDO — DEC-45 D3 *(original: "ABERTO")* | `config.enabled` | — |
