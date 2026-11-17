# Rastreabilidade — spec-012

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-055 | Persistir desempenho por estudante/atividade com nota final do gradebook, sem pesos próprios | spec-012; DEC-37 | Confirmado; gatilho, unidade e média definidos por DEC-37 (25/10/2026) |

*(25/10/2026: colunas "Fonte" e "Estado" atualizadas depois da DEC-37; textos originais: "spec-012" e "Confirmado".)*

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-24 | Relatórios (zero, `no_data`, `error`; aplicado ao desempenho) *(incluída em 25/10/2026)* |
| DEC-37 | Notas e desempenho *(incluída em 25/10/2026)* |

*(Texto original: "Nenhuma decisão tem esta spec listada na seção 3 do registro.")*

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q11). Validação técnica em `closure-register.md` CL-34. Nada foi executado.
