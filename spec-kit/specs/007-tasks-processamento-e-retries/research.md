# Research — spec-007 (Fase 0)

> Sem pesquisa externa (PROMPT-3).

## Decididos pela spec
- **Decision**: fila lógica própria executada via Tasks API (Scheduled Task para o ciclo periódico). **Rationale**: [REQ §Contratos, §Conformidade]. **Alternatives considered**: n/a.
- **Decision**: intervalo inicial de 15 min, configurável pelo administrador. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.
- **Decision**: no máximo 3 tentativas por evento; tentativas e falhas persistidas; processamento idempotente. **Rationale**: [REQ §Regras]. **Alternatives considered**: n/a.

## Abertos
*(Coluna "Decision" alterada em 25/10/2026 por DEC-32, DEC-08, DEC-16, DEC-22 e P-04; valor original em todas as linhas: "ABERTO", e "ABERTO — decisão humana" em Q-007-02.)*

| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-007-01 Configuração do intervalo | RESOLVIDO (DEC-32 D1): agendamento nativo das Scheduled Tasks, padrão de 15 min, sem formulário próprio | Sem campo no esquema | configuração própria do plugin (não adotada) |
| Q-007-02 Mecanismo interno | RESOLVIDO (DEC-32 D2): laço na Scheduled Task, um evento por vez sob o lock do evento | Ambas as opções são citadas pela spec (Scheduled/Adhoc) | laço na Scheduled Task; Adhoc Tasks (não adotadas, por causa das retentativas automáticas) |
| Q-007-03 Política de retry | RESOLVIDO (DEC-32 D3): uma tentativa por ciclo; retentativa no ciclo seguinte | Não definida (até 25/10/2026) | retentativa imediata no mesmo ciclo (não adotada) |
| Q-007-04 Estados | RESOLVIDO (DEC-32 D4): `processing`, `processed`, `failure`; retorno a `pending` com tentativas restantes | Não definidos (até 25/10/2026) | — |
| Q-007-05 Concorrência | RESOLVIDO no nível funcional (DEC-16, DEC-22, P-04): Lock API por evento; validação em CL-02 | Não definida (até 22/10/2026) | — |
| Q-007-06 Limites do lote | RESOLVIDO (DEC-32 D5): encerra com a fila elegível esgotada ou ao chegar o horário da próxima execução; sem limite numérico | Não definidos (até 25/10/2026) | limite numérico de quantidade ou tempo (não adotado por falta de evidência) |
| Q-007-07 Ordem | RESOLVIDO (DEC-32 D6): `created_at` crescente | Não definida (até 25/10/2026) | — |
| Q-007-08 Idempotência | RESOLVIDO (DEC-08): a repetição reutiliza o processamento; sem resultado duplicado | Não definida (até 22/10/2026) | — |
