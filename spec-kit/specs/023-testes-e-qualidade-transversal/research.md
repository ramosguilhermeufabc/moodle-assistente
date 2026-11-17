# Research — spec-023 (Fase 0)

> Sem pesquisa externa (PROMPT-3). Nenhum runner foi avaliado. *(26/10/2026: os runners foram escolhidos pelo responsável entre as ferramentas oficiais do Moodle, DEC-48; nenhum foi executado.)*

## Decididos pela spec
- **Decision**: tipos de teste (unitário, integração, persistência/XMLDB, eventos/tasks, LLM mockada, regras determinísticas, acesso, histórico, UI). **Rationale**: [REQ §Contratos]. **Alternatives considered**: n/a.
- **Decision**: testes unitários por entrega + verificação de usabilidade pelo pesquisador, sem professores/estudantes. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: execução no ambiente de spec-001. **Rationale**: [REQ §Conformidade]. **Alternatives considered**: n/a.

## Decididos pelo responsável *(incluído em 26/10/2026, DEC-48)*
- **D1**: PHPUnit e Behat do Moodle; LLM pelo adaptador simulado. **Alternatives considered**: só PHPUnit, com a UI coberta pelo pesquisador.
- **D2**: rastreabilidade de cada cenário, sem percentual mínimo. **Alternatives considered**: limiar numérico de cobertura.
- **D3**: entrega = tarefa que altera código; checklist do pesquisador depois de cada entrega de UI. **Alternatives considered**: entrega = plano concluído; usabilidade só ao final.

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-023-01 Runners | RESOLVIDO — DEC-48 D1 *(original: "ABERTO — decisão humana")* | Ferramentas oficiais do Moodle | não avaliadas (exigiria fonte externa) |
| Q-023-02 Critérios de aceitação | RESOLVIDO — cenários dos planos aprovados *(original: "ABERTO")* | Lacunas fechadas por DEC-36 a 45 | — |
| Q-023-03 Usabilidade | RESOLVIDO — DEC-48 D3 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-023-04 Cobertura | RESOLVIDO — DEC-48 D2 *(original: "ABERTO")* | Decisão do responsável | — |
| Q-023-05 Fixtures | RESOLVIDO — geradores do Moodle e do plugin *(original: "ABERTO")* | Ferramenta oficial | — |
| Q-023-06 "Entrega" | RESOLVIDO — DEC-48 D3 *(original: "ABERTO")* | Decisão do responsável | — |
