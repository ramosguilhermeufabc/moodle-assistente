# spec-021 — Navegação, Renderização e UI/JS

> **Nota de 08/11/2026 (Fase 3 da reorganização documental).** Foram removidos 1 marcador de citação que a ferramenta geradora do texto original deixou neste arquivo, delimitado por caracteres invisíveis de uso privado (U+E200 a U+E202). Eles não apareciam no Markdown renderizado, quebravam a busca textual e apontavam fontes não resolvíveis: `turn0search13` e equivalentes não identificam nenhum documento consultável. **Nenhuma palavra, regra, valor ou condição do texto foi alterada**; apenas os marcadores e o espaço que os precedia saíram. Texto removido, preservado conforme a constituição §11: `citeturn0search0`. As afirmações que os traziam ficam sem citação de fonte e dependem de verificação na documentação oficial do Moodle 5.2.

## Objetivo & Escopo

Definir a apresentação do assistente dentro do contexto do curso.

## Dependências de Specs Anteriores

spec-018, spec-019, spec-020

## Contratos & Interfaces

Camadas:
- pages/controllers;
- Output API;
- renderables;
- templates Mustache quando aplicável;
- AMD/JS apenas quando comportamento cliente for necessário.

## Regras de Validação & Requisitos Técnicos

A UI deve:
- respeitar contexto/capability;
- separar funções de consulta;
- mostrar versão atual;
- permitir histórico;
- indicar fontes removidas quando aplicável;
- distinguir ausência de dados suficientes (`no_data`) de um zero real (`valid`) e erro técnico (`error`) de resultado válido; *(Incluído em 22/10/2026 por DEC-24 e contrato P-06.)*
- exibir estudantes com pontuação igual em ordem alfabética crescente do nome. *(Incluído em 22/10/2026 por DEC-02.)*
Não introduzir chat natural-language.

## Conformidade Moodle

Local plugins podem alterar navegação; Moodle recomenda manter `lib.php` pequeno e lógica em classes autocarregáveis.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
