# spec-003 — API Interna de Persistência e `lib.php`

> **Nota de 08/11/2026 (Fase 3 da reorganização documental).** Foram removidos 1 marcador de citação que a ferramenta geradora do texto original deixou neste arquivo, delimitado por caracteres invisíveis de uso privado (U+E200 a U+E202). Eles não apareciam no Markdown renderizado, quebravam a busca textual e apontavam fontes não resolvíveis: `turn0search13` e equivalentes não identificam nenhum documento consultável. **Nenhuma palavra, regra, valor ou condição do texto foi alterada**; apenas os marcadores e o espaço que os precedia saíram. Texto removido, preservado conforme a constituição §11: `citeturn0search3turn0search0`. As afirmações que os traziam ficam sem citação de fonte e dependem de verificação na documentação oficial do Moodle 5.2.

## Objetivo & Escopo

Criar a especificação da camada que encapsula persistência e evita acoplamento direto do domínio ao `$DB`.

`lib.php` não deve conter lógica central; deve ser reservado a callbacks necessários.

## Dependências de Specs Anteriores

spec-002

## Contratos & Interfaces

Interfaces lógicas:
- repositories para aggregates próprios;
- gateway/reader para dados derivados;
- operações DML;
- callbacks Moodle em `lib.php` somente quando não houver mecanismo moderno adequado.

O domínio não conhece SQL.

## Regras de Validação & Requisitos Técnicos

- Usar `$DB`/DML exclusivamente na infraestrutura.
- Não usar prefixo físico de tabela nas chamadas DML.
- Não colocar lógica de negócio em `lib.php`.
- Operações multi-tabela devem usar transações quando necessário.
- Respeitar as chaves e índices do XMLDB.

## Conformidade Moodle

A documentação Moodle afirma que DML deve ser usado para recuperar/modificar dados e abstrai diferenças entre SGBDs. O plugin local deve manter `lib.php` pequeno e lógica em classes autocarregáveis.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
