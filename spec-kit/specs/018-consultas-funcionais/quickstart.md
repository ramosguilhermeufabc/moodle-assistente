# Quickstart (validação) — spec-018

> Nada foi executado. *(26/10/2026: Q13 é validação técnica no Moodle 5.2, CL-38.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Professor consulta um curso que possui | Dados exibidos | §Regras |
| Q2 | Professor consulta um curso alheio (chamada direta) | Negado | §Regras; spec-005 |
| Q3 | Comparar os campos de cada consulta com o contrato | Nenhum indicador extra | §Regras |
| Q4 | Procurar entrada de texto livre | Inexistente | §Objetivo |
| Q5 | Filtrar notas pelo grupo A | Só estudantes do grupo A | DEC-43 D1 |
| Q6 | Filtrar a síntese ou as métricas de questionário por grupo | Filtro indisponível; dados de curso/seção | DEC-43 D1 |
| Q7 | Curso em grupos separados; professor sem capability de ver todos, membro do grupo A | Consultas individuais só com o grupo A; agregadas completas | DEC-43 D2 |
| Q8 | Mesmo curso, professor com a capability de ver todos | Todos os estudantes | DEC-43 D2 |
| Q9 | Questionário com `coverage = 0.0` sem materiais e outro com 0.0 real | Indicação "sem materiais" só no primeiro | DEC-35 D2 |
| Q10 | Estudante com `frequency_status = 2` e outro com nota `no_data` | "sem dados", nunca zero | DEC-40; DEC-24 |
| Q11 | Valor 66.666 | Exibido "66,66%" | DEC-36 D2 |
| Q12 | Atividade removida no Moodle | Resultado histórico com "fonte removida" | DEC-42 D3 |
| Q13 | Moodle 5.2 (validação técnica, CL-38): (a) curso com dois grupos e um estudante em ambos; (b) modo de grupos separados, professor só no grupo A, sem a capability de ver todos; (c) o mesmo professor com a capability concedida; (d) lista com mais estudantes do que o tamanho de página | A verificar na validação, sem resultado antecipado: (a) a associação a grupos é lida na consulta e o estudante aparece uma vez; (b) a API retorna só os grupos visíveis ao professor; (c) a capability amplia para todos os grupos; (d) a paginação nativa preserva a ordenação e o desempate por nome. Registrar comandos, saídas e divergências em CL-38 | CL-38 *(alterado em 26/10/2026; texto original: "Moodle 5.2: API de grupos, grupos separados, capability de ver todos, paginação \| Comportamentos confirmados")* |

*(Q5–Q13 incluídos em 26/10/2026, depois de DEC-43.)*
