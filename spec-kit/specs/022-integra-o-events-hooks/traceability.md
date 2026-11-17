# Rastreabilidade — spec-022

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-025 | Observer não executa diretamente chamada de LLM | spec-006; spec-022 | Confirmado *(26/10/2026: observer fino, também sem regra de negócio e sem evento em curso desabilitado)* |

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-03 | Fóruns |
| DEC-04 | Fóruns |
| DEC-08 | Fóruns; Processamento assíncrono |
| DEC-09 | Processamento assíncrono |
| DEC-10 | Processamento assíncrono |
| DEC-16 | Processamento assíncrono |
| DEC-21 | Processamento assíncrono |
| DEC-22 | Processamento assíncrono |
| DEC-31 | Eventos de questionário *(incluída em 26/10/2026)* |
| DEC-33 | Materiais *(incluída em 26/10/2026)* |
| DEC-36 | Métricas de questionário (gatilhos) *(incluída em 26/10/2026)* |
| DEC-37 | Notas e desempenho (gatilhos) *(incluída em 26/10/2026)* |
| DEC-38 | Fóruns (edição e exclusão) *(incluída em 26/10/2026)* |
| DEC-39 | Frequência (gatilhos de participação) *(incluída em 26/10/2026)* |
| DEC-42 | Histórico e rastreabilidade (exclusão de curso) *(incluída em 26/10/2026)* |
| DEC-45 | Controle de acesso (curso desabilitado) *(incluída em 26/10/2026)* |
| DEC-47 | Processamento assíncrono (sem gatilho de matrícula) *(incluída em 26/10/2026)* |

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q10). Validação técnica distribuída por gatilho em `closure-register.md` CL-02, CL-33 a CL-37, CL-40 e CL-41. Nada foi executado. *(Declaração da etapa de planejamento, mantida como histórico. Execuções posteriores estão registradas nas tarefas de `tasks.md` e, para o tipo interno `forum_rebuild_requested`, em `closure-register.md` CL-47 e na T121 da spec 020 (31/10/2026). Esta nota não afirma que os cenários desta spec tenham sido reexecutados.)*

## Atualização de 31/10/2026 (CL-47)

O catálogo de gatilhos do [plan.md](plan.md) ganhou o tipo lógico interno `forum_rebuild_requested` (DEC-38 D2, DEC-45 D3), autorizado pelo responsável em CL-47. Ele não tem evento Moodle nem observer, não entra em `assistente/db/events.php` e é enfileirado só pela sincronização inicial (spec 020, T121). Evidência e estado: `closure-register.md` CL-47.
