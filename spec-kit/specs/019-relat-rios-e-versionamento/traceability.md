# Rastreabilidade — spec-019

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-065 | Manter um relatório lógico por curso, com versões; atualizar a cada 72 horas, contadas desde a última atualização bem-sucedida, até a data final mais distante e uma vez no dia seguinte *(alterado em 25/10/2026 conforme DEC-06; texto original: "atualizar a cada 3 dias até a data final mais distante e uma vez no dia seguinte")* | spec-019; decisões confirmadas em 2026-10-21; DEC-06, DEC-07, DEC-17, DEC-18; DEC-44 | Confirmado no nível funcional: regra base, fuso do site e "dia seguinte", reabertura e conteúdo do snapshot definidos (DEC-44); CL-06 fechado no nível funcional; validação técnica pendente em CL-39 *(alterado em 26/10/2026; texto original: "Regra base confirmada; casos de calendário parcialmente definidos (fuso e reabertura pendentes)")* |

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-02 | Relatórios |
| DEC-06 | Relatórios |
| DEC-07 | Relatórios |
| DEC-17 | Relatórios |
| DEC-18 | Relatórios |
| DEC-23 | Relatórios |
| DEC-24 | Relatórios |
| DEC-39 | Frequência (elegibilidade de atividades) *(incluída em 26/10/2026)* |
| DEC-42 | Histórico e rastreabilidade (privacidade; exclusão de curso; elo com o relatório) *(incluída em 26/10/2026)* |
| DEC-43 | Controle de acesso (grupos na leitura) *(incluída em 26/10/2026)* |
| DEC-44 | Relatórios *(incluída em 26/10/2026)* |

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q16). Validação técnica em `closure-register.md` CL-39. Nada foi executado.
