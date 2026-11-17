# Implementation Plan: spec-021 — Navegação, Renderização e UI/JS

**Branch**: `feature/sdd-ajustado` (feature dir `021-navega-o-renderiza-o-e-ui-js`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/021-navega-o-renderiza-o-e-ui-js/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, conforme DEC-46 e as regras de apresentação de DEC-24 a DEC-45, com as referências autorizadas registradas nos planos 008, 009, 013, 016 e 018. A validação técnica do §5 (CL-41) continua pendente e R-G3 fica como risco; nenhum dos dois bloqueia o planejamento. Nenhum cenário do quickstart foi executado. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-41 Fechado (VT) 03/11/2026; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Apresentar o assistente dentro do contexto do curso usando as camadas pages/controllers → Output API → renderables → templates Mustache (quando aplicável), com AMD/JS só quando houver comportamento cliente necessário [REQ spec-021 §Objetivo, §Contratos]. A UI deve [REQ §Regras]:
- respeitar context/capability;
- separar as funções de consulta;
- mostrar a versão atual e permitir o histórico;
- indicar fontes removidas quando aplicável;
- não ter chat em linguagem natural.

`lib.php` permanece pequeno, com a lógica em classes autocarregáveis [REQ §Conformidade].

*(Incluído em 26/10/2026 por DEC-46 e pelos tratamentos da revisão.)* Entrada "Assistente" na navegação secundária do curso, só para quem tem `local/assistente:view`. Uma página de entrada com uma aba por consulta da spec-018, mais "Relatório" e "Configuração" (só com `manage`). Sem JS próprio obrigatório. As regras de apresentação aprovadas (DEC-24 a DEC-45) valem em todas as páginas; todo texto gerado pela LLM é exibido escapado. A interface usa `lang/en` e `lang/pt_br` no idioma de cada usuário; o conteúdo gerado pela LLM está no idioma registrado na análise (DEC-46).

## Technical Context

**Language/Version**: PHP, Mustache e AMD/JS [REQ §Contratos].

**Primary Dependencies**: Output API, renderables, templates Mustache, AMD [REQ]; consultas (018), relatórios (019), formulários (020). *(26/10/2026: ponto de extensão da navegação do curso e String API do Moodle 5.2, CL-41.)*

**Storage**: N/A (apresentação).

**Testing**: UI quando aplicável [spec-023]. Runner em Q-023-01. *(26/10/2026: também capability por página, estados e indicações, escape de conteúdo gerado e idioma.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, apresentação.

**Performance Goals**: N/A.

**Constraints**: sem chat em linguagem natural; JS só quando necessário [REQ]. Conteúdo gerado pela LLM nunca é renderizado como HTML; `no_data` e `error` nunca são exibidos como zero. *(26/10/2026: incluída a segunda frase.)*

**Scale/Scope**: uma página por função de consulta? (Q-021-02). *(26/10/2026: resolvida — uma página de entrada com abas: 8 consultas, relatório e configuração.)*

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-41): ponto de extensão da navegação do curso, Output API e renderables, escape em Mustache, pacotes de idioma e idioma forçado do curso/site, acessibilidade de abas e tabelas no Moodle 5.2. *(Alterado em 26/10/2026; observação original: "Ponto de extensão de navegação no Moodle 5.2 não verificado (Q-021-01).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-41 (Fechado (VT) em 03/11/2026, com limitações aceitas; commit 750a66d).)* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | BLOQUEADO | PASSA | "Fonte removida" é derivada na leitura, sem campo novo (DEC-42 D3); a UI mostra a versão atual e o histórico sem alterá-los. *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| "Indicar fontes removidas" não tem dado correspondente no esquema (Q-021-04).")* |
| §8 Idempotência | N/A | N/A | — |
| §9 Testabilidade | PASSA | PASSA | [quickstart.md](quickstart.md). *(26/10/2026: cenários Q1–Q14.)* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | — *(26/10/2026: idioma do conteúdo gerado decidido pelo responsável, DEC-46.)* |
| §12 Plataforma e arquitetura | — | PASSA | Camadas pages/controllers → Output API → renderables → Mustache, com AMD só quando necessário [REQ spec-021]. Os caminhos ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* *(26/10/2026: a UI não chama a LLM; só exibe, escapado, o que foi gerado.)* |
| §14 Controle de acesso | — | PASSA | A UI respeita context e capability [REQ spec-021 §Regras]; o critério operacional é o da DEC-30 (spec-005). *(Reavaliado em 25/10/2026: DEC-30; Pós anterior: "BLOQUEADO", "herda Q-005-01".)* Configuração só com `manage` (DEC-45); listas individuais com restrição por grupo (DEC-43). *(26/10/2026.)* |
| §15 Interfaces externas | — | PASSA | Chamadas da própria interface (AMD/JS) não são API pública (constituição §15). Os comportamentos que exigem JS estão em Q-021-03. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* *(26/10/2026: Q-021-03 resolvida — sem JS próprio obrigatório.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-41; não bloqueia o planejamento). Questões Bloqueador: nenhuma. *(Alterado em 26/10/2026; texto anterior: "princípios BLOQUEADO: §5, §7 (§14 passou a PASSA por DEC-30 em 25/10/2026). Questões Bloqueador: nenhum.")* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa a PASSA (validado em CL-41 (Fechado (VT) em 03/11/2026, com limitações aceitas; commit 750a66d)); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/021-navega-o-renderiza-o-e-ui-js/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/ui-pages.md
```

### Source Code (repository root)

```text
assistente/
├── lib.php                 # pequeno (spec-021 §Conformidade; spec-003)
└── [pages, renderables, templates Mustache, AMD]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido para pages, renderables e templates: layout de Q-003-04 em 003/plan.md; `assistente/index.php`, `assistente/configure.php`, `assistente/classes/output/` (`page_controller.php`, `renderer.php`, `query_page.php`, `report_page.php`, `history_page.php`, `configure_page.php`, `table_data.php`) e `assistente/templates/*.mustache`; ressalva: não existe diretório `amd/` em `assistente/` nesta data, e nenhuma tarefa de AMD foi encontrada em 021/tasks.md)
```
*(26/10/2026: também `lang/en` e `lang/pt_br`.)*

## Dependências entre specs

- Upstream: spec-018, 019, 020 [REQ §Dependências].
- Downstream: 022, 023.

## Fases de planejamento & gates

1. Ponto de entrada na navegação e mapa de páginas (Q-021-01, Q-021-02, resolvidas no nível funcional; mecanismo de navegação em CL-41). *(Alterado em 26/10/2026; texto original: "Definir o ponto de entrada de navegação (Q-021-01) e o mapa de páginas (Q-021-02).")*
2. Renderables e templates por consulta, com as regras de apresentação ([contracts/ui-pages.md](contracts/ui-pages.md)). *(Alterado em 26/10/2026; texto original: "Definir renderables e templates por consulta ([contracts/ui-pages.md](contracts/ui-pages.md)).")*
3. **Gate G-021**: cada página verifica context/capability; histórico acessível; sem campo de chat. *(26/10/2026: também estados, indicações e escape do conteúdo gerado; cenários Q1–Q14.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-021-01 | Resolvido | Onde o assistente aparece na navegação do curso e por qual mecanismo (callback × hook; verificar no 5.2). **Resolvido no nível funcional (26/10/2026):** entrada "Assistente" na navegação secundária do curso, visível só com `local/assistente:view`; o mecanismo (callback `extend_navigation_course` ou hook equivalente) fica em CL-41. *(Tipo original: "Aberto".)* | §Conformidade; spec-022; DEC-30 |
| Q-021-02 | Resolvido | Mapa de páginas e funções de consulta. **Resolvido (26/10/2026):** página de entrada com uma aba por consulta da spec-018 (8), mais "Relatório" (atual e histórico) e "Configuração" (só com `manage`); verificação de context/capability no backend em cada página. *(Tipo original: "Aberto".)* | §Regras; plano 018; DEC-45 |
| Q-021-03 | Resolvido | Comportamentos que exigem JS. **Resolvido (26/10/2026):** nenhum obrigatório; filtros são formulários do servidor (plano 020) e a paginação é a nativa; AMD só se surgir necessidade concreta nas tasks. *(Tipo original: "Aberto".)* | §Contratos |
| Q-021-04 | Resolvido | "Indicar fontes removidas" sem campo no esquema (Q-002-07, Q-017-03). **Resolvido por DEC-42 D3 (26/10/2026):** exibir "fonte removida" quando o registro Moodle referenciado não existe mais, derivado na leitura, sem campo novo. *(Tipo original: "Contradição".)* | §Regras; spec-002; DEC-42 |
| Q-021-05 | Resolvido | Strings e idioma (não especificado). **Resolvido (26/10/2026):** interface com `lang/en` (obrigatório) e `lang/pt_br`, no idioma de cada usuário; conteúdo gerado pela LLM no idioma de DEC-46 D1, registrado na análise, sem regeneração nem tradução automática. *(Tipo original: "Aberto".)* | DEC-46 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |
| R-G3 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | Marcador `citeturn…` não resolvível. *(26/10/2026: mantido como risco; `spec.md` não alterado.)* *(04/11/2026, etapa 5a: Aceito como limitação, por decisão do responsável: as citações não são resolvíveis; os marcadores (`cite` e `turn` com caracteres Unicode de uso privado U+E200, U+E202 e U+E201, que `grep citeturn` não encontra) são mantidos nos 8 `spec.md` e no `research.md` porque o texto original é preservado; as regras dessas frases foram confirmadas por decisões e validações, não pelas citações.)* | §Conformidade |

## Revisão pós-propagação (25/10/2026)

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Distinguir `no_data` de zero real (`valid`) e erro técnico (`error`) de resultado válido [REQ spec-021 §Regras] | DEC-24 | Os renderables e templates devem receber o estado junto com o valor | Incorporada |
| Pontuação igual em ordem alfabética crescente do nome [REQ §Regras] | DEC-02 | Aplica-se às listas exibidas | Incorporada |
| "Sem materiais" × cobertura zero | DEC-35 D2 | Indicação na consulta de questionários | Incorporada (26/10/2026) |
| Percentuais truncados a duas casas | DEC-36, DEC-37, DEC-39 | Todas as páginas | Incorporada (26/10/2026) |
| Snapshot atual do fórum | DEC-38 | Página de fóruns | Incorporada (26/10/2026) |
| Acesso ao curso como evidência complementar | DEC-39 D3 | Página de frequência | Incorporada (26/10/2026) |
| Estados 1/0/2 dos critérios ("sem dados") | DEC-40 | Página de abaixo dos critérios | Incorporada (26/10/2026) |
| Síntese e textos gerados exibidos escapados | DEC-41 | Todas as páginas com texto da LLM | Incorporada (26/10/2026) |
| "Fonte removida" derivada na leitura | DEC-42 D3 | Q-021-04 resolvida; §7 passa | Incorporada (26/10/2026) |
| Restrição por grupo nas listas individuais | DEC-43 | Páginas de notas, frequência e abaixo dos critérios; partes individuais do relatório | Incorporada (26/10/2026) |
| Relatório renderizado do manifesto; versões antigas | DEC-44 | Aba "Relatório" | Incorporada (26/10/2026) |
| "Assistente não habilitado"; critérios somente leitura; aba de configuração só com `manage` | DEC-45 | Q-021-02 | Incorporada (26/10/2026) |
| Idioma do conteúdo gerado; pacotes de idioma | DEC-46 | Q-021-05 resolvida | Incorporada (26/10/2026); validação em CL-41 |

Continua aberta a contradição Q-021-04 (fontes removidas sem campo, `closure-register.md` CL-15). Nada foi executado nem testado. *(26/10/2026: deixou de valer — Q-021-04 resolvida por DEC-42 D3; Q-021-01 a 05 resolvidas.)*

O `spec.md` desta feature não foi alterado: DEC-46 afeta o conteúdo gerado e este plano. Propagações pendentes: 022 (eventos de navegação, se houver); 023 (testes de UI, escape e idioma). No plano 018 aprovado, só foi acrescentada a referência a DEC-45 D3 ("assistente não habilitado"), com autorização. Nos planos aprovados 008, 009, 013 e 016, só foram acrescentadas referências a DEC-46 (idioma registrado no contexto; idioma na instruction da porta), com autorização. *(Incluído em 26/10/2026; texto anterior da lista de pendências: "planos aprovados 008, 013 e 016 (registrar o idioma no contexto de cada análise que gera texto pela LLM) e 009 (idioma informado na instruction) — exigem autorização para incluir referências; 022 …")* *(04/11/2026, revisão dos planos: propagação da DEC-46 aos planos-alvo 022 não feita; superada pela implementação e validação: 022: Q-022-02 resolvida (só o hook de navegação do curso) e CL-41 Fechado (VT) em 03/11/2026; os planos-alvo não foram reabertos. Planos-alvo com citação: 023.)*
**Referência a DEC-47** *(incluída em 26/10/2026, na revisão do plano 022, com autorização do responsável; alteração somente referencial, sem mudança das demais regras de exibição deste plano aprovado)*: na camada de apresentação, as listas por estudante mostram só os estudantes com matrícula ativa e papel contemplado pelo livro de notas, filtrados no momento da consulta; o recém-matriculado sem resultados aparece como "sem dados"; o removido do curso deixa de aparecer, com o histórico preservado (DEC-42). Ver `decision-register-DEC-01-25.md`, DEC-47.

## Complexity Tracking

Nenhuma violação justificável.
