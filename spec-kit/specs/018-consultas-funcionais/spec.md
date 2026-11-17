# spec-018 — Consultas Funcionais

## Objetivo & Escopo

Disponibilizar consultas predefinidas no Moodle, sem perguntas livres em linguagem natural.

## Dependências de Specs Anteriores

spec-005, spec-011, spec-012, spec-013, spec-014, spec-015, spec-016, spec-017

## Contratos & Interfaces

Consultas por curso/turma e função:
- notas;
- métricas de questionários;
- frequência;
- fóruns;
- alunos abaixo dos critérios;
- resumos de materiais;
- síntese;
- histórico.

## Regras de Validação & Requisitos Técnicos

Cada consulta só mostra dados definidos pelas specs correspondentes.
Organização por estudante quando aplicável.
Estudantes com pontuação igual são exibidos em ordem alfabética crescente do nome. *(Incluído em 22/10/2026 por DEC-02.)*
Professor/admin respeitam escopo.
Nenhum indicador adicional deve ser inventado.

## Conformidade Moodle

A UI deverá usar Output API/Form API nas specs superiores.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
