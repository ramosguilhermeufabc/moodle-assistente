# Implementation Plan: spec-007 — Tasks, Processamento Assíncrono e Retries

**Branch**: `feature/sdd-ajustado` (feature dir `007-tasks-processamento-e-retries`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/007-tasks-processamento-e-retries/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026, com a validação técnica do §5 (CL-02) como pendência que não bloqueia o planejamento. A aprovação não significa que o comportamento foi testado no Moodle 5.2 e, isolada, não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Executar a fila própria (spec-006) de forma assíncrona via Moodle Tasks API: uma Scheduled Task com ciclo periódico (intervalo inicial de 15 min, configurável pelo administrador) e um mecanismo interno que processa os eventos pendentes em lote até esgotar a fila [REQ spec-007 §Contratos, §Regras]. Cada evento tem um `processing` (1:1) e até 3 `processing_attempt`. Tentativas e falhas ficam persistidas, e o processamento é idempotente [REQ §Contratos, §Regras]. Pela DEC-32: o intervalo usa o agendamento nativo do Moodle (D1); um laço na Scheduled Task trata um evento por vez sob o lock do evento (D2), com no máximo uma tentativa por ciclo e retentativa no ciclo seguinte (D3); os estados reutilizam o vocabulário da spec-006 (D4); o ciclo se encerra quando a fila elegível se esgota ou quando chega o horário da próxima execução (D5); a ordem é `created_at` crescente (D6). *(Incluído em 25/10/2026.)*

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: Moodle Tasks API (Scheduled; Adhoc citada como suportada) [REQ §Conformidade]; fila de spec-006; persistência de spec-003.

**Storage**: `local_assistente_processing` (`event_id` foreign-unique), `local_assistente_processing_attempt` (único `processing_id,attempt_number`) [spec-002].

**Testing**: máximo de 3 tentativas totais por processamento, incluindo a inicial, e idempotência [REQ spec-007 §Regras; spec-023 §Regras; DEC-09]. Runner em Q-023-01. *(Alterado em 25/10/2026; texto original: "máximo de 3 retries e idempotência [REQ spec-023 §Regras]".)*

**Target Platform**: GitHub Codespaces + Moodle 5.2. O disparo periódico no ambiente está em Q-001-06.

**Project Type**: plugin local Moodle, processamento assíncrono.

**Performance Goals**: intervalo inicial de 15 min [REQ §Regras]. Vazão não especificada.

**Constraints**: ≤ 3 tentativas por evento; persistência de todas as tentativas e falhas; idempotência [REQ §Regras].

**Scale/Scope**: lote "até esgotar a fila" [REQ §Regras]. Limites em Q-007-06.

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | O desenho está decidido (DEC-32). Falta validar no Moodle 5.2 o agendamento nativo, o backend de lock, o horário da próxima execução, a ausência de execução simultânea da task e a recuperação de worker interrompido (`closure-register.md` CL-02, que não bloqueia o planejamento). *(Alterado em 25/10/2026; observação original: "Uso de Scheduled/Adhoc e configuração do intervalo não verificados (Q-007-01/02).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, agendamento nativo, backend de lock, exclusão mútua e recuperação de worker confirmados em CL-02 (FECHADA em 02/11/2026, com limitações residuais, T051 [x] V1–V3); ressalva operacional do cron real registrada no CL-02)* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | PASSA | PASSA | Tentativas e falhas persistidas [REQ]. |
| §8 Idempotência/concorrência | BLOQUEADO | PASSA | O desenho está definido: um processamento lógico por evento sob Lock API por evento, recuperação depois de obter o mesmo lock e sem campos XMLDB adicionais (DEC-16, DEC-22, P-04), com idempotência dos resultados (DEC-08). A validação fica no §5 (CL-02). *(Alterado em 25/10/2026; texto anterior: "BLOQUEADO \| Lock/concorrência entre execuções e chave de idempotência indefinidos (Q-007-05/08).")* |
| §9 Testabilidade | PASSA | PASSA | [quickstart.md](quickstart.md). |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | — |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | O processamento assíncrono chama a LLM sem transação aberta [REQ spec-007 §Regras]. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-02). Questões Bloqueador: nenhuma; Q-007-05 resolvida no nível funcional. *(Alterado em 25/10/2026; texto anterior: "princípios BLOQUEADO: §5, §8. Questões Bloqueador: Q-007-05.")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa (CL-02 FECHADA, T051 [x]); nenhum princípio BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/007-tasks-processamento-e-retries/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/queue-processing.md
```

### Source Code (repository root)

```text
assistente/
└── [Scheduled Task + processador da fila]   # registro da task e caminhos NEEDS CLARIFICATION (Q-007-01, Q-003-04) (04/11/2026: resolvido: Q-007-01 por DEC-32 D1; registro em `assistente/db/tasks.php` e processador em `assistente/classes/application/queue/queue_processor.php` (T048 e T050 [x]))
```

## Dependências entre specs

- Upstream: spec-006 [REQ §Dependências].
- Downstream: 008, 009, 011–014 (dependência declarada) e 019 (agendamento).

## Fases de planejamento & gates

1. Aplicar a DEC-32: agendamento nativo (D1), laço sob lock por evento (D2), retentativa no ciclo seguinte (D3), estados (D4), encerramento (D5) e ordem (D6).
2. Aplicar a idempotência e a concorrência decididas (DEC-08, DEC-16, DEC-22, P-04).
3. Validar no Moodle 5.2 os itens de CL-02.
4. **Gate G-007**: cenários Q1–Q12 do [quickstart.md](quickstart.md).

*(Fases alteradas em 25/10/2026 por DEC-32; texto original: "1. Decidir o mecanismo interno (Q-007-02), a configuração do intervalo (Q-007-01) e a política de retry (Q-007-03). 2. Definir os estados de `processing`/`attempt` e sua relação com `event.status` (Q-007-04). 3. Definir a garantia de idempotência e concorrência (Q-007-05/08). 4. **Gate G-007**: cenários Q1–Q6 do [quickstart.md](quickstart.md).")*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-007-01 | Resolvido | Como o administrador configura o intervalo: configuração própria (não há tabela/campo no esquema) ou outro mecanismo do Moodle a verificar. **DEC-32 (D1):** agendamento nativo das Scheduled Tasks, padrão de 15 min, sem campo nem formulário próprio; validação em CL-02. *(Tipo original: "Aberto".)* | §Regras; spec-002; DEC-32 |
| Q-007-02 | Resolvido | O que é o "mecanismo interno para processar eventos": laço dentro da Scheduled Task ou Adhoc Tasks. **DEC-32 (D2):** laço na Scheduled Task, um evento por vez sob o lock do evento, sem Adhoc Tasks por evento. *(Tipo original: "Aberto".)* | §Contratos; DEC-32 |
| Q-007-03 | Resolvido | Momento da re-tentativa (mesmo ciclo ou próximo) e eventual espera entre tentativas. **DEC-32 (D3):** no máximo uma tentativa por ciclo; retentativa no ciclo seguinte, sem espera adicional. *(Tipo original: "Aberto".)* | §Regras; DEC-32 |
| Q-007-04 | Resolvido | Valores de `processing.status` e `attempt.status`, e mapeamento para `event.status` (ex.: `failure` após a 3ª falha). **DEC-32 (D4):** `attempt.status` ∈ {`processing`, `processed`, `failure`}; `processing.status` usa o vocabulário da spec-006; o evento volta a `pending` com tentativas restantes e passa a `failure` depois da 3ª falha. *(Tipo original: "Aberto".)* | §Contratos; spec-006; DEC-32 |
| Q-007-05 | Resolvido | Execuções concorrentes do ciclo: como evitar processamento duplo do mesmo evento. **Resolvido no nível funcional (25/10/2026):** Lock API por evento, um processamento lógico, recuperação depois de obter o mesmo lock (P-04 aprovado; DEC-16, DEC-22); validação técnica em CL-02. *(Tipo anterior: "Bloqueador".)* **Parcial (25/10/2026):** a spec define um único processamento lógico por evento, bloqueio por evento lógico, retentativas no mesmo processamento e recuperação sem novo processamento (DEC-16, DEC-22). Continua bloqueado: validar no Moodle 5.2 o backend de bloqueio e a recuperação de execução interrompida. | §Regras; constituição §8; DEC-16, DEC-22 |
| Q-007-06 | Resolvido | "Até esgotar a fila": limite de tempo/quantidade; eventos que chegam durante a execução. **DEC-32 (D5):** inclui os que chegam durante a execução; o ciclo se encerra quando a fila elegível se esgota ou quando chega o horário da próxima execução agendada, concluindo o evento em andamento; sem limite numérico novo. *(Tipo original: "Aberto".)* | §Regras; DEC-32 |
| Q-007-07 | Resolvido | Ordem de processamento (ex.: por `created_at`, que é indexado). **DEC-32 (D6):** `created_at` crescente. *(Tipo original: "Aberto".)* | spec-002; DEC-32 |
| Q-007-08 | Resolvido | Definição operacional de "idempotente" (o que não pode duplicar ao reprocessar). **Resolvido pela spec (25/10/2026), no nível funcional:** a repetição do mesmo evento lógico reutiliza o processamento existente; a tarefa concorrente não duplica resultados; a idempotência se aplica aos resultados (DEC-08, DEC-16, DEC-22). *(Tipo original: "Aberto".)* | §Regras; DEC-08 |
| Q-007-09 | Resolvido | A spec-007 diz "máximo de 3 tentativas do mesmo evento"; a spec-023 diz "máximo de 3 retries". Os textos não são equivalentes (3 retries = até 4 execuções). Ver Q-023-07. O registro de decisões traz DEC-09 (três tentativas totais, incluindo a inicial); o texto das specs ainda não foi atualizado. **Resolvido (25/10/2026):** as duas specs foram alinhadas em 22/10/2026 a "3 tentativas totais, incluindo a inicial" (DEC-09). *(Tipo original: "Contradição".)* | spec-007 §Regras; spec-023 §Regras; DEC-09 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |
| R-G3 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | Marcador `citeturn…` não resolvível. *(04/11/2026, etapa 5a: Aceito como limitação, por decisão do responsável: as citações não são resolvíveis; os marcadores (`cite` e `turn` com caracteres Unicode de uso privado U+E200, U+E202 e U+E201, que `grep citeturn` não encontra) são mantidos nos 8 `spec.md` e no `research.md` porque o texto original é preservado; as regras dessas frases foram confirmadas por decisões e validações, não pelas citações.)* | §Conformidade |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Eventos de fórum (toda postagem ou interação) são processados por esta fila; processar evento da síntese não obriga criar nova versão [REQ spec-007 §Regras] | DEC-03, DEC-04 | Sem mudança de desenho; a regra de versão é da spec-016 | Incorporada |
| 3 tentativas totais, incluindo a inicial; o contador persistido do plugin é a referência [REQ §Regras] | DEC-09 | Testing corrigido; Q-007-09 resolvida. Q-007-03 (momento da retentativa) e Q-007-04 (estados) continuam abertas | Incorporada |
| Repetição reutiliza o processamento; nova ocorrência tem processamento próprio [REQ §Regras] | DEC-08 | Q-007-08 resolvida | Incorporada |
| Evento lógico identificado pela identidade por tipo (spec-006) [REQ §Regras] | DEC-21 | Depende de Q-006-01/Q-006-04 | Incorporada; validação pendente *(04/11/2026, revisão dos planos: CL-02 FECHADA em 02/11/2026.)* |
| Um processamento lógico por evento; bloqueio por evento lógico; recuperação sem novo processamento; sem transação aberta durante a chamada à LLM [REQ §Regras] | DEC-16, DEC-22 | Q-007-05 parcial; §8 continua BLOQUEADO até a validação do backend de bloqueio (`closure-register.md` CL-02) *(04/11/2026, revisão da propagação: superado; CL-02 fechada em 02/11/2026, com T051 e V1 a V3, e a §5 deste plano passou a PASSA)* | Incorporada; validação pendente *(04/11/2026: validação concluída na CL-02)* |
| Agendamento nativo; laço sob lock por evento; uma tentativa por ciclo; estados; encerramento; ordem [REQ spec-007 §Regras] | DEC-32 | Q-007-01…04, 06 e 07 resolvidas; Q-007-05 resolvida no nível funcional; §8 passa; contrato, data-model, quickstart e research atualizados | Incorporada (25/10/2026); validação em CL-02 |

## Complexity Tracking

Nenhuma violação justificável.
