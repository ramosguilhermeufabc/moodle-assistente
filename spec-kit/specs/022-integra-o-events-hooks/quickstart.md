# Quickstart (validação) — spec-022

> Nada foi executado. *(26/10/2026: Q10 é validação técnica no Moodle 5.2.)* *(Declaração da etapa de planejamento, mantida como histórico. Execuções posteriores estão registradas nas tarefas de `tasks.md` e, para o tipo interno `forum_rebuild_requested`, em `closure-register.md` CL-47 e na T121 da spec 020 (31/10/2026). Esta nota não afirma que os cenários desta spec tenham sido reexecutados.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Disparar cada evento Moodle mapeado | Um `local_assistente_event` correspondente | §Regras |
| Q2 | Instrumentar o observer | Sem LLM e sem regra de negócio | §Regras |
| Q3 | Revisar o registro de observers | Cada evento tem evidência documentada no 5.2 | §Regras |
| Q4 | Disparar um evento mapeado em curso desabilitado e em curso sem configuração | Nenhum `local_assistente_event` criado | DEC-45 D3 |
| Q5 | Disparar duas vezes a mesma ocorrência | Um único evento; a repetição reutiliza o existente | DEC-08; DEC-21 |
| Q6 | Matricular um estudante novo | Nenhum evento; o estudante aparece com "sem dados" nas listas | DEC-47 |
| Q7 | Remover um estudante do curso | Nenhum evento; deixa de aparecer nas listas; histórico preservado | DEC-47; DEC-42 |
| Q8 | Vencer o `timeclose` de um questionário sem nenhum evento Moodle | A detecção vem da Scheduled Task, não de observer | DEC-36 D4 |
| Q9 | Procurar no registro observers não previstos no catálogo | Nenhum | spec-022; DEC-47 |
| Q10 | Moodle 5.2 (validação técnica): disparar cada gatilho do catálogo e registrar a classe do evento, os dados disponíveis e a possibilidade de distinguir ocorrências | A verificar na validação, sem resultado antecipado: qual evento existe para cada gatilho, quais dados carrega e se permite a identidade por tipo (DEC-21). Registrar comandos, saídas e divergências na CL correspondente (CL-02, 33 a 37, 40, 41) | catálogo do plano |

*(Q4–Q10 incluídos em 26/10/2026, depois de DEC-45 e DEC-47.)*
