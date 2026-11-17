# Contenção de trava nos observers: espera longa e evento perdido sem recuperação

> **Nota de atualização (DEC-59, proposta de organização do registro; data de aprovação confirmada pelo responsável em 03/11/2026 (texto anterior, preservado: "data de aprovação [A CONFIRMAR]")).** Este documento é um registro histórico de uma medição e **não foi reescrito**. A decisão sobre a perda de evento por falha de trava no observer está na DEC-59 (`spec-kit/decision-register-DEC-01-25.md`): a entrega que falha por trava passa a ser repetida por uma tarefa ad hoc de reentrega (exceção delimitada à DEC-32 D2), com observers esperando 3 s (valor inicial, a validar). Implementada no Lote 1 e no lote de fechamento, com revisão técnica concluída com ressalvas. As perguntas em aberto abaixo refletem o estado no momento da medição. Não foram medidos novamente o tempo de 120,05 s nem o efeito da reentrega sobre ele. Limitações que a DEC-59 mantém: falha de agendamento sem registro durável; tarefa esgotada não verificada; ordem de eventos de fórum e de questionário após a reentrega não verificada; custo da verificação de duplicata em rajada não medido; sinais de lock validados só no MariaDB 11.4.13.

Risco aberto, **separado** do diagnóstico de desempenho de A2: aqui o problema não depende do volume de dados. Não altera decisões normativas,
não tem identificador no registro de fechamento e não resolve nem altera CL-02, CL-07 nem CL-40.

## O que foi observado (medido uma vez, ambiente de teste)

Um processo separado segurou a trava `privacy_user_{id}` do único autor das mensagens de uma discussão com 4 mensagens (3 inseridas mais a
inicial), e `forum_delete_discussion` foi executada com os observers reais do plugin e o tempo de espera padrão de trava (30 s):

| Item | Resultado |
|---|---|
| Duração da exclusão | 120,05 s (quase todo dentro dos observers: 120,02 s em 9 entregas) |
| Exclusão no Moodle | concluída: discussão e mensagens removidas |
| `forum_post_deleted` | 4 de 4 persistidos (não usam a trava do aluno) |
| `participation_changed` | 0 de 4 persistidos |
| Registro da falha | 4 linhas de `error_log` e 4 mensagens de `debugging()` ("Could not acquire the lock 'privacy_user_…'") |
| Reentrega | nenhuma |

Medição única de um cenário construído; não mede com que frequência isso acontece num site real nem outros tipos de trava.

## Por que acontece (lido no código)

- Cada entrega de participação ou de nota de um aluno toma a trava do aluno **antes** de verificar se o evento já existe, com espera de até
  30 s (`unit_of_work::DEFAULT_LOCK_TIMEOUT`). Cada mensagem excluída é uma entrega, então as esperas se somam: N mensagens × 30 s.
- Os observers capturam qualquer `\Throwable`, registram e seguem (isolamento de falha por desenho, T046), para que um erro da fila não derrube
  a ação do professor. A falha não propaga, mas o tempo de espera é gasto na requisição ou tarefa do professor.
- Os observers rodam depois do commit (`internal => false`), então a falha não desfaz a operação do Moodle.
- Não existe tarefa de reconciliação: as tarefas agendadas são `process_queue`, `detect_quiz_close`, `detect_reference_dates` e
  `report_dispatcher`. Um evento perdido em um observer só é recuperado indiretamente (um evento posterior do mesmo aluno, a detecção de datas de
  referência ou uma nova sincronização inicial), e isso **não foi verificado por teste** para este caso.

## O que isso significa

- Uma trava presa por outro processo, por exemplo a exclusão de dados de privacidade do aluno, que também toma `privacy_user_{id}`, faz a ação do
  professor demorar 30 s por entrega e **perde** as entregas que falham.
- A perda é silenciosa para o usuário: só há log e mensagem de depuração.
- Há duas situações distintas que não devem ser confundidas com o gargalo de volume: (1) a busca de identidade, que degrada com a quantidade de
  eventos; (2) a contenção de trava, que atinge operações pequenas.

## Perguntas em aberto (nenhuma foi decidida ou implementada)

1. Quando uma entrega falha por trava, o evento deve ser perdido, reentregue depois ou registrado em algum lugar durável para recuperação?
2. O tempo de espera dos observers deve ser menor que o da fila (30 s), já que corre na ação do professor?
3. A trava do aluno deve ser tomada antes da verificação de existência quando o evento já existe (reentrega idempotente)?
4. O que a recuperação de um `participation_changed` perdido significaria para a frequência: um recálculo posterior já a corrige?
5. A exclusão de dados do aluno (privacidade) retém a trava por quanto tempo num site real?

Estas perguntas se ligam a CL-40 (recuperação e integridade da sincronização) e a CL-02 (backend de trava), que continuam abertos.

## Limites desta evidência

Um cenário, uma execução, MariaDB 11.4 com a fábrica de travas `GET_LOCK`, processos da mesma máquina. Não foram medidos outros backends de trava,
nem a frequência real da contenção.
