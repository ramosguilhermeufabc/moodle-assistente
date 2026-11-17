# Rastreabilidade — spec-006

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-020 | Criar evento próprio persistido para cada ocorrência do assistente | spec-006 | Confirmado |
| AT-021 | Persistir tipo, payload, curso, timestamps, estado e resposta do evento | spec-006; XMLDB local_assistente_event | Confirmado |
| AT-022 | Usar estados pending, processing, processed e failure para eventos | spec-006 | Confirmado |
| AT-023 | Publicação de questionário gera exatamente um evento | spec-006; spec-010 | Confirmado |
| AT-024 | Toda interação/postagem em fórum gera evento para atualização | spec-006; spec-013; decisão confirmada em 2026-10-21 | Confirmado por decisão aprovada |
| AT-025 | Observer não executa diretamente chamada de LLM | spec-006; spec-022 | Confirmado |

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

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md). Nada foi executado.
