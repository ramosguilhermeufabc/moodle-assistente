# Quickstart (validação) — spec-005

> Nada foi executado.

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Professor acessa um curso que possui | Permitido | §Regras |
| Q2 | Professor acessa um curso que não possui, por chamada direta ao backend (sem UI) | Negado | §Regras, §Conformidade |
| Q3 | Administrador acessa qualquer curso | Permitido | §Regras |
| Q4 | Definir os critérios de um curso | Persistidos em `config`/`criteria` | §Contratos |
| Q5 | Tentar alterar os critérios definidos | Rejeitado | §Regras |
| Q6 | Verificar que análises antigas não são recalculadas após a definição | Sem reprocessamento retroativo | §Regras |
| Q7 | Professor sem edição (`teacher`) sem concessão explícita da capability | Negado | DEC-30 |
| Q8 | Usuário com `local/assistente:view` no contexto de sistema (padrão `manager`) | Acesso a todos os cursos | DEC-30 |
| Q9 | Usuário com papel de professor no curso, mas com a capability removida | Negado (a autorização é por capability, não por papel) | DEC-30 |
