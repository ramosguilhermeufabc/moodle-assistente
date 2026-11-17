# Rastreabilidade — spec-017

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-035 | Cada alteração de material gera nova versão preservando a anterior | spec-008; spec-017 | Confirmado |
| AT-039 | Criar snapshot imutável do contexto por processamento | spec-008; spec-017 | Confirmado *(26/10/2026: única exceção, a remoção de entradas identificáveis por solicitação de privacidade, DEC-42 D1)* |
| AT-042 | Rastrear todos os materiais e versões efetivamente usados | spec-008; spec-017 | Confirmado |
| AT-062 | Manter cadeia de rastreabilidade source -> event -> processing -> attempt -> snapshot -> analysis -> result -> report | spec-017; Constituição §7 *(1.1.0; referência original: §9 da constituição do pacote modular, ajustada em 25/10/2026)*; DEC-42 | Confirmado *(26/10/2026: elo resultado → relatório pelo `snapshot_json`)* |
| AT-063 | Preservar estados anteriores e histórico quando fonte é atualizada/removida | spec-008; spec-017; Constituição §7 *(1.1.0; referência original: §10 da constituição do pacote modular, ajustada em 25/10/2026)*; DEC-42 | Confirmado; exceções por DEC-42 D1/D2 (26/10/2026) |

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-04 | Histórico e rastreabilidade; Síntese pedagógica |
| DEC-05 | Histórico e rastreabilidade; Materiais e contexto |
| DEC-08 | Histórico e rastreabilidade |
| DEC-12 | Histórico e rastreabilidade; Materiais e contexto; Síntese pedagógica |
| DEC-13 | Síntese pedagógica |
| DEC-14 | Síntese pedagógica |
| DEC-15 | Materiais e contexto |
| DEC-19 | Histórico e rastreabilidade; Síntese pedagógica |
| DEC-20 | Histórico e rastreabilidade; Materiais e contexto |
| DEC-21 | Histórico e rastreabilidade |
| DEC-22 | Histórico e rastreabilidade |
| DEC-38 | Fóruns (reconstrução e histórico na exclusão) *(incluída em 26/10/2026)* |
| DEC-42 | Histórico e rastreabilidade *(incluída em 26/10/2026)* |

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q12). Validação técnica em `closure-register.md` CL-37. Nada foi executado.
