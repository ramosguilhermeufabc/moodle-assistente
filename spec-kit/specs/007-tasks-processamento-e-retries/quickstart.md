# Quickstart (validação) — spec-007

> Nada foi executado.

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Verificar a instalação da task | Intervalo inicial de 15 min | §Regras |
| Q2 | Administrador altera o intervalo no agendamento nativo das Scheduled Tasks | Novo intervalo vigente; o plugin não tem formulário próprio | §Regras; DEC-32 D1 |
| Q3 | Rodar o ciclo com N eventos pendentes | Todos processados no mesmo ciclo | §Regras |
| Q4 | Handler falha sempre | Uma tentativa por ciclo; exatamente 3 `processing_attempt` em 3 ciclos; o evento volta a `pending` depois da 1ª e da 2ª falha e fica em `failure` depois da 3ª | §Regras; DEC-32 D3, D4 |
| Q5 | Reexecutar o ciclo sobre um evento já processado | Nenhum resultado duplicado | §Regras |
| Q6 | Duas execuções simultâneas | Nenhum evento processado duas vezes | constituição §8 (Q-007-05) |
| Q7 | Interromper a execução no meio do processamento de um evento | O ciclo seguinte recupera o mesmo processamento lógico depois de obter o lock, sem criar outro | DEC-22, P-04 |
| Q8 | Nova ocorrência legítima do mesmo objeto | Novo evento e novo processamento | DEC-08 |
| Q9 | Tarefa concorrente encontra o evento em processamento | Não reinicia e não duplica resultados | DEC-16 |
| Q10 | Eventos chegam durante o ciclo | São processados no mesmo ciclo, se houver tempo antes da próxima execução | DEC-32 D5 |
| Q11 | O ciclo atinge o horário da próxima execução com a fila ainda não vazia | O evento em andamento é concluído; os restantes ficam para o ciclo seguinte | DEC-32 D5 |
| Q12 | Vários eventos pendentes | Processados em ordem de `created_at` crescente | DEC-32 D6 |

*(Q2 e Q4 ajustados e Q7–Q12 incluídos em 25/10/2026 por DEC-32; Q2 original: "Administrador altera o intervalo \| Novo intervalo vigente"; Q4 original: "Exatamente 3 `processing_attempt` persistidas; evento em falha".)*
