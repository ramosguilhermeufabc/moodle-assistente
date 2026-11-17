# spec-020 — Form API

## Objetivo & Escopo

Definir formulários Moodle para configuração, critérios e filtros, sem colocar regras de domínio na camada de apresentação.

## Dependências de Specs Anteriores

spec-005, spec-018, spec-019

## Contratos & Interfaces

Usar Form API (`moodleform`/formslib) para:
- configuração;
- critérios;
- filtros de consulta/relatório.

Fluxo:
form -> validation -> application service.

## Regras de Validação & Requisitos Técnicos

Critérios imutáveis depois da configuração.
Os formulários não redefinem a regra de comparação dos critérios, definida na spec-005. *(Incluído em 22/10/2026 por DEC-11.)*
Validação de autorização antes de persistir.
Sanitização/validação devem seguir convenções Moodle.

## Conformidade Moodle

A Form API é uma API core do Moodle para entrada via formulários. Deve ser usada em vez de HTML/POST manual quando houver formulário Moodle.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
