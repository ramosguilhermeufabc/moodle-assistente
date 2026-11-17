# Quickstart (validação) — spec-014

> Nada foi executado. *(25/10/2026: Q14 é validação técnica no Moodle 5.2, CL-36.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Estudante participa de 1 de 3 atividades da seção | 33,33% (forma persistida: Q-014-05) *(25/10/2026: `33.333`)* | §Regras; DEC-39 |
| Q2 | Duas participações na mesma atividade | Conta como 1 (presente) | §Regras |
| Q3 | Inspecionar `reference_date` | Igual à data final/devida da atividade | §Regras |
| Q4 | Inspecionar as tabelas próprias | Somente resultados derivados | §Conformidade |
| Q5 | Página e arquivo com conclusão habilitada | Fora do denominador | DEC-39 D1 |
| Q6 | Atividade com restrição que o estudante não atende | Fora do denominador desse estudante; dentro para quem atende | DEC-39 D1; DEC-26 |
| Q7 | Tarefa com submissão; questionário concluído; atividade com conclusão "reprovada"; fórum com uma postagem | Todas contam como participação | DEC-39 D1 |
| Q8 | Fórum sem prazo em curso com `course.enddate` | `reference_date = course.enddate` | DEC-39 D2 |
| Q9 | Atividade sem prazo em curso sem `course.enddate` | Fora do cálculo; condição listada no contexto | DEC-39 D2; R-014-01 |
| Q10 | Atividade com prazo futuro: estudante A já participou, B não | Entra no denominador de A (participou) e não no de B | DEC-39 D2 |
| Q11 | Prazo vence sem evento | A Scheduled Task recalcula o curso; B passa a ter a atividade no denominador com `participated = 0` | DEC-39 D2 |
| Q12 | Estudante que só acessou o curso, sem participar | Percentual não muda; snapshot registra acesso e data | DEC-25; DEC-39 D3 |
| Q13 | Seção sem atividade no denominador; outra com 0 de 2; falha técnica | `no_data` com 0; `valid` com 0.000; `error`, sem zero | DEC-24 |
| Q14 | Moodle 5.2 (validação técnica, CL-36): (a) atividade com conclusão completa, aprovada e reprovada; (b) tarefa em rascunho e enviada; (c) página e arquivo com conclusão habilitada; (d) atividade com restrição de acesso atendida por um estudante e não por outro; (e) tarefa, questionário e fórum com e sem data de entrega/encerramento; (f) postagem no fórum; (g) acesso ao curso sem participação | A verificar na validação, sem resultado antecipado: (a) os três estados são legíveis; (b) rascunho e envio são distinguíveis; (c) recurso e atividade são distinguíveis pelo módulo; (d) a disponibilidade é obtida por usuário; (e) os campos de data de cada módulo e `course.enddate` podem ser lidos; (f) as postagens podem ser contadas por usuário; (g) o último acesso ao curso pode ser lido; eventos de participação emitidos em (a), (b), (f) e na nota. Registrar comandos, saídas e divergências em CL-36 | CL-36 *(alterado em 26/10/2026; texto original: "Moodle 5.2: conclusão, submissão, atividade × recurso, disponibilidade, datas, postagens, último acesso, eventos \| APIs e eventos confirmados")* |

*(Q5–Q14 incluídos em 25/10/2026, depois de DEC-39.)*
