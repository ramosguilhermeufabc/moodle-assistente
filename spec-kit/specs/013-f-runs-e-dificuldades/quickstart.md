# Quickstart (validação) — spec-013

> Nada foi executado. LLM mockada. *(25/10/2026: Q13 é validação técnica no Moodle 5.2, CL-35.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Nova mensagem relevante | Novo evento; a análise usa resumo atual + mensagem + contexto da seção | §Regras *(25/10/2026: "relevante" = toda postagem, DEC-03)* |
| Q2 | Segunda atualização | O resumo anterior continua consultável (histórico) | §Regras |
| Q3 | Discussão com dificuldade | Linha em `forum_difficulty` com `discussion_id` | §Contratos |
| Q4 | Material de outra seção | Ausente do contexto | §Contratos |
| Q5 | Primeira mensagem do fórum | Resumo atual vazio no `context`; análise criada | tratamento de Q-013-04 |
| Q6 | Discussão A com 2 dificuldades; nova mensagem em A; mock devolve 1 revisada e 1 nova, sem uma das antigas | A fica com as 2 devolvidas; a antiga removida segue na análise anterior | DEC-38 D1 |
| Q7 | Discussões A e B; nova mensagem em A | Dificuldades de B copiadas idênticas, sem LLM para B | DEC-38 D1 |
| Q8 | Mock devolve `discussion_id` de B no modo incremental de A | `invalid_response`; nada publicado | DEC-38 D1; plano 009 |
| Q9 | Falha na chamada à LLM | Nenhuma `analysis` nova; snapshot anterior continua o atual | DEC-38 D1 |
| Q10 | Mensagem editada; depois mensagem excluída; depois discussão excluída | Edição: incremental marcado como edição; exclusões: reconstrução com o que existe, sem o conteúdo excluído | DEC-38 D2 |
| Q11 | Inspecionar o `context` | Sem autor, ID de usuário, nome ou e-mail | DEC-34 D3 |
| Q12 | Evento de mensagem chega a `failure`; nova mensagem depois | A mensagem falha não entra no resumo atual (R-013-01) | DEC-32; R-013-01 |
| Q13 | Moodle 5.2 (validação técnica, CL-35): (a) criar uma mensagem; (b) editá-la; (c) excluí-la; (d) excluir uma discussão; (e) postagens de estudante e de professor no mesmo fórum | A verificar na validação, sem resultado antecipado: (a)–(d) cada ação emite um evento identificável, com dados para distinguir a ocorrência (CL-02); (b) a edição é distinguível da criação; (c) e (d) depois da exclusão, as mensagens e discussões restantes podem ser lidas pela API do `mod_forum`; (e) o papel de quem postou pode ser obtido sem expor identificadores à LLM. Registrar comandos, saídas e divergências em CL-35 | CL-35 *(alterado em 26/10/2026; texto original: "Moodle 5.2: eventos de criação, edição e exclusão de mensagem e de discussão; leitura de mensagens e papéis \| Eventos e APIs confirmados")* |

*(Q5–Q13 incluídos em 25/10/2026, depois de DEC-34 e DEC-38.)*
