# Rastreabilidade — spec-020

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-018 | Critérios são definidos na configuração e imutáveis depois | spec-005; spec-020; DEC-45 | Confirmado; alcance definido por DEC-45 D2 (26/10/2026) |
| AT-066 | Usar Form API/Output API/UI Moodle e testar unidade, integração, XMLDB, eventos/tasks, LLM mockada, regras, acesso, histórico e UI aplicável | spec-020–023; Etapa 3 §3.4 | Confirmado |

*(26/10/2026: AT-018 com fonte original "spec-005; spec-020" e estado original "Confirmado". AT-016 e AT-019, também afetados por DEC-45, ficam na matriz global e no plano 005.)*

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-02 | Critérios |
| DEC-11 | Critérios |
| DEC-30 | Controle de acesso (leitura) *(incluída em 26/10/2026)* |
| DEC-32 | Execução da fila (Q-020-05) *(incluída em 26/10/2026)* |
| DEC-40 | Critérios (unidade e faixas) *(incluída em 26/10/2026)* |
| DEC-43 | Controle de acesso (filtro de grupo) *(incluída em 26/10/2026)* |
| DEC-44 | Relatórios (filtros de versão) *(incluída em 26/10/2026)* |

*(29/10/2026: Q-020-02, filtros de grupo, seção, versão e data de execução: DEC-43 e DEC-44, critérios CA-1 a CA-10 do plano, cenários Q15–Q26 do quickstart, tarefa T143.)*
| DEC-45 | Controle de acesso (gravação, imutabilidade e habilitação) *(incluída em 26/10/2026)* |

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q14). Validação técnica em `closure-register.md` CL-40. Nada foi executado. *(Declaração da etapa de planejamento, mantida como histórico. Execuções posteriores estão registradas nas tarefas de `tasks.md`; para a sincronização inicial, Q9 a Q13 foram executados em 29/10/2026 e Q10 foi ampliado com o fórum e reexecutado em 31/10/2026 por `assistente/tests/application/sync/initial_sync_service_test.php` (ver T121). Esta nota não afirma execução de nenhum outro cenário.)*

## Atualização de 31/10/2026 (CL-47, T121)

| Item | Rastro |
|---|---|
| Reconstrução completa dos fóruns na sincronização inicial (DEC-45 D3; DEC-38 D2) | Evento interno `forum_rebuild_requested` (sem evento Moodle e sem observer), um por fórum do curso, enfileirado por `initial_sync_service`; catálogo do plano 022; `closure-register.md` CL-47 (Fechado em 31/10/2026; estava Aguardando revisão) |
| Cenário Q10 | Passa a incluir o fórum: [quickstart.md](quickstart.md) Q10; teste `assistente/tests/application/sync/initial_sync_service_test.php` |
| Tarefa | T121 em [tasks.md](tasks.md), com a evidência executada e o que resta |
| Decisões | Nenhuma decisão foi criada ou reaberta; DEC-38 e DEC-45 preservadas. A criação do tipo de evento foi autorizada pelo responsável (CL-47) |
