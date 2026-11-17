# Quickstart (validação) — spec-017

> Nada foi executado. *(26/10/2026: Q12 é validação técnica no Moodle 5.2, CL-37.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | A partir de uma `forum_difficulty` | Chega-se ao evento e à fonte Moodle | §Contratos |
| Q2 | Atualizar um resultado | O estado anterior continua consultável | §Regras (Q-017-01) *(26/10/2026: nova `analysis`; a anterior intacta)* |
| Q3 | Remover uma atividade no Moodle | O histórico permanece | §Regras (Q-017-03) *(26/10/2026: e é exibida como "fonte removida", DEC-42 D3)* |
| Q4 | Processing com 3 materiais | Os 3 (com versão) aparecem no snapshot | §Regras |
| Q5 | A partir de uma `report_version` | `snapshot_json` leva aos `analysis_id` e às versões de síntese usados | tratamento de Q-017-04 |
| Q6 | Excluir uma discussão de fórum | Análises históricas mantêm o texto; o snapshot atual não o contém; a referência permanece | DEC-42 D3; DEC-38 |
| Q7 | Solicitação de exportação de um estudante | Exporta as linhas por estudante e suas entradas nos snapshots | DEC-42 D1 |
| Q8 | Solicitação de exclusão de um estudante | Remove as linhas por estudante e suas entradas identificáveis nos snapshots (inclusive acesso ao curso); os eventos e as análises ficam, com `userid` 0 e `identity` neutra nos eventos finais; operação no log | DEC-42 D1; DEC-57 |
| Q9 | Mesmo cenário, com o nome do estudante citado num resumo de fórum preservado | A verificação de privacidade detecta o nome (completo, nome ou sobrenome como palavra inteira) e o texto passa a ter `[removido]` no lugar, com o resultado da busca e a contagem de textos e campos alterados no log; versão e vínculos permanecem | DEC-42 D1; DEC-56 (Q-017-07); **não executado** (T104) |
| Q10 | Excluir o curso | Nenhuma linha do plugin daquele curso permanece, inclusive versões de relatório | DEC-42 D2 |
| Q11 | Execuções repetidas por meses | Nenhum expurgo automático; volume medido | DEC-42 D4 |
| Q12 | Moodle 5.2 (validação técnica, CL-37): (a) instalar o plugin e inspecionar as restrições do banco; (b) remover no Moodle uma atividade referenciada por um resultado; (c) solicitar exportação e exclusão de dados de um estudante pela Privacy API; (d) excluir um curso com dados do plugin; (e) estudante citado pelo nome num texto preservado | A verificar na validação, sem resultado antecipado: (a) se as chaves `foreign` do XMLDB geram ou não restrição no banco (premissa de DEC-42 D3); (b) se a remoção ocorre sem cascata nem bloqueio e se a ausência do registro é detectável na leitura; (c) se o provider recebe as solicitações por usuário e por contexto; (d) qual mecanismo do Moodle 5.2 sinaliza a exclusão e se todos os dados do curso são removidos; (e) se a verificação detecta o dado identificável (tratamento em Q-017-07). Registrar comandos, saídas e divergências em CL-37 | CL-37 *(alterado em 26/10/2026; texto original: "Moodle 5.2: chaves XMLDB no banco, provider da Privacy API, exclusão de curso, leitura de registro removido \| Comportamentos confirmados")* |
| Q13 | Excluir um estudante com o lock de um evento seu preso por mais de 30 s (CL-37, F1; DEC-58) | O pedido pode terminar `deleted` no Moodle, mas o plugin agenda a tarefa de nova tentativa (8 tentativas), avisa o DPO com texto fixo, mantém os dados até a tentativa dar certo e, liberado o lock, conclui sem novo pedido; o estado é PENDENTE, depois SEM RESÍDUO ENCONTRADO com `closed_by_retry`; nome indisponível nunca é sucesso (TEXTOS NÃO VERIFICADOS) | DEC-58; T142 |

*(Q5–Q12 incluídos em 26/10/2026, depois de DEC-42; Q13 em 28/10/2026, DEC-58.)*
