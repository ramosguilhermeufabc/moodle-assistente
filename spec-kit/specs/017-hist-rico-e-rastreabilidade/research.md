# Research — spec-017 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: cadeia de rastreabilidade source → event → processing → attempt → snapshot → analysis → result → report. **Rationale**: [REQ §Contratos]. **Alternatives considered**: n/a.
- **Decision**: preservar o estado anterior; a remoção de fonte não apaga histórico; todos os materiais associados; timestamp e versão disponíveis. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: só tabelas do XMLDB e transações DML. **Rationale**: [REQ §Conformidade]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 26/10/2026, DEC-42)*
- **D1**: exportação e exclusão por estudante (Privacy API), sem presumir anonimato dos textos. **Alternatives considered**: não atender às solicitações (fora da conformidade do Moodle).
- **D2**: exclusão do curso remove os dados do plugin. **Alternatives considered**: manter histórico órfão.
- **D3**: fonte removida preserva o histórico; remoção derivada na leitura. **Alternatives considered**: expurgar histórico derivado.
- **D4**: sem expurgo automático. **Alternatives considered**: prazo de retenção agora.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-017-01 Preservação | RESOLVIDO — `analysis` imutável por execução *(original: "ABERTO — decisão humana")* | Planos 010–016 aprovados | nova analysis por atualização; outra |
| Q-017-02 Versões no snapshot | RESOLVIDO — `context_json` *(original: "ABERTO")* | A spec exige registro, não FK | — |
| Q-017-03 Fonte removida × FK | RESOLVIDO — DEC-42 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-017-04 result → report | RESOLVIDO no escopo da 017 — `analysis_id` no `snapshot_json` *(original: "ABERTO")* | Fecha a cadeia sem campo | — |
| Q-017-05 Dependências divergentes | RESOLVIDO — README corrigido *(original: "ABERTO")* | spec.md prevalece | — |
| Q-017-06 Retenção | RESOLVIDO — DEC-42 D4 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-017-07 Dados identificáveis em textos preservados | RESOLVIDA em 27/10/2026 | DEC-42 D1; DEC-56 | — |
