# spec-013 — Fóruns e Dificuldades

## Objetivo & Escopo

Gerar resumo de cada fórum e dificuldades por discussão/tópico.

## Dependências de Specs Anteriores

spec-008, spec-009, spec-007, spec-004

## Contratos & Interfaces

Entrada:
- fórum;
- resumo corrente;
- nova mensagem;
- materiais elegíveis da mesma seção.

Saída:
- resumo do fórum;
- `local_assistente_forum_difficulty` por discussão.

## Regras de Validação & Requisitos Técnicos

Toda postagem ou interação no fórum gera evento e dispara o fluxo de atualização, respeitando o processamento assíncrono. *(Alterado em 22/10/2026 por DEC-03; texto original: "Cada atualização relevante gera evento.")*
Análise incremental usa resumo atual + nova mensagem + contexto da seção.
Histórico deve ser preservado.
A seção da análise é derivada do `cmid` da atividade pela regra da spec-004, quando a relação for inequívoca. Esta spec não exige que toda análise tenha um único `cmid` ou uma única seção. *(Alterado em 21/10/2026 por decisão funcional sobre síntese por curso e seção; DEC-13 e decisões relacionadas, conforme aplicável.)*

## Conformidade Moodle

Usar APIs Moodle do módulo fórum e Events API. Nomes exatos de eventos devem ser verificados na versão 5.2.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
