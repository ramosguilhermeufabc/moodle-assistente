# Rastreabilidade — spec-016

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-061 | Toda nova interação/evento elegível dispara atualização; criar versão da síntese somente quando uma nova síntese for efetivamente produzida | spec-016; decisão confirmada em 2026-10-21; DEC-41 | Confirmado; conjunto comparável, fingerprint e gatilhos definidos por DEC-41 (25/10/2026) |

*(25/10/2026: fonte original "spec-016; decisão confirmada em 2026-10-21"; estado original "Parcial; regra de versionamento confirmada em parte".)*

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-03 | Fóruns |
| DEC-04 | Fóruns; Síntese pedagógica |
| DEC-08 | Fóruns |
| DEC-12 | Síntese pedagógica |
| DEC-13 | Síntese pedagógica |
| DEC-14 | Síntese pedagógica |
| DEC-19 | Síntese pedagógica |
| DEC-34 | Integração com LLM externa (D3) *(incluída em 25/10/2026)* |
| DEC-38 | Fóruns (snapshot atual) *(incluída em 25/10/2026)* |
| DEC-41 | Síntese pedagógica *(incluída em 25/10/2026)* |

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q14). Validação técnica em `closure-register.md` CL-16 (seção) e CL-07 (Lock API). Nada foi executado.
