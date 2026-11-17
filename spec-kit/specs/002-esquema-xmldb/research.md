# Research — spec-002 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: `db/install.xml` é a fonte primária do esquema, com as 21 tabelas do §Inventário. **Rationale**: [REQ spec-002 §Objetivo]. **Alternatives considered**: n/a.
- **Decision**: DML para dados; XMLDB/upgrade API para estrutura. **Rationale**: [REQ §Contratos, §Conformidade]. **Alternatives considered**: n/a.
- **Decision**: nenhum campo ou índice fora do inventário. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Decididos por decisões aprovadas *(incluído em 26/10/2026)*
- **Decision**: alteração governada do esquema antes da primeira instalação, direto no `install.xml`. **Rationale**: DEC-29. **Alternatives considered**: `db/upgrade.php` desde já (desnecessário antes da primeira instalação).
- **Decision**: fontes removidas preservam o histórico e as referências; sem campo de marcação. **Rationale**: DEC-42 D3. **Alternatives considered**: expurgar histórico; campo de "fonte removida".
- **Decision**: dados exigidos por outras specs atendidos com campos existentes. **Rationale**: DEC-38, DEC-43, DEC-44; plano 017. **Alternatives considered**: novas colunas (não necessárias).

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-002-01 Conferência com XML auditado | RESOLVIDO — 205 itens idênticos depois da DEC-29 *(original: "ABERTO")* | Auditoria §7.2 e conferência de 26/10/2026 | — |
| Q-002-02 Upgrade/versão | RESOLVIDO — `version.php` e `db/upgrade.php` pela convenção do Moodle, depois da primeira instalação *(original: "ABERTO")* | DEC-29 | — |
| Q-002-03 Domínios `char` | PARCIAL — todos definidos, exceto `summary_status` (Q-008-05) *(original: "ABERTO")* | DEC-32, DEC-33; planos 010–016 | — |
| Q-002-04 Semântica `int(1)` | PARCIAL — todos definidos, exceto `availability` (Q-008-07) *(original: "ABERTO")* | DEC-39, DEC-40, DEC-45 | — |
| Q-002-05 Unicidade de `is_current` | RESOLVIDO — `current_version` (DEC-29) *(original: "ABERTO")* | Índice não único | garantir por transação; outra (não especificada) |
| Q-002-06 FKs × fontes removidas | RESOLVIDO — DEC-42 *(original: "ABERTO")* | Conflito com spec-017 resolvido | — |
| Q-002-07 Colunas exigidas por outras specs | RESOLVIDO — sem campo novo *(original: "ABERTO — decisão humana")* | spec-002 proíbe inventar campos | alterar spec/XMLDB; reinterpretar usando campos existentes (ambas exigem aprovação) |
| Q-002-08 Unidade de timestamp | RESOLVIDO — timestamp Unix em segundos *(original: "ABERTO")* | Convenção do Moodle | — |
