# spec-014 — Frequência e Participação

## Objetivo & Escopo

Calcular frequência por tópico/atividades usando as interações definidas no SDD.

## Dependências de Specs Anteriores

spec-004, spec-007

## Contratos & Interfaces

Fontes:
- acesso ao curso;
- atividades avaliadas/conclusão/entrega;
- participação em fóruns.

Persistência:
`local_assistente_frequency`
`local_assistente_frequency_activity`.

## Regras de Validação & Requisitos Técnicos

Presença:
participou pelo menos uma vez = presente.
Percentual:
`participating_activities / total_activities * 100`.
Exemplo: 1/3 = 33,33%.
Quando `total_activities` for zero, o resultado numérico é 0 com estado `no_data`. Um zero obtido com denominador positivo é resultado válido (`valid`). Falha técnica tem estado `error` e não é convertida em zero. O arredondamento continua em aberto (plano 014, Q-014-05). *(Incluído em 22/10/2026 por DEC-01, DEC-24 e contrato P-06.)* *(04/11/2026, revisão da propagação: o arredondamento foi decidido pela DEC-39: percentual armazenado truncado a três casas e exibido truncado a duas, como na DEC-36 D2; Q-014-05 resolvida. Texto anterior, preservado: "O arredondamento continua em aberto (plano 014, Q-014-05)".)*
Referência temporal: data final/devida da atividade.
O acesso ao curso é evidência de presença e não é atividade contabilizada em `total_activities`. O denominador conta as atividades elegíveis das fontes acima (atividades avaliadas, com conclusão ou com entrega, e participação em fóruns); o numerador conta a participação registrada nessas atividades. Uma mesma atividade conta uma única vez por estudante e seção, mesmo com vários eventos. Não se inferem categorias além dessas fontes. *(Incluído em 25/10/2026 por DEC-25.)*

## Conformidade Moodle

Usar APIs Moodle de completion/atividade/fórum e DML quando necessário. Persistir apenas resultados derivados próprios.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
