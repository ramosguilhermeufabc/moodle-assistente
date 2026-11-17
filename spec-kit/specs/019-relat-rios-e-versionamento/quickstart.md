# Quickstart (validação) — spec-019

> Nada foi executado. Usar relógio simulado. *(26/10/2026: Q16 é validação técnica no Moodle 5.2, CL-39.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Avançar 72 horas desde a última atualização bem-sucedida *(alterado em 25/10/2026; original: "Avançar 3 dias")* | Nova versão; a anterior é consultável | §Regras; DEC-06 |
| Q2 | Atividade mais distante em D | Atualizações até D; versão final em D+1 | §Regras *(26/10/2026: D+1 = 00:00 do dia seguinte no fuso do site)* |
| Q3 | Após D+1 | Nenhuma atualização periódica nova (Q-019-07) | §Regras *(26/10/2026: Q-019-07 resolvida, DEC-44 D3)* |
| Q4 | Professor sem acesso pede a versão atual | Negado | §Conformidade |
| Q5 | Atualização periódica coincide com D+1 | Uma única execução, identificada como final | §Regras; DEC-06 |
| Q6 | Curso sem data final aplicável | Nenhuma atualização periódica agendada (datas anuláveis por DEC-29) | §Regras; DEC-07 |
| Q7 | Falha na atualização | Falha registrada; o último resultado válido permanece; o intervalo de 72 h não reinicia; nenhum relatório lógico novo | §Regras; DEC-17 |
| Q8 | Data da atividade mais distante muda | A data da atualização final é recalculada | §Regras; DEC-18 |
| Q9 | Inspecionar `snapshot_json` | Só referências (`analysis_id`, `material_version`, versões de síntese), tipo, momento e estado por bloco; nenhum valor copiado; sem bloco de histórico | DEC-44 D1 |
| Q10 | Renderizar uma versão antiga | Valores das análises referenciadas, com as regras do plano 018 | DEC-44 D1 |
| Q11 | Professor em grupos separados lê o relatório | Partes individuais só com seus grupos; agregadas completas | DEC-43 D2; DEC-44 D1 |
| Q12 | Estudante com dados excluídos pela Privacy API | Não aparece em nenhuma versão renderizada | DEC-42 D1; DEC-44 D1 |
| Q13 | Site em fuso UTC−3; última atividade termina 25/10 às 23:30 local | `final_update_at` = 26/10 00:00 local; 72 h contadas em tempo absoluto | DEC-44 D2 |
| Q14 | Depois da final, prazo estendido para o futuro | `final_completed_at` limpo; data final recalculada; ciclo retomado; versão final anterior no histórico | DEC-44 D3 |
| Q15 | Depois da final, nova nota sem mudança de datas | Nenhuma versão nova | DEC-44 D3 |
| Q16 | Moodle 5.2 (validação técnica, CL-39): (a) site em fuso diferente de UTC; (b) cron parado por mais de 72 h e depois retomado; (c) duas execuções simultâneas do despachante; (d) atividades com data de entrega e com data de encerramento | A verificar na validação, sem resultado antecipado: (a) `final_update_at` corresponde a 00:00 do dia seguinte no fuso do site; (b) uma única versão é gerada na retomada e as 72 h passam a contar desse sucesso; (c) só uma execução grava versão, a outra não duplica (Lock API); (d) a data mais distante lida corresponde à data própria da atividade elegível. Registrar comandos, saídas e divergências em CL-39 | CL-39 *(alterado em 26/10/2026; texto original: "Moodle 5.2: fuso do site, Scheduled Task com cron atrasado, Lock API, datas de entrega/encerramento \| Comportamentos confirmados")* |

*(Q5 a Q8 incluídos em 25/10/2026 conforme a spec-019 propagada. Q9 a Q16 incluídos em 26/10/2026, depois de DEC-44.)*
