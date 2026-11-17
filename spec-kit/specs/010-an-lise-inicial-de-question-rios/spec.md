# spec-010 — Análise Inicial de Questionários

## Objetivo & Escopo

Analisar questionários publicados usando materiais da mesma seção.

## Dependências de Specs Anteriores

spec-004, spec-008, spec-009

## Contratos & Interfaces

Entrada:
- question_id/version;
- question;
- alternatives;
- teacher-provided answer;
- summaries of eligible same-section materials.

Saída:
- coverage do questionário;
- complexity por questão.

## Regras de Validação & Requisitos Técnicos

Coverage e complexity:
- faixa 0.0–1.0;
- passo 0.1.
Coverage é do questionário.
Complexity é por questão.
Não existe requisito de tipo de raciocínio.
Publicação do questionário gera um único evento.
A publicação ocorre quando o questionário passa a ficar visível aos estudantes pela primeira vez, seja criado já visível, seja tornado visível depois (spec-006). Alterações no questionário depois da publicação não geram novo evento nem nova análise inicial. *(Incluído em 25/10/2026 por DEC-31.)*
A seção da análise é derivada do `cmid` da atividade pela regra da spec-004, quando a relação for inequívoca. Esta spec não exige que toda análise tenha um único `cmid` ou uma única seção. *(Alterado em 21/10/2026 por decisão funcional sobre síntese por curso e seção; DEC-13 e decisões relacionadas, conforme aplicável.)*

## Conformidade Moodle

Questões devem ser lidas via APIs Moodle apropriadas; respostas/alternativas devem respeitar a estrutura do question type. O resultado LLM deve ser validado antes de persistir.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
