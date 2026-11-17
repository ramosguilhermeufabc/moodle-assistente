# Research — spec-020 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: `moodleform`/formslib para configuração, critérios e filtros. **Rationale**: [REQ §Contratos, §Conformidade]. **Alternatives considered**: HTML/POST manual (vetado pela spec).
- **Decision**: fluxo form → validation → application service; autorização antes de persistir. **Rationale**: [REQ §Contratos, §Regras]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 26/10/2026, DEC-45)*
- **D1**: capability `local/assistente:manage` para gravar. **Alternatives considered**: reutilizar `view`.
- **D2**: somente leitura para professor; correção por administrador só para novas avaliações, com limites preservados no contexto. **Alternatives considered**: imutabilidade absoluta.
- **D3**: desabilitado por padrão; sincronização inicial ao habilitar e reabilitar; desabilitar preserva o histórico. **Alternatives considered**: sem sincronização inicial.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-020-01 Campos de configuração | RESOLVIDO — `enabled` e critérios *(original: "ABERTO")* | spec-002 | — |
| Q-020-02 Filtros | RESOLVIDO — grupo, seção, versão/data *(original: "ABERTO")* | DEC-43, DEC-44 | — |
| Q-020-03 Critérios pós-definição | RESOLVIDO — DEC-45 D2 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-020-04 Faixas | RESOLVIDO — DEC-40 D1 *(original: "ABERTO")* | Herda 005 | — |
| Q-020-05 Intervalo da task | RESOLVIDO — DEC-32 D1 *(original: "ABERTO")* | Agendamento nativo | — |
