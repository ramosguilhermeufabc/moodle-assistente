# Research — spec-003 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: `$DB`/DML somente na infraestrutura; o domínio não conhece SQL. **Rationale**: [REQ §Contratos, §Regras]. **Alternatives considered**: n/a.
- **Decision**: `lib.php` reservado a callbacks necessários, sem lógica de negócio. **Rationale**: [REQ §Objetivo, §Regras]. **Alternatives considered**: n/a.
- **Decision**: chamadas DML sem prefixo físico de tabela. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Decididos no plano *(incluído em 26/10/2026, sem decisão humana nova)*
- **Decision**: agregados por plano e por `analysis_type`. **Rationale**: planos aprovados 005–019. **Alternatives considered**: um repositório por tabela (descartado: perderia a gravação tudo-ou-nada das análises).
- **Decision**: unidades transacionais curtas, sem LLM, com Lock API onde as decisões exigem. **Rationale**: spec-007; DEC-15, 20, 22, 23, 32, 38, 41, 42, 44, 45. **Alternatives considered**: n/a.
- **Decision**: layout `local_assistente` em `classes/`, com camadas domínio/aplicação/infraestrutura. **Rationale**: autoload do Moodle; constituição §12. **Alternatives considered**: estrutura plana por funcionalidade (sem separação explícita de camadas).

## Abertos
| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-003-01 Agregados | RESOLVIDO — onze agregados *(original: "ABERTO")* | Planos aprovados | — |
| Q-003-02 Política transacional | RESOLVIDO — unidades enumeradas *(original: "ABERTO")* | Decisões aprovadas | — |
| Q-003-03 Callbacks `lib.php` | RESOLVIDO no nível funcional — navegação e, se necessário, exclusão de curso *(original: "ABERTO")* | Verificação Moodle 5.2 em CL-41 e CL-37 | — |
| Q-003-04 Namespace/caminhos | RESOLVIDO — layout do plano *(original: "ABERTO")* | Convenções do Moodle | — |
| Q-003-05 Aggregate × derivado | RESOLVIDO — repositórios × readers *(original: "ABERTO")* | spec-004; planos 018, 019 | — |
