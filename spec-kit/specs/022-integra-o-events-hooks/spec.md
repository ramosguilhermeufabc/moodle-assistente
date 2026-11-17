# spec-022 — Integração Events/Hooks

> **Nota de 08/11/2026 (Fase 3 da reorganização documental).** Foram removidos 1 marcador de citação que a ferramenta geradora do texto original deixou neste arquivo, delimitado por caracteres invisíveis de uso privado (U+E200 a U+E202). Eles não apareciam no Markdown renderizado, quebravam a busca textual e apontavam fontes não resolvíveis: `turn0search13` e equivalentes não identificam nenhum documento consultável. **Nenhuma palavra, regra, valor ou condição do texto foi alterada**; apenas os marcadores e o espaço que os precedia saíram. Texto removido, preservado conforme a constituição §11: `citeturn0search14`. As afirmações que os traziam ficam sem citação de fonte e dependem de verificação na documentação oficial do Moodle 5.2.

## Objetivo & Escopo

Conectar o plugin aos pontos de extensão do Moodle sem espalhar regras de negócio.

## Dependências de Specs Anteriores

spec-006, spec-007, spec-008, spec-010–021

## Contratos & Interfaces

Events:
Moodle event -> observer -> assistant event.

Hooks:
usar somente quando houver hook Moodle apropriado e necessário.

Observer/hook deve ser fino e delegar para application services.

## Regras de Validação & Requisitos Técnicos

Não executar LLM no request/observer.
Não inventar evento/hook.
Validar o evento Moodle na versão 5.2.
A identidade de deduplicação é definida por tipo concreto de evento, sem presumir identificador universal; as limitações de cada tipo devem ser documentadas. *(Incluído em 22/10/2026 por DEC-10 e DEC-21.)*
A repetição da mesma ocorrência reutiliza o processamento existente; uma nova ocorrência legítima cria novo evento. *(Incluído em 22/10/2026 por DEC-08.)*
A publicação de questionário é observada nos eventos de criação e atualização de módulo, com verificação de visibilidade e de publicação anterior, conforme a spec-006. *(Incluído em 25/10/2026 por DEC-31.)*
Concorrência, tentativas e recuperação do processamento seguem a spec-007: um único processamento lógico por evento, bloqueio por evento lógico e no máximo 3 tentativas totais, incluindo a inicial. *(Incluído em 22/10/2026 por DEC-16 e DEC-22.)*
Eventos próprios continuam persistidos na fila.
Toda postagem ou interação no fórum é observada e gera evento próprio; o fluxo de atualização ocorre de forma assíncrona, fora do observer. *(Incluído em 22/10/2026 por DEC-03.)*

## Conformidade Moodle

Moodle Hooks API usa PSR-14 e substitui parte dos callbacks one-to-many antigos. Events API permanece adequada para observar eventos.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
