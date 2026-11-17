# Rastreabilidade — spec-023

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-030 | Limitar a três tentativas totais por processamento e persistir tentativas/falhas | spec-007; spec-023; decisão confirmada em 2026-10-21 | Confirmado |
| AT-031 | Garantir idempotência do processamento | spec-007; spec-023 | Confirmado, critério incompleto *(26/10/2026: critérios nos quickstarts 006, 007 e 022; validação técnica em CL-02)* *(04/11/2026, nota de resolução: resolvido por DEC-21 e DEC-31; CL-02 Fechada em 02/11/2026, com limitações residuais (`closure-register.md`); estado anterior preservado.)* |
| AT-038 | Contexto de atividade/questionário/fórum usa materiais da mesma seção | spec-008; spec-023 | Confirmado |
| AT-066 | Usar Form API/Output API/UI Moodle e testar unidade, integração, XMLDB, eventos/tasks, LLM mockada, regras, acesso, histórico e UI aplicável | spec-020–023; Etapa 3 §3.4; DEC-48 | Confirmado; runners, cobertura e entrega definidos por DEC-48 (26/10/2026) *(incluído em 26/10/2026)* |

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-01 | Frequência; Métricas de questionário |
| DEC-02 | Critérios; Frequência; Relatórios |
| DEC-03 | Fóruns |
| DEC-04 | Fóruns; Histórico e rastreabilidade; Síntese pedagógica |
| DEC-05 | Histórico e rastreabilidade; Materiais e contexto |
| DEC-06 | Relatórios |
| DEC-07 | Relatórios |
| DEC-08 | Fóruns; Histórico e rastreabilidade; Processamento assíncrono |
| DEC-09 | Processamento assíncrono |
| DEC-10 | Processamento assíncrono |
| DEC-11 | Critérios |
| DEC-12 | Histórico e rastreabilidade; Materiais e contexto; Síntese pedagógica |
| DEC-13 | Síntese pedagógica |
| DEC-14 | Síntese pedagógica |
| DEC-15 | Materiais e contexto |
| DEC-16 | Processamento assíncrono |
| DEC-17 | Relatórios |
| DEC-18 | Relatórios |
| DEC-19 | Histórico e rastreabilidade; Síntese pedagógica |
| DEC-20 | Histórico e rastreabilidade; Materiais e contexto |
| DEC-21 | Histórico e rastreabilidade; Processamento assíncrono |
| DEC-22 | Histórico e rastreabilidade; Processamento assíncrono |
| DEC-23 | Relatórios |
| DEC-24 | Relatórios |
| DEC-25 | Frequência |
| DEC-26 | Materiais e contexto *(incluída em 26/10/2026)* |
| DEC-29 | Esquema e persistência *(incluída em 26/10/2026)* |
| DEC-30 | Controle de acesso *(incluída em 26/10/2026)* |
| DEC-31 | Eventos de questionário *(incluída em 26/10/2026)* |
| DEC-32 | Execução da fila *(incluída em 26/10/2026)* |
| DEC-33 | Materiais: tipos, alteração e entrega 008 ↔ 009 *(incluída em 26/10/2026)* |
| DEC-34 | Integração com LLM externa *(incluída em 26/10/2026)* |
| DEC-35 | Análise inicial de questionário *(incluída em 26/10/2026)* |
| DEC-36 | Métricas de questionário *(incluída em 26/10/2026)* |
| DEC-37 | Notas e desempenho *(incluída em 26/10/2026)* |
| DEC-38 | Fóruns *(incluída em 26/10/2026)* |
| DEC-39 | Frequência *(incluída em 26/10/2026)* |
| DEC-40 | Critérios *(incluída em 26/10/2026)* |
| DEC-41 | Síntese pedagógica *(incluída em 26/10/2026)* |
| DEC-42 | Histórico e rastreabilidade *(incluída em 26/10/2026)* |
| DEC-43 | Controle de acesso *(incluída em 26/10/2026)* |
| DEC-44 | Relatórios *(incluída em 26/10/2026)* |
| DEC-45 | Controle de acesso *(incluída em 26/10/2026)* |
| DEC-46 | Integração com LLM externa *(incluída em 26/10/2026)* |
| DEC-47 | Processamento assíncrono *(incluída em 26/10/2026)* |
| DEC-48 | Testes *(incluída em 26/10/2026)* |

*(26/10/2026: DEC-27 e DEC-28, de ambiente, entram pela revisão do plano 001; texto original da lista ia até DEC-25.)*

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md). Nada foi executado. *(26/10/2026: a matriz associa cada cenário a PHPUnit, Behat, verificação do pesquisador ou CL; CL não conta como teste aprovado.)*
