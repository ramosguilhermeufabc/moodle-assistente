# Contract — Processamento da fila (spec-007)

## Ciclo (Scheduled Task)
- Intervalo inicial 15 min, configurável pelo administrador no agendamento nativo das Scheduled Tasks, sem formulário próprio [REQ §Regras; DEC-32 D1].
- Laço na Scheduled Task: um evento por vez, sob o lock do evento, em ordem de `created_at` crescente (D2, D6).
- Encerramento: o ciclo continua enquanto houver evento `pending` ainda não tentado nele, inclusive os que chegarem durante a execução, e se encerra quando (a) não restar nenhum ou (b) chegar o horário da próxima execução agendada da própria task; no caso (b), o evento em andamento é concluído e os restantes ficam para o ciclo seguinte. A condição usa o intervalo configurado (D1), sem limite numérico novo (D5).

*(Remissão à DEC-59, proposta de organização do registro, data de aprovação confirmada pelo responsável em 03/11/2026 (texto anterior, preservado: "data de aprovação [A CONFIRMAR]"): a D2 acima tem uma exceção delimitada, a tarefa ad hoc de reentrega após falha de lock no observer, que não processa evento e apenas repete `event_service::enqueue()`. O ciclo da Scheduled Task descrito aqui não muda.)*

*(Ciclo alterado em 25/10/2026; texto original: "Intervalo inicial 15 min; configurável pelo administrador [REQ §Regras] (Q-007-01)." / "Processa os eventos `pending` em lote até esgotar a fila [REQ §Regras] (Q-007-06/07).")*

## Por evento
1. Obter ou criar o `processing` (1:1 com o evento).
2. Registrar `processing_attempt` (n ≤ 3).
3. Despachar para o handler do `event_type` (specs 008, 010, 011, 013…).
4. Persistir o resultado ou a falha da tentativa. Se falhar com tentativas restantes, o evento volta a `pending` para o ciclo seguinte; depois da 3ª falha, passa a `failure` (DEC-32 D3, D4). No máximo uma tentativa por evento em cada ciclo. *(Alterado em 25/10/2026; texto original: "Persistir o resultado/falha da tentativa; ao exceder 3, encerrar como falha.")*

## Interface de handler
`handle(event, processing) → sucesso | falha(motivo)`. A assinatura concreta está NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: interface em `assistente/classes/application/queue/event_handler.php`).

## Garantias
- Idempotência: a repetição reutiliza o processamento e os resultados não se duplicam [REQ §Regras; DEC-08].
- Concorrência: Lock API por evento; a tarefa concorrente reconhece o processamento existente; recuperação de execução interrompida depois de obter o mesmo lock; sem transação aberta durante a chamada à LLM (DEC-16, DEC-22, P-04). *(Alterado em 25/10/2026; texto original: "Idempotência [REQ §Regras] (Q-007-08)." / "Concorrência entre ciclos (Q-007-05).")*
