# Rastreabilidade — spec-003

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-009 | Usar DML para dados e XMLDB/upgrade para mudanças estruturais | spec-002; spec-003 | Confirmado |
| AT-010 | Encapsular persistência em repositórios; domínio não conhece SQL | spec-003 | Confirmado |
| AT-011 | Manter lib.php mínimo, apenas callbacks necessários | Constituição §12 *(1.1.0; referência original: §6 da constituição do pacote modular, ajustada em 25/10/2026)*; spec-003 | Confirmado |

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-15, DEC-20 | Materiais e contexto (transição de versão) |
| DEC-16, DEC-22, DEC-32 | Processamento assíncrono; Execução da fila (transições e lock) |
| DEC-23, DEC-44 | Relatórios (versão e reabertura) |
| DEC-29 | Esquema e persistência |
| DEC-38 | Fóruns (snapshot atômico) |
| DEC-41 | Síntese pedagógica (versão sob lock) |
| DEC-42 | Histórico e rastreabilidade (exclusões) |
| DEC-45 | Controle de acesso (configuração) |
| DEC-48 | Testes (PHPUnit) |

*(Incluído em 26/10/2026; texto original: "Nenhuma decisão tem esta spec listada na seção 3 do registro.")*

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md). Nada foi executado. *(26/10/2026: Q1–Q10; Q10 é validação técnica em CL-07.)*
