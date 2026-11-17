# spec-019 — Relatórios e Versionamento

## Objetivo & Escopo

Consolidar informações por curso e manter versões históricas.

## Dependências de Specs Anteriores

spec-017, spec-018

## Contratos & Interfaces

Persistência:
`local_assistente_report`
`local_assistente_report_version`

Um relatório lógico por curso.

## Regras de Validação & Requisitos Técnicos

Atualização:
- a cada 72 horas, contadas desde a última atualização bem-sucedida; *(Alterado em 22/10/2026 por DEC-06; texto original: "a cada 3 dias;")*
- até a data final da atividade mais distante;
- atualização final um dia após essa data.
A atualização final ocorre no dia seguinte ao encerramento da atividade elegível com a data mais distante. Se a atualização periódica coincidir com a final, executa-se uma única vez, identificada como final. *(Incluído em 22/10/2026 por DEC-06.)*
Enquanto não houver data final aplicável de atividade, não se agendam atualizações periódicas. Nesse caso, `next_update_at`, `final_activity_date` e `final_update_at` ficam nulos. *(Incluído em 22/10/2026 por DEC-07. Alterado em 25/10/2026 por DEC-29; texto substituído: "Esta regra conflita com `next_update_at`, `final_activity_date` e `final_update_at` NOT NULL em `local_assistente_report` (spec-002); a alteração de esquema depende de `closure-register.md`, CL-07.")*
O agendamento usa um despachante periódico com datas de vencimento persistidas. O reagendamento é idempotente e as atualizações de um mesmo relatório são serializadas (Lock API por relatório, transação DML). A conclusão da atualização final é registrada em `final_completed_at`. A versão corrente é a referência `current_version` do relatório, sem indicador separado na versão. Uma falha preserva a versão corrente e o último sucesso. Quando os vencimentos periódico e final coincidem, executa-se uma única atualização. O cron do Moodle garante execução na primeira oportunidade processada, não em horário exato. *(Incluído em 25/10/2026 por DEC-23 e DEC-29.)*
Uma falha de atualização não conta como atualização bem-sucedida, não reinicia o intervalo de 72 horas e não substitui o último resultado válido. A falha é registrada e recuperada sem criar outro relatório lógico. *(Incluído em 22/10/2026 por DEC-17.)*
Se a data de encerramento da atividade elegível mais distante mudar, a data prevista da atualização final é recalculada. *(Incluído em 22/10/2026 por DEC-18.)*
Fuso horário, reabertura de datas depois da atualização final e recuperação de falha da atualização final continuam em aberto (AT-065, `closure-register.md`, CL-06). *(04/11/2026, revisão da propagação: fuso horário e reabertura foram decididos pela DEC-44 (D2: "o dia seguinte" é 00:00 do próximo dia-calendário no fuso do site, com 72 horas em tempo absoluto; D3: a reabertura depois de `final_completed_at` limpa `final_completed_at`, recalcula a data final e retoma o ciclo, preservando as versões anteriores). A recuperação de falha da atualização final segue a DEC-17: "a falha deve ser registrada e recuperada sem criar outro relatório lógico", como já indica a CL-06. Texto anterior, preservado: "Fuso horário, reabertura de datas depois da atualização final e recuperação de falha da atualização final continuam em aberto (AT-065, `closure-register.md`, CL-06)".)*
Versões anteriores permanecem consultáveis.
O relatório distingue ausência de dados suficientes de um zero real e distingue erro técnico de resultado válido. *(Incluído em 22/10/2026 por DEC-24.)*
Estudantes com pontuação igual são exibidos em ordem alfabética crescente do nome. *(Incluído em 22/10/2026 por DEC-02.)*

## Conformidade Moodle

O agendamento pode usar Tasks API. A versão atual deve respeitar o escopo de acesso.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
