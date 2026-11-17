# Rastreabilidade — spec-002

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-007 | Usar install.xml como fonte de verdade do esquema | spec-002; Constituição §1 e §12 *(1.1.0; referência original: §3 da constituição do pacote modular, ajustada em 25/10/2026)* | Confirmado |
| AT-008 | Manter 21 tabelas e definições sincronizadas com XMLDB | spec-002 | Confirmado |
| AT-009 | Usar DML para dados e XMLDB/upgrade para mudanças estruturais | spec-002; spec-003 | Confirmado |

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-29 | Esquema e persistência |
| DEC-32 | Execução da fila (domínios de status) |
| DEC-33 | Materiais (tipos) |
| DEC-38 | Fóruns (resumo em `analysis.summary`) |
| DEC-39 | Frequência (`participated`) |
| DEC-40 | Critérios (`forum_criterion`, `*_status`) |
| DEC-42 | Histórico e rastreabilidade (fontes removidas) |
| DEC-43 | Controle de acesso (turma sem campo) |
| DEC-44 | Relatórios (manifesto) |
| DEC-45 | Controle de acesso (`enabled`) |

*(Incluído em 26/10/2026; texto original: "Nenhuma decisão tem esta spec listada na seção 3 do registro.")*

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md). Nada foi executado. *(26/10/2026: Q1–Q9; Q7–Q8 são validação técnica em CL-07 e CL-37.)*
