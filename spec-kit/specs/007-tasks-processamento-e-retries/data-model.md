# Data Model — spec-007

## `local_assistente_processing`
`event_id` (foreign-unique → `event`), `status` char(20), `created_at`, `finished_at` (nullable). Relação 1:1 com `event` [REQ §Contratos].

## `local_assistente_processing_attempt`
`processing_id` (FK), `attempt_number`, `started_at`, `finished_at` (nullable), `status` char(20), `response` (nullable). Único `processing_id,attempt_number`.
- Regra: `attempt_number` ≤ 3 [REQ §Regras]. O esquema não impõe esse limite; a aplicação deve garantir.

## Estados
- `attempt.status` ∈ {`processing`, `processed`, `failure`}; `processing.status` usa o vocabulário da spec-006 (DEC-32 D4).
- Relação com `event.status` (spec-006): depois de uma falha, o evento volta a `pending` se restarem tentativas e passa a `failure` depois da 3ª falha (DEC-32 D4).

*(Alterado em 25/10/2026; texto original: "`processing.status`, `attempt.status`: valores NEEDS CLARIFICATION (Q-007-04)." (04/11/2026: resolvido: DEC-32 D4 (Q-007-04 resolvida no plano 007)) / "Relação com `event.status` (spec-006): ao fim de 3 tentativas falhas o evento fica em `failure` [ESCOLHA de leitura, a confirmar em Q-007-04].")*
