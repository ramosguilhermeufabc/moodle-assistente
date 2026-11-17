# spec-001 — Infraestrutura & Ambiente GitHub Codespaces

> **Nota de 08/11/2026 (Fase 3 da reorganização documental).** Foram removidos 4 marcadores de citação que a ferramenta geradora do texto original deixou neste arquivo, delimitados por caracteres invisíveis de uso privado (U+E200 a U+E202). Eles não apareciam no Markdown renderizado, quebravam a busca textual e apontavam fontes não resolvíveis: `turn0search13` e equivalentes não identificam nenhum documento consultável. **Nenhuma palavra, regra, valor ou condição do texto foi alterada**; apenas os marcadores e o espaço que os precedia saíram. Texto removido, preservado conforme a constituição §11: `citeturn1search6turn1search7`, `citeturn1search2`, `citeturn1search6turn1search9`, `citeturn0search9`. As afirmações que os traziam ficam sem citação de fonte e dependem de verificação na documentação oficial do Moodle 5.2.

## Objetivo & Escopo

Definir o ambiente reprodutível de desenvolvimento em GitHub Codespaces para executar Moodle 5.2 e o plugin `local_assistente`.

O histórico deste projeto já fixa:
- GitHub Codespaces;
- Docker/containers;
- Moodle `MOODLE_502_STABLE`;
- raiz de trabalho do plugin `assistente/`, na raiz do repositório, no mesmo nível de `.devcontainer/` (referenciada a partir de `.devcontainer/` como `../assistente`); *(Esclarecido em 25/10/2026 por DEC-27; texto original: "raiz de trabalho do plugin `assistente/`;")*
- plugin montado/enxergado pelo Moodle em `/var/www/html/public/local/assistente`. *(Alterado em 25/10/2026 por DEC-27; texto original: "plugin montado/enxergado pelo Moodle em `/var/www/html/local/assistente`.")*

A especificação de infraestrutura deve detalhar o mecanismo de dev container sem inventar valores ainda não fornecidos.

## Dependências de Specs Anteriores

—

## Contratos & Interfaces

Artefatos esperados:
- `.devcontainer/devcontainer.json` como ponto principal de configuração;
- Dockerfile **ou** Docker Compose, conforme a decisão final de ambiente;
- scripts de criação/pós-criação;
- configuração de portas encaminhadas;
- variáveis de ambiente não secretas; (03/11/2026, DEC-69: a credencial da API LLM não usa secret do Codespaces nem variável de ambiente; é configurada só pela administração do Moodle, gravada criptografada)
- secrets para credenciais da API LLM; (03/11/2026, DEC-69: a credencial da API LLM não usa secret do Codespaces nem variável de ambiente; é configurada só pela administração do Moodle, gravada criptografada)
- montagem do plugin em `/var/www/html/public/local/assistente`, que é a solução adotada; no Docker Compose, a montagem esperada é `../assistente:/var/www/html/public/local/assistente`. *(Alterado em 25/10/2026 por DEC-27, solução de montagem confirmada na revisão do diagnóstico; texto original: "montagem/sincronização do plugin para `/var/www/html/local/assistente`.")*

O `devcontainer.json` pode definir imagem, features, `forwardPorts`, `postCreateCommand`, customizações e usuário remoto. GitHub documenta `devcontainer.json` como configuração primária e permite Dockerfile/Compose.

## Regras de Validação & Requisitos Técnicos

1. O ambiente deve iniciar um Moodle 5.2 funcional.
2. O plugin deve ser visível no caminho `/var/www/html/public/local/assistente`. *(Alterado em 25/10/2026 por DEC-27; texto original: "O plugin deve ser visível no caminho `/var/www/html/local/assistente`.")*
3. O workspace deve preservar o repositório Git.
4. O `postCreateCommand` deve realizar apenas preparação reprodutível.
5. Segredos da API externa não devem ser gravados no Git.
6. Variáveis sensíveis devem usar Codespaces development secrets; GitHub recomenda secrets para valores que não devem ser commitados. (03/11/2026, DEC-69: a credencial da API LLM não usa secret do Codespaces nem variável de ambiente; é configurada só pela administração do Moodle, gravada criptografada)
7. Exatos valores de imagem, portas, banco, credenciais, comandos e serviços auxiliares ainda não foram definidos no histórico e devem ser decididos no `plan.md`, não inventados nesta spec.

## Conformidade Moodle

GitHub Codespaces cria um ambiente baseado em container Docker; `devcontainer.json` define a configuração do dev container e pode referenciar Dockerfile. `postCreateCommand` é executado após a criação do ambiente.
Moodle 5.2 exige PHP >= 8.3 e suporta PHP 8.4; requisitos de banco devem ser escolhidos de acordo com a matriz oficial do Moodle 5.2.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
