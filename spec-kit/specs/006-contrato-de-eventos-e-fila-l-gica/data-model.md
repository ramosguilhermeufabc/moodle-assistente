# Data Model — spec-006

## `local_assistente_event`
Campos (spec-002 §Inventário): `course_id` (FK `course`), `event_type` char(100), `payload` text, `created_at`, `status` char(20), `processed_at` (nullable), `response` (nullable).

Regras:
- Um evento = uma ocorrência [REQ §Regras].
- `status` ∈ {`pending`, `processing`, `processed`, `failure`} [REQ §Regras].
- Questionário publicado → exatamente 1 evento por questionário (primeira visibilidade aos estudantes) [REQ §Regras; DEC-31]. O mecanismo é a estratégia por tipo (DEC-21), sem campo novo (P-01; risco R-006-01). *(Alterado em 25/10/2026; texto original: "Mecanismo: Q-006-04.")*

## Estados
```text
pending → processing → processed
                    ↘ failure
```
Somente os quatro estados estão especificados. As setas acima são **[ESCOLHA]** de leitura (ordem natural dos nomes) e precisam de confirmação (Q-006-06). Os retornos `processing → pending` entre tentativas estão em Q-007-04. *(25/10/2026: confirmadas por DEC-32 D4, que inclui o retorno a `pending` depois de uma falha com tentativas restantes e `failure` depois da 3ª falha.)*
