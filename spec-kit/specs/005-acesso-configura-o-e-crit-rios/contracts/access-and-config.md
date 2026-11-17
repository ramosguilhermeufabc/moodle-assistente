# Contract — Autorização e configuração (spec-005)

| Operação | Entrada | Saída / efeito | Regras |
|---|---|---|---|
| `canAccessCourse(user, course)` | usuário, curso | bool | Verdadeiro se `user` tem `local/assistente:view` no contexto do curso, ou no contexto de sistema (acesso a todos os cursos). Padrões: `editingteacher` (curso) e `manager` (sistema); o administrador do site passa pelo modelo do Moodle. Verificado no backend [REQ §Regras, §Conformidade; DEC-30]. *(Alterado em 25/10/2026; texto original: "Professor: cursos que possui; Admin: todos. Verificado no backend [REQ §Regras, §Conformidade]. Critério de "possui": Q-005-01")* |
| `listAccessibleCourses(user)` | usuário | cursos | mesma regra |
| `defineCriteria(course, activity, forum, frequency)` | critérios | cria `config` + `criteria` | só se ainda não definidos; autorização antes de persistir (spec-020); capability de escrita indefinida (Q-005-08) *(04/11/2026, revisão dos planos: a capacidade de escrita foi definida pela DEC-45 D1 (Q-005-08); CL-42 Fechado em 02/11/2026.)* |
| `getCriteria(course)` | curso | critérios | somente leitura |
| `updateCriteria` | — | **não existe** | critérios imutáveis [REQ §Regras] |

Consumidores: spec-015 (aplicação), 018 (escopo de consultas), 020 (formulários).
