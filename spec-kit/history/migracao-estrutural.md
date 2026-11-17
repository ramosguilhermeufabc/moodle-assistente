# Migração estrutural das 23 especificações — registro histórico

> **`HISTÓRICO`** · Este documento era o corpo de `spec-kit/README.md` e foi movido para
> `spec-kit/history/migracao-estrutural.md` em 08/11/2026, **verbatim**: nenhuma palavra do texto
> abaixo foi reescrita, acrescentada ou removida. Só os destinos de link foram corrigidos, porque
> o arquivo mudou de pasta: os 23 links da tabela de dependências passaram de `NNN-slug/spec.md`
> (destino que já estava quebrado na localização anterior) para `../specs/NNN-slug/spec.md`;
> `specs/README.md` passou a `../specs/README.md`; e `../README.md` passou a `../../README.md`.
>
> `spec-kit/README.md` passou a ser o índice dos documentos do Spec Kit. O estado do projeto é de
> `../closure-register.md`; a ordem e as dependências vigentes das specs são de
> [`../specs/README.md`](../specs/README.md). Índice do histórico: [`README.md`](README.md).

---

# Spec Kit — Assistente Inteligente para Apoio às Atividades Docentes no Moodle

> **Nota de 08/11/2026 — escopo e fontes vigentes.** Este arquivo descreve a migração estrutural
> das 23 specs e é mantido como registro dessa etapa. Duas afirmações do texto abaixo foram
> superadas pelos fatos e ficam preservadas como histórico, conforme a constituição §11:
>
> 1. "Não há `plan.md` nem `tasks.md` nesta entrega" — os 23 `plan.md` e os 23 `tasks.md` existem
>    desde 26/10/2026 (autorização registrada em `closure-register.md` §8.1).
> 2. A tabela de dependências reproduzida aqui **não é a vigente**. A vigente é a de
>    [`specs/README.md`](../specs/README.md), que recebeu as correções de 25–26/10/2026 (CL-11:
>    specs 013, 017 e 022) não replicadas neste arquivo.
>
> Para navegar o código, veja o [`README.md`](../../README.md) da raiz do repositório. Para o estado
> do projeto, `closure-register.md`.

Este diretório contém a migração estrutural das 23 especificações modulares fornecidas no ZIP original.

## Escopo desta migração

- Cada arquivo original `specs/spec-NNN.md` foi colocado em um diretório de especificação no formato `specs/NNN-slug/spec.md`.
- O conteúdo dos 23 arquivos `spec.md` foi preservado sem reescrita funcional.
- A constituição foi mantida em `.specify/memory/constitution.md`.
- `research.md` e `install_auditado.xml` foram mantidos como referências de origem.
- Nenhum plano de implementação (`plan.md`) ou lista de tarefas (`tasks.md`) foi inventado ou gerado nesta migração.
- Nenhum arquivo do plugin Moodle foi alterado; esta pasta é um pacote de especificações separado.

## Ordem e dependências

| ID | Diretório Spec Kit | Especificação de origem | Dependências |
|---|---|---|---|
| spec-001 | [`001-infraestrutura-github-codespaces-e-container-moodle/spec.md`](../specs/001-infraestrutura-github-codespaces-e-container-moodle/spec.md) | Infraestrutura GitHub Codespaces e container Moodle | — |
| spec-002 | [`002-esquema-xmldb/spec.md`](../specs/002-esquema-xmldb/spec.md) | Esquema XMLDB | 001 |
| spec-003 | [`003-api-interna-de-persist-ncia-e-lib-php/spec.md`](../specs/003-api-interna-de-persist-ncia-e-lib-php/spec.md) | API interna de persistência e `lib.php` | 002 |
| spec-004 | [`004-acesso-aos-dados-moodle/spec.md`](../specs/004-acesso-aos-dados-moodle/spec.md) | Acesso aos dados Moodle | 003 |
| spec-005 | [`005-acesso-configura-o-e-crit-rios/spec.md`](../specs/005-acesso-configura-o-e-crit-rios/spec.md) | Acesso, configuração e critérios | 003, 004 |
| spec-006 | [`006-contrato-de-eventos-e-fila-l-gica/spec.md`](../specs/006-contrato-de-eventos-e-fila-l-gica/spec.md) | Contrato de eventos e fila lógica | 003, 004, 005 |
| spec-007 | [`007-tasks-processamento-e-retries/spec.md`](../specs/007-tasks-processamento-e-retries/spec.md) | Tasks, processamento e retries | 006 |
| spec-008 | [`008-materiais-vers-es-e-contexto/spec.md`](../specs/008-materiais-vers-es-e-contexto/spec.md) | Materiais, versões e contexto | 004, 007 |
| spec-009 | [`009-adapter-da-api-externa-de-llm/spec.md`](../specs/009-adapter-da-api-externa-de-llm/spec.md) | Adapter da API externa de LLM | 007, 008 |
| spec-010 | [`010-an-lise-inicial-de-question-rios/spec.md`](../specs/010-an-lise-inicial-de-question-rios/spec.md) | Análise inicial de questionários | 008, 009 |
| spec-011 | [`011-respostas-e-m-tricas-de-question-rios/spec.md`](../specs/011-respostas-e-m-tricas-de-question-rios/spec.md) | Respostas e métricas de questionários | 007, 010 |
| spec-012 | [`012-notas-e-desempenho/spec.md`](../specs/012-notas-e-desempenho/spec.md) | Notas e desempenho | 004, 007 |
| spec-013 | [`013-f-runs-e-dificuldades/spec.md`](../specs/013-f-runs-e-dificuldades/spec.md) | Fóruns e dificuldades | 008, 009 |
| spec-014 | [`014-frequ-ncia-e-participa-o/spec.md`](../specs/014-frequ-ncia-e-participa-o/spec.md) | Frequência e participação | 004, 007 |
| spec-015 | [`015-aplica-o-dos-crit-rios/spec.md`](../specs/015-aplica-o-dos-crit-rios/spec.md) | Aplicação dos critérios | 005, 011, 012, 013, 014 |
| spec-016 | [`016-s-ntese-pedag-gica/spec.md`](../specs/016-s-ntese-pedag-gica/spec.md) | Síntese pedagógica | 009, 011, 012, 013, 014, 015 |
| spec-017 | [`017-hist-rico-e-rastreabilidade/spec.md`](../specs/017-hist-rico-e-rastreabilidade/spec.md) | Histórico e rastreabilidade | 002, 006–016 |
| spec-018 | [`018-consultas-funcionais/spec.md`](../specs/018-consultas-funcionais/spec.md) | Consultas funcionais | 005, 011–017 |
| spec-019 | [`019-relat-rios-e-versionamento/spec.md`](../specs/019-relat-rios-e-versionamento/spec.md) | Relatórios e versionamento | 017, 018 |
| spec-020 | [`020-form-api/spec.md`](../specs/020-form-api/spec.md) | Form API | 005, 018, 019 |
| spec-021 | [`021-navega-o-renderiza-o-e-ui-js/spec.md`](../specs/021-navega-o-renderiza-o-e-ui-js/spec.md) | Navegação, renderização e UI/JS | 018, 019, 020 |
| spec-022 | [`022-integra-o-events-hooks/spec.md`](../specs/022-integra-o-events-hooks/spec.md) | Integração Events/Hooks | 006–021 |
| spec-023 | [`023-testes-e-qualidade-transversal/spec.md`](../specs/023-testes-e-qualidade-transversal/spec.md) | Testes e qualidade transversal | 001–022 |

## Observações de fidelidade

Esta conversão é estrutural. O texto normativo das especificações foi preservado; eventuais correções de requisitos, decisões SDD, atualizações de APIs ou reconciliações com outras fontes devem ocorrer em uma etapa separada e rastreável.

Os nomes de diretório foram derivados dos títulos da tabela de dependências em `specs/README.md`. A ordem e as dependências refletem esse mesmo arquivo.

## Referências preservadas

- `research.md` — notas de pesquisa do pacote original.
- `install_auditado.xml` — esquema auditado fornecido no ZIP.
- `.specify/memory/constitution.md` — constituição do projeto.


## Requisitos atômicos e fluxo Spec Kit com Claude

A matriz `requirements-traceability.md` registra os 66 requisitos candidatos AT-001…AT-066 a partir da reconciliação da Etapa 1, com origem, estado e nota de auditoria. `decision-register-DEC-01-25.md` contém o registro aprovado de decisões; `decision-propagation.md` descreve como propagá-las com validação explícita. `source-audit-66.md` preserva a fonte da matriz usada para esta consolidação.

**Importante:** os 23 arquivos `spec.md` foram preservados estruturalmente e ainda não receberam propagação integral das decisões. Não há `plan.md` nem `tasks.md` nesta entrega; eles devem ser gerados pelo fluxo oficial do Spec Kit usando a integração Claude, revisados antes de implementar. Cobertura de requisito não é sinônimo de implementação/teste concluído.

Fluxo documentado do Spec Kit: `/speckit.specify` → `/speckit.clarify` (somente pendências) → `/speckit.plan` → `/speckit.checklist` → `/speckit.tasks` → `/speckit.analyze` → revisão humana → `/speckit.implement`. Referência oficial: https://github.github.io/spec-kit/reference/agentic-sdd.html.

## Fluxo separado em quatro prompts (Claude)

A execução foi separada intencionalmente em quatro prompts independentes. Cada prompt tem seu próprio objetivo, contexto permitido, limites e critérios de conclusão; nenhum deve iniciar automaticamente a etapa seguinte.

1. **Create Spec Kit Environment** — [`prompts/PROMPT-1-CREATE-SPEC-KIT-ENVIRONMENT.md`](prompts/PROMPT-1-CREATE-SPEC-KIT-ENVIRONMENT.md)
2. **Configure Spec Kit Environment** — [`prompts/PROMPT-2-CONFIGURE-SPEC-KIT-ENVIRONMENT.md`](prompts/PROMPT-2-CONFIGURE-SPEC-KIT-ENVIRONMENT.md)
3. **Plan (Specs como contexto único)** — [`prompts/PROMPT-3-PLAN-USING-SPECS-ONLY.md`](prompts/PROMPT-3-PLAN-USING-SPECS-ONLY.md)
4. **Tasks (Specs + artefatos aprovados do Plan)** — [`prompts/PROMPT-4-TASKS-USING-SPECS-AND-PLAN.md`](prompts/PROMPT-4-TASKS-USING-SPECS-AND-PLAN.md)

**Ordem obrigatória:** Create Spec Kit Environment → Configure Spec Kit Environment → Plan (Specs) → Tasks (Specs + Plan). Revise o resultado de cada fase e autorize explicitamente a próxima. O Prompt 3 usa exclusivamente as 23 specs como contexto de requisitos; o Prompt 4 usa exclusivamente as specs e os artefatos gerados/aprovados pelo Plan. Nenhum desses prompts autoriza implementação.

O arquivo [`PROMPT-CLAUDE-SPECKIT.md`](PROMPT-CLAUDE-SPECKIT.md) é uma orientação histórica consolidada e não substitui os quatro prompts separados acima. Para executar o novo fluxo, use os arquivos numerados em `prompts/`.
