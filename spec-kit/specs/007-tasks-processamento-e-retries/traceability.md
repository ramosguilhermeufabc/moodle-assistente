# Rastreabilidade — spec-007

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-026 | Usar Scheduled Task para varrer/processar a fila própria | spec-007; DEC-32 | Confirmado (DEC-32 D2) |
| AT-027 | Criar um processing para cada evento (relação 1:1) | spec-007; XMLDB | Confirmado |
| AT-028 | Executar ciclo inicial a cada 15 minutos, com intervalo configurável por administrador | spec-007; DEC-32 | Confirmado (DEC-32 D1) |
| AT-029 | Processar eventos pendentes em lote até esgotar a fila | spec-007; DEC-32 | Confirmado (DEC-32 D5) |
| AT-030 | Limitar a três tentativas totais por processamento e persistir tentativas/falhas | spec-007; spec-023; decisão confirmada em 2026-10-21 | Confirmado (DEC-32 D3/D4) |
| AT-031 | Garantir idempotência do processamento | spec-007; spec-023 | Confirmado, critério incompleto *(04/11/2026, nota de resolução: resolvido por DEC-21 e DEC-31; CL-02 Fechada em 02/11/2026, com limitações residuais (`closure-register.md`); estado anterior preservado.)* |

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
