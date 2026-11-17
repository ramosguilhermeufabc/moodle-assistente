# Spec Kit — Assistente Inteligente para Apoio às Atividades Docentes no Moodle

> **`VIGENTE`** · Mapa das 23 specs: ordem, dependências e entregas. Índice dos documentos do
> Spec Kit: [`../README.md`](../README.md).

## Ordem e dependências

| ID | Diretório Spec Kit | Especificação de origem | Dependências |
|---|---|---|---|
| spec-001 | [`001-infraestrutura-github-codespaces-e-container-moodle/spec.md`](001-infraestrutura-github-codespaces-e-container-moodle/spec.md) | Infraestrutura GitHub Codespaces e container Moodle | — |
| spec-002 | [`002-esquema-xmldb/spec.md`](002-esquema-xmldb/spec.md) | Esquema XMLDB | 001 |
| spec-003 | [`003-api-interna-de-persist-ncia-e-lib-php/spec.md`](003-api-interna-de-persist-ncia-e-lib-php/spec.md) | API interna de persistência e `lib.php` | 002 |
| spec-004 | [`004-acesso-aos-dados-moodle/spec.md`](004-acesso-aos-dados-moodle/spec.md) | Acesso aos dados Moodle | 003 |
| spec-005 | [`005-acesso-configura-o-e-crit-rios/spec.md`](005-acesso-configura-o-e-crit-rios/spec.md) | Acesso, configuração e critérios | 003, 004 |
| spec-006 | [`006-contrato-de-eventos-e-fila-l-gica/spec.md`](006-contrato-de-eventos-e-fila-l-gica/spec.md) | Contrato de eventos e fila lógica | 003, 004, 005 |
| spec-007 | [`007-tasks-processamento-e-retries/spec.md`](007-tasks-processamento-e-retries/spec.md) | Tasks, processamento e retries | 006 |
| spec-008 | [`008-materiais-vers-es-e-contexto/spec.md`](008-materiais-vers-es-e-contexto/spec.md) | Materiais, versões e contexto | 004, 007 |
| spec-009 | [`009-adapter-da-api-externa-de-llm/spec.md`](009-adapter-da-api-externa-de-llm/spec.md) | Adapter da API externa de LLM | 007, 008 |
| spec-010 | [`010-an-lise-inicial-de-question-rios/spec.md`](010-an-lise-inicial-de-question-rios/spec.md) | Análise inicial de questionários | 004, 008, 009 |
| spec-011 | [`011-respostas-e-m-tricas-de-question-rios/spec.md`](011-respostas-e-m-tricas-de-question-rios/spec.md) | Respostas e métricas de questionários | 007, 010 |
| spec-012 | [`012-notas-e-desempenho/spec.md`](012-notas-e-desempenho/spec.md) | Notas e desempenho | 004, 007 |
| spec-013 | [`013-f-runs-e-dificuldades/spec.md`](013-f-runs-e-dificuldades/spec.md) | Fóruns e dificuldades | 008, 009, 007, 004 *(alterado em 25/10/2026 para seguir o `spec.md`, Q-013-06/CL-11; texto original: "008, 009, 004")* |
| spec-014 | [`014-frequ-ncia-e-participa-o/spec.md`](014-frequ-ncia-e-participa-o/spec.md) | Frequência e participação | 004, 007 |
| spec-015 | [`015-aplica-o-dos-crit-rios/spec.md`](015-aplica-o-dos-crit-rios/spec.md) | Aplicação dos critérios | 005, 011, 012, 013, 014 |
| spec-016 | [`016-s-ntese-pedag-gica/spec.md`](016-s-ntese-pedag-gica/spec.md) | Síntese pedagógica | 004, 008, 009, 010, 011, 012, 013, 014, 015 |
| spec-017 | [`017-hist-rico-e-rastreabilidade/spec.md`](017-hist-rico-e-rastreabilidade/spec.md) | Histórico e rastreabilidade | 002, 006, 007, 008, 010–016 *(alterado em 26/10/2026 para seguir o `spec.md`, Q-017-05/CL-11; texto original: "002, 006–016")* |
| spec-018 | [`018-consultas-funcionais/spec.md`](018-consultas-funcionais/spec.md) | Consultas funcionais | 005, 011–017 |
| spec-019 | [`019-relat-rios-e-versionamento/spec.md`](019-relat-rios-e-versionamento/spec.md) | Relatórios e versionamento | 017, 018 |
| spec-020 | [`020-form-api/spec.md`](020-form-api/spec.md) | Form API | 005, 018, 019 |
| spec-021 | [`021-navega-o-renderiza-o-e-ui-js/spec.md`](021-navega-o-renderiza-o-e-ui-js/spec.md) | Navegação, renderização e UI/JS | 018, 019, 020 |
| spec-022 | [`022-integra-o-events-hooks/spec.md`](022-integra-o-events-hooks/spec.md) | Integração Events/Hooks | 006, 007, 008, 010–021 *(alterado em 26/10/2026 para seguir o `spec.md`, Q-022-03/CL-11; texto original: "006–021")* |
| spec-023 | [`023-testes-e-qualidade-transversal/spec.md`](023-testes-e-qualidade-transversal/spec.md) | Testes e qualidade transversal | 001–022 |

## Ordem de entregas das tasks *(incluída em 26/10/2026)*

As tarefas ficam no local previsto pelo Spec Kit, um `tasks.md` em cada diretório de feature. A execução **não** segue o agrupamento por plano: segue as entregas abaixo, derivadas de DEC-33 D1 (porta LLM com adaptador simulado → consumidores → adaptador real) e das dependências desta tabela. Os IDs `T001…T140` são globais e únicos, em ordem de execução; cada `tasks.md` contém só as tarefas da sua feature, com o rótulo da entrega (`[E0]`…`[E12]`) no lugar do rótulo de história de usuário, porque as specs migradas não têm histórias P1…Pn. Autorização: responsável, 26/10/2026 (`closure-register.md` §8.1). Nenhuma tarefa foi executada.

| Entrega | Conteúdo | Features (`tasks.md`) | Tarefas | Pré-requisito |
|---|---|---|---|---|
| E0 | Pré-condições e ambiente (CL-24; DEC-49) | 001 | T001–T008 | — |
| E1 | Esqueleto, esquema, persistência, readers; runners no ambiente | 002, 003, 004, 001 | T009–T036 | T001 (CL-24); execução dos testes depende de T013 (CL-22) |
| E2 | Acesso e configuração | 005 | T037–T041 | E1 |
| E3 | Eventos e fila; processamento e tentativas | 006, 007 | T042–T051 | E2 |
| E4 | Porta LLM e **adaptador simulado** (DEC-33 D1, passo 1) | 009 | T052–T056 | E3 |
| E5 | Materiais, versões e contexto, consumindo a porta pelo simulado (DEC-33 D1, passo 2) | 008 | T057–T066 | E4; condições de entrada Q-008-05 e Q-008-07 (T057, T058) |
| E6 | Análise inicial, métricas, notas, fórum, frequência | 010, 011, 012, 013, 014 | T067–T090 | 010 e 013: E5; 011: 010; 012 e 014: E3 (podem começar em paralelo a E4/E5) |
| E7 | Critérios e síntese | 015, 016 | T091–T099, T141 | E6 |
| E8 | Histórico e privacidade | 017 | T100–T106 | E7; provider de privacidade só depois de Q-017-07 (T100) |
| E9 | Consultas e relatórios | 018, 019 | T107–T117 | E8 |
| E10 | Formulários, UI e integração de eventos | 020, 021, 022 | T118–T132 | E9 |
| E11 | **Adaptador LLM real** (DEC-33 D1, passo 3) | 009 | T133–T138 | E10 e gate CL-19 (T133) |
| E12 | Fechamento da qualidade transversal (G-023) | 023 | T139–T140 | E0–E11 |

Regras comuns a todas as `tasks.md`:

- **Testes (DEC-48):** toda tarefa que altera código é uma entrega e inclui seus testes automatizados (PHPUnit ou Behat do Moodle), indicados na própria tarefa. LLM sempre pelo adaptador simulado. A matriz completa cenário → tarefa está em [`023-testes-e-qualidade-transversal/tasks.md`](023-testes-e-qualidade-transversal/tasks.md).
- **Validações técnicas (CL):** aparecem como tarefas `VT`, que registram comandos, saídas e divergências no item CL de `closure-register.md`, sem resultado antecipado. Uma VT nunca conta como teste aprovado. As VTs não bloqueiam o planejamento; bloqueiam só o que a tarefa indicar.
- **Condições de entrada:** pendências funcionais destinadas a etapas posteriores (CL-19, Q-008-05, Q-008-07, Q-017-07) aparecem como tarefas `Gate`, sem decisão antecipada.
- **Versão do plugin:** depois da primeira instalação (T013), alterações em `db/*.php` exigem incremento de `version.php`; alteração de esquema exige `db/upgrade.php` com savepoint (DEC-29).
- **Raiz do código:** `assistente/` na raiz do repositório, montado em `/var/www/html/public/local/assistente` (DEC-27); layout de classes do plano 003.

## Observações de fidelidade

Esta conversão é estrutural. O texto normativo das especificações foi preservado; eventuais correções de requisitos, decisões SDD, atualizações de APIs ou reconciliações com outras fontes devem ocorrer em uma etapa separada e rastreável.

Os nomes de diretório foram derivados dos títulos da tabela de dependências em `specs/README.md`. A ordem e as dependências refletem esse mesmo arquivo.

## Referências preservadas *(caminhos atualizados em 08/11/2026)*

- [`../history/research.md`](../history/research.md) — notas de pesquisa do pacote original. *(08/11/2026: movido para `spec-kit/history/`; caminho anterior: `spec-kit/research.md`.)*
- [`../history/install_auditado.xml`](../history/install_auditado.xml) — esquema auditado fornecido no ZIP. *(08/11/2026: movido para `spec-kit/history/`; caminho anterior: `spec-kit/install_auditado.xml`. É cópia congelada e não é fonte: a fonte primária do esquema é `assistente/db/install.xml`, constituição §1.)*
- [`../.specify/memory/constitution.md`](../.specify/memory/constitution.md) — constituição do projeto.
