# Rastreabilidade — spec-014

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-058 | Calcular presença como participação pelo menos uma vez e percentual participating_activities / total_activities * 100 | spec-014; decisão confirmada em 2026-10-21; DEC-25; DEC-39 | Fórmula confirmada; regra de caso-limite parcialmente confirmada; elegibilidade parcialmente definida: categorias da spec-014 e DEC-25 cobertas, mapeamento no Moodle e casos-limite pendentes (CL-04) *(25/10/2026)* *(25/10/2026, DEC-39: elegibilidade, participação, datas, acesso e truncamento definidos; CL-04 fechado no nível funcional; validação em CL-36)* |
| AT-059 | Usar data final/devida da atividade como referência temporal da frequência | spec-014; DEC-39 | Confirmado; sem data própria, `course.enddate`; sem nenhuma, fora do cálculo (DEC-39 D2, 25/10/2026) |

*(25/10/2026: AT-059 atualizado; fonte original "spec-014", estado original "Confirmado".)*

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-01 | Frequência |
| DEC-02 | Frequência |
| DEC-24 | Relatórios (zero, `no_data`, `error`; aplicado à frequência) *(incluída em 25/10/2026)* |
| DEC-25 | Frequência |
| DEC-26 | Materiais e contexto (noção de disponibilidade usada na elegibilidade) *(incluída em 25/10/2026)* |
| DEC-39 | Frequência *(incluída em 25/10/2026)* |

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q14). Validação técnica em `closure-register.md` CL-36. Nada foi executado.
