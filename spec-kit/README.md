# Spec Kit — índice dos documentos

> **`VIGENTE`** · Este arquivo é o índice do `spec-kit/`. Ele diz, para cada documento, a que
> categoria pertence, em que ciclo de vida está e quem pode alterá-lo. Ele não contém requisito,
> estado nem decisão: cada um desses tem dono próprio, indicado abaixo.

A norma do projeto é [`.specify/memory/constitution.md`](.specify/memory/constitution.md), e a
hierarquia entre as fontes está na §1 dela. As instruções permanentes do agente estão em
[`../CLAUDE.md`](../CLAUDE.md). O mapa do código é o [`../README.md`](../README.md) da raiz.

## Rótulos de ciclo de vida

| Rótulo | Significado | Quem mantém |
|---|---|---|
| `VIGENTE` | descreve o projeto como ele é hoje; divergir do código é defeito a corrigir | o dono indicado na categoria |
| `REGISTRO` | acumulativo; o passado é preservado e o estado é a última linha | o agente, sob autorização expressa |
| `GERADO` | derivado de um script; alterar é rodar o script, nunca editar à mão | o script |
| `HISTÓRICO` | retrato de uma data; não descreve o presente e não deve ser atualizado | ninguém; só nota datada de correção |

Transições permitidas: `VIGENTE → HISTÓRICO` e `REGISTRO → HISTÓRICO`, preservando o texto
substituído com a data e a decisão (constituição §11). A transição inversa não existe: para um
documento histórico voltar a valer é preciso decisão nova, registrada como DEC.

## Documentação viva

| Documento | Rótulo | Responde a | Quem pode alterar |
|---|---|---|---|
| [`.specify/memory/constitution.md`](.specify/memory/constitution.md) | `VIGENTE` | o que é obrigatório; hierarquia das fontes | responsável, por emenda com relatório de impacto (§11) |
| [`../CLAUDE.md`](../CLAUDE.md) | `VIGENTE` | como o agente deve trabalhar neste repositório | responsável |
| [`../README.md`](../README.md) | `VIGENTE` | onde está o código de cada área | agente, na mesma alteração que muda a estrutura |
| este `README.md` | `VIGENTE` | qual documento é oficial, histórico, gerado ou acumulativo | agente, na mesma alteração que cria ou move documento |
| [`specs/README.md`](specs/README.md) | `VIGENTE` | ordem, dependências e entregas das 23 specs | agente, com autorização |
| `specs/NNN-*/spec.md` (23) | `VIGENTE` | o que o sistema deve fazer | responsável; o agente só propaga, com data e DEC |
| `specs/NNN-*/{plan,tasks,traceability,data-model,quickstart}.md`, `contracts/` | `REGISTRO` | como foi desenhado, decomposto e validado | agente, com autorização |
| [`closure-register.md`](closure-register.md) | `REGISTRO` | estado do projeto, pendências, itens CL e evidências de fechamento | agente sob autorização; o estado é decidido pelo responsável |
| [`requirements-traceability.md`](requirements-traceability.md) | `REGISTRO` | cobertura dos 66 requisitos AT-001…AT-066 | agente, com autorização |
| `specs/023-*/checklists/test-evidence.md` | `GERADO` | qual cenário tem teste aprovado | `validation/t139/generate_evidence.py` |
| `specs/023-*/checklists/usability-*.md` | `REGISTRO` | verificação de usabilidade pelo pesquisador | responsável |

Em divergência entre a seção "Estado atual" do `closure-register.md` e o corpo do registro, **o
corpo prevalece**, e a divergência é corrigida na seção.

## Registros de decisão (ADRs)

| Documento | Rótulo | Responde a | Quem pode alterar |
|---|---|---|---|
| [`decision-register-DEC-01-25.md`](decision-register-DEC-01-25.md) | `REGISTRO` | **por que** cada regra do projeto é como é — DEC-01 a DEC-72 | agente sob autorização; a decisão é do responsável |

Este é o registro de decisões arquiteturais do projeto, e o único. Cada DEC traz contexto, regra,
artefatos afetados e critério de validação. Os identificadores `DEC-nn` são citados dentro do
código PHP e **nunca** são renumerados nem renomeados. O sufixo `DEC-01-25` do nome do arquivo é
de origem histórica e não delimita o conteúdo, como o próprio cabeçalho do registro explica.

Dentro dele, as seções 3, 5, 6 e 7 estão marcadas como históricas desde 08/11/2026: descrevem a
fase de consolidação de 21/10/2026 e não valem como orientação atual.

## Documentação operacional

| Documento | Rótulo | Responde a |
|---|---|---|
| [`operacao.md`](operacao.md) | `VIGENTE` | como subir o ambiente, rodar PHPUnit e Behat, atualizar o plugin num site em funcionamento e rodar os scripts de validação |
| [`validation/README.md`](validation/README.md) | `VIGENTE` | como rodar os scripts de validação em Moodle real |
| [`validation/t139/run.md`](validation/t139/run.md) | `REGISTRO` | comandos e resultado da execução completa das suítes |
| [`validation/docs-check/verificar.py`](validation/docs-check/verificar.py) | `VIGENTE` | conferência da documentação: cinco checagens de leitura, chamadas no `operacao.md` §8 |
| [`closure-register.md`](closure-register.md) §4.5 | `REGISTRO` | o que fazer ao instalar ou atualizar o plugin num site em funcionamento |
| `specs/001-*/quickstart.md` | `REGISTRO` | cenários de validação do ambiente (Q1–Q12), **não** é guia de execução |

## Onboarding

| Documento | Rótulo | Responde a | Quem pode alterar |
|---|---|---|---|
| [`onboarding.md`](onboarding.md) | `VIGENTE` | camadas e as regras que os testes impõem, ciclo de processamento, pontos de entrada, as 21 tabelas, convenções de teste e o **glossário dos identificadores** (`DEC`, `CL`, `T`, `AT`, `Q`, `C`, `E`, `G`, `R`, `F`, `D`, `CR`, `I`, `AUD`, e as classes PROP, DH, VT e VE) | agente, derivando das outras fontes |

É o primeiro documento de quem assume a manutenção. Ele deriva das outras fontes e não cria
requisito nem estado: onde um fato tiver dono, ele aponta para o dono.

## Conteúdo histórico

Está todo em [`history/`](history/README.md), separado dos documentos vigentes. Nada ali descreve
o projeto de hoje. O índice da pasta, com o caminho anterior de cada artefato, é
[`history/README.md`](history/README.md).

## O que ainda não existe

Uma lacuna da análise de 08/11/2026 ainda não preenchida, cuja execução depende de autorização do
responsável: os **rótulos de ciclo de vida dos documentos dentro das subpastas de
`validation/`**, que vêm junto com a revisão daquele índice.

*(08/11/2026, Fase 2: as duas lacunas anteriores desta seção foram preenchidas. Texto anterior,
preservado: "documentação operacional consolidada — subir o ambiente, instalar o plugin, rodar
PHPUnit e Behat, purgar caches ao atualizar e rodar os scripts de validação, hoje dispersos entre
`.devcontainer/`, `specs/001-*/quickstart.md`, `validation/t139/run.md` e `closure-register.md`
§4.5" e "guia de onboarding e glossário dos identificadores — `DEC-nn`, `CL-nn`, `Tnnn`,
`AT-nnn`, `Q-nnn-nn`, `spec-NNN`, as entregas `E0`…`E12`, o gate `G-023` e as classes PROP, DH, VT
e VE, hoje definidos de forma dispersa ou não definidos". Hoje são `operacao.md` e
`onboarding.md`.)*

## Regra de manutenção deste índice

Documento novo em `spec-kit/` só existe depois de entrar nesta tabela, com categoria, rótulo e
dono. Documento que muda de lugar é atualizado aqui na mesma alteração. Este índice aponta para os
donos; não reproduz o conteúdo deles — em particular, a tabela de dependências das specs é de
[`specs/README.md`](specs/README.md) e não é copiada para cá.

---

*Registro de alteração — 08/11/2026, Fase 2.* `operacao.md` e `onboarding.md` criados e
indexados; `validation/README.md` passou a descrever as 17 pastas; o `README.md` da raiz e o
`CLAUDE.md` passaram a apontar para os dois documentos novos. Registro formal em
[`closure-register.md`](closure-register.md) §10.

*Registro de alteração — 08/11/2026, Fase 1.* Este arquivo era o registro da migração estrutural das 23
especificações, escrito em 21/10/2026. Esse texto foi movido, verbatim, para
[`history/migracao-estrutural.md`](history/migracao-estrutural.md), e este arquivo passou a ser o
índice dos documentos do `spec-kit/`. Na mesma alteração, `install_auditado.xml`,
`source-audit-66.md`, `decision-propagation.md`, `research.md`, `PROMPT-CLAUDE-SPECKIT.md`,
`migration-manifest.json` e `prompts/` foram movidos para `history/`. Nenhum texto normativo foi
alterado. Autorização: responsável, 08/11/2026.
