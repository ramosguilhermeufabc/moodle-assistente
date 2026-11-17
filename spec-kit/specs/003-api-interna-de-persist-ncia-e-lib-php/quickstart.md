# Quickstart (validação) — spec-003

> Nada foi executado nesta fase. Runner: Q-023-01. *(26/10/2026: PHPUnit do Moodle, DEC-48. Q10 é validação técnica, CL-07.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Inspecionar o código de domínio em busca de `$DB` ou SQL | Nenhuma ocorrência fora da infraestrutura | §Regras |
| Q2 | Inspecionar as chamadas DML | Nenhum prefixo físico de tabela | §Regras |
| Q3 | Inspecionar `lib.php` | Somente callbacks necessários, sem regra de negócio | §Regras *(26/10/2026: só navegação e, se necessário, exclusão de curso)* |
| Q4 | Teste de integração: violar um único via repository | Erro tratado; dado não duplicado | §Regras |
| Q5 | Teste de integração: falha no meio de operação multi-tabela transacional | Nenhum estado parcial persistido | §Regras (escopo em Q-003-02) *(26/10/2026: escopo resolvido)* |
| Q6 | Gravar um valor fora do domínio de um campo (`analysis_type` desconhecido, `*_status` = 3) | Rejeitado pelo repositório | data-model 002; Q-002-03/04 |
| Q7 | Simular chamada à LLM dentro de um serviço que grava resultados | A chamada ocorre antes de abrir a transação | spec-007; contrato |
| Q8 | Duas gravações concorrentes de versão no mesmo material, síntese ou relatório | Uma por vez sob lock; versões sem duplicata | DEC-20, DEC-41, DEC-44 |
| Q9 | Inspecionar o layout | Classes no namespace `local_assistente` sob `classes/`, nos diretórios previstos; arquivos exigidos pelo Moodle nos locais fixos | Q-003-04 |
| Q10 | Validação técnica (CL-07): transação DML com Lock API no Moodle 5.2, com rollback provocado | A verificar, sem resultado antecipado: rollback completo e liberação do lock; registrar comandos, saídas e divergências em CL-07 | CL-07 |

*(Q6–Q10 incluídos em 26/10/2026.)*
