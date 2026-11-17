# spec-012 — Notas e Desempenho

## Objetivo & Escopo

Consultar e persistir o desempenho por estudante/atividade usando a nota final do Moodle.

## Dependências de Specs Anteriores

spec-004, spec-007

## Contratos & Interfaces

Persistência:
`local_assistente_performance`

Entrada:
student_id + activity/cmid + grade final do gradebook.

## Regras de Validação & Requisitos Técnicos

Não criar pesos próprios.
Usar a nota final disponibilizada pelo Moodle gradebook respeitando a configuração de avaliação do Moodle.
A média de notas do estudante deve derivar dessas notas conforme configuração Moodle.

## Conformidade Moodle

A fonte de notas deve ser a API/estrutura oficial de gradebook do Moodle, não uma regra de peso criada pelo assistente.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
