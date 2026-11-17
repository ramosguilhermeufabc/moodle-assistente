# Rastreabilidade — spec-004

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-012 | Isolar leitura dos dados originais do Moodle em readers/adapters | spec-004 | Confirmado |
| AT-013 | Usar APIs Moodle preferencialmente; DML quando não houver API adequada | spec-004 | Confirmado |
| AT-014 | Ler notas finais do gradebook e identificar questões por question_id + question_version | spec-004 | Confirmado |

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-26 | Materiais e contexto (disponibilidade por usuário) |
| DEC-33 | Materiais (tipos) |
| DEC-35, DEC-36 | Análise inicial e métricas de questionário (`mod_quiz`) |
| DEC-37 | Notas e desempenho |
| DEC-39 | Frequência (participação e acesso) |
| DEC-41 | Síntese pedagógica (resolução de seção) |
| DEC-43, DEC-47 | Controle de acesso; Processamento assíncrono (grupos, matrícula) |
| DEC-44, DEC-46 | Relatórios; Integração com LLM externa (fuso e idioma) |

*(Incluído em 26/10/2026; texto original: "Nenhuma decisão tem esta spec listada na seção 3 do registro.")*

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md). Nada foi executado. *(26/10/2026: Q1–Q17; Q11–Q17 são validação técnica nas CL indicadas.)*
