# moodle-assistente

Assistente Inteligente para Apoio às Atividades Docentes no Moodle — plugin `local_assistente`
para Moodle 5.2. Acompanha questionários, notas, fóruns, participação e materiais de um curso,
processa os eventos de forma assíncrona, aplica os critérios configurados pelo professor e
apresenta consultas, síntese pedagógica, histórico e relatórios versionados.

O projeto é conduzido por desenvolvimento orientado por especificações (Spec Kit). Este arquivo é
a porta de entrada do repositório e o mapa do código. Se você está assumindo a manutenção
agora, comece por `spec-kit/onboarding.md`. Ele não é a fonte do estado do projeto, da
ordem de execução nem das decisões: veja "Documentação" no fim.

## O que há no repositório

| Caminho | Conteúdo |
|---|---|
| `assistente/` | o plugin; montado no contêiner como `local/assistente` |
| `spec-kit/` | especificações, decisões, registros de estado, rastreabilidade e validações |
| `.devcontainer/` | ambiente de desenvolvimento (Moodle, MariaDB, MailHog, Selenium, cron) |

## Onde mexer no código

| Preciso… | Vou a… | Confiro com… |
|---|---|---|
| entender as camadas | `spec-kit/.specify/memory/constitution.md` §12 | `assistente/tests/architecture_test.php` |
| mexer na fila e no processamento assíncrono | `assistente/classes/application/queue/` e `assistente/classes/task/` | `spec-kit/specs/006-contrato-de-eventos-e-fila-l-gica/spec.md`, `spec-kit/specs/007-tasks-processamento-e-retries/spec.md`, `assistente/tests/application/queue/` |
| tratar um evento do Moodle | `assistente/db/events.php` e `assistente/classes/observer/` | `spec-kit/specs/022-integra-o-events-hooks/spec.md`, `assistente/tests/observer/` |
| alterar o esquema | `assistente/db/install.xml`, `assistente/db/upgrade.php`, `assistente/version.php` | `spec-kit/specs/002-esquema-xmldb/spec.md`, `assistente/tests/db/` |
| mexer na persistência | `assistente/classes/infrastructure/persistence/` | `spec-kit/specs/003-api-interna-de-persist-ncia-e-lib-php/spec.md`, `assistente/tests/infrastructure/persistence/` |
| ler dados do Moodle | `assistente/classes/infrastructure/moodle/` | `spec-kit/specs/004-acesso-aos-dados-moodle/spec.md` |
| mexer na integração com LLM | `assistente/classes/application/llm/` e `assistente/classes/infrastructure/llm/` | `spec-kit/specs/009-adapter-da-api-externa-de-llm/spec.md` |
| mexer na interface e nos formulários | `assistente/index.php`, `assistente/configure.php`, `assistente/classes/output/`, `assistente/classes/form/`, `assistente/templates/` | `spec-kit/specs/020-form-api/spec.md`, `spec-kit/specs/021-navega-o-renderiza-o-e-ui-js/spec.md`, `assistente/tests/behat/` |
| mexer em privacidade e histórico | `assistente/classes/privacy/provider.php` e `assistente/classes/application/privacy/` | `spec-kit/specs/017-hist-rico-e-rastreabilidade/spec.md`, `assistente/tests/privacy/` |
| mexer em regra de cálculo pura | `assistente/classes/domain/` | a spec da área, em `spec-kit/specs/` |
| mexer em textos de interface | `assistente/lang/` | `assistente/tests/` (paridade de strings) |

Pontos de entrada: `assistente/index.php` e `assistente/configure.php` (web),
`assistente/settings.php` (administração do site), `assistente/db/tasks.php` (tarefas agendadas),
`assistente/db/events.php` (observers de eventos), `assistente/db/hooks.php` e
`assistente/classes/hook_callbacks.php` (navegação), `assistente/db/access.php` (capacidades).

## Ambiente e testes

Para subir o ambiente, rodar as suítes PHPUnit e Behat e atualizar o plugin num site em
funcionamento, veja `spec-kit/operacao.md`. As suítes rodam dentro do contêiner, contra o Moodle
instalado em `/var/www/html`. Para os scripts de validação em Moodle real, veja
`spec-kit/validation/README.md`.

## Documentação

Este arquivo cobre o repositório e o código. Ele não cobre:

- **como subir o ambiente, rodar as suítes e atualizar o plugin** — `spec-kit/operacao.md`;
- **arquitetura, ciclo de processamento e glossário dos identificadores** — `spec-kit/onboarding.md`;
- **qual documento é oficial, histórico ou gerado** — `spec-kit/README.md`;
- **especificações, dependências e ordem de execução** — `spec-kit/specs/README.md`;
- **estado, pendências e itens CL** — `spec-kit/closure-register.md`;
- **decisões do projeto** — `spec-kit/decision-register-DEC-01-25.md`;
- **rastreabilidade de requisitos** — `spec-kit/requirements-traceability.md`;
- **norma e hierarquia de fontes** — `spec-kit/.specify/memory/constitution.md`.
