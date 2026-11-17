# Implementation Plan: spec-003 — API Interna de Persistência e `lib.php`

**Branch**: `feature/sdd-ajustado` (feature dir `003-api-interna-de-persist-ncia-e-lib-php`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-api-interna-de-persist-ncia-e-lib-php/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, inclusive a organização de classes de Q-003-04 (`domain/`, `application/`, `infrastructure/` no namespace `local_assistente`) como decisão de planejamento; nomes concretos nas tasks. CL-07, CL-41, CL-37 e CL-02 continuam validações técnicas, sem resultado presumido. A aprovação isolada não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Criar a camada de infraestrutura que encapsula `$DB`/DML: repositórios para agregados próprios, gateway/reader para dados derivados, e `lib.php` restrito aos callbacks necessários [REQ spec-003 §Objetivo, §Contratos]. O domínio não conhece SQL, não usa prefixo físico nas chamadas DML, usa transações em operações multi-tabela quando necessário e respeita as chaves e índices do XMLDB [REQ §Contratos, §Regras].

*(Incluído em 26/10/2026, na revisão do plano 003, sem decisão humana nova.)* Os agregados, as unidades transacionais e os callbacks decorrem dos planos aprovados 001, 002 e 005–023. O layout das classes segue as convenções de autoload do Moodle (`local_assistente\…` em `classes/`), com camadas de domínio, aplicação e infraestrutura e os diretórios que o Moodle exige em local fixo (tarefas, privacidade, saída, formulários, `db/`, `lang/`, `tests/`).

## Technical Context

**Language/Version**: PHP conforme spec-001 (≥ 8.3). Lógica em "classes autocarregáveis" [REQ §Conformidade].

**Primary Dependencies**: Moodle DML (`$DB`) apenas na infraestrutura [REQ §Regras]; esquema de spec-002. *(26/10/2026: também a Lock API nas unidades que a exigem, DEC-15, DEC-20, DEC-23, DEC-41.)*

**Storage**: tabelas de spec-002.

**Testing**: testes unitários e de integração/persistência (spec-023). Runner em Q-023-01. *(26/10/2026: PHPUnit do Moodle, DEC-48.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, camada de persistência.

**Performance Goals**: N/A, não especificado.

**Constraints**: domínio sem SQL; nenhuma lógica de negócio em `lib.php`; callbacks em `lib.php` só "quando não houver mecanismo moderno adequado" [REQ §Contratos, §Regras]. Nenhuma transação aberta durante chamada à LLM [spec-007]. *(26/10/2026: incluída a última frase.)*

**Scale/Scope**: repositórios para os agregados das 21 tabelas. Delimitação dos agregados em Q-003-01. *(26/10/2026: resolvida — ver "Agregados".)*

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | Domínio × infraestrutura explícitos. |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Callbacks e "mecanismo moderno" a verificar no Moodle 5.2 (Q-003-03). *(26/10/2026: validação técnica que não bloqueia o planejamento — navegação em CL-41, exclusão de curso em CL-37, registro de eventos e tarefas em CL-02, transações e Lock API em CL-07.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-41 (Fechado em 03/11/2026), CL-37 (Fechado em 28/10/2026), CL-02 (FECHADA em 02/11/2026) e CL-07 (Fechado em 02/11/2026), todos com ressalvas ou limitações registradas)* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | BLOQUEADO | PASSA | As unidades transacionais estão enumeradas a partir das decisões aprovadas (ver "Unidades transacionais"); nenhuma mantém transação aberta durante chamada à LLM. *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| Critério de "transações quando necessário" indefinido (Q-003-02).")* |
| §8 Idempotência | N/A | N/A | spec-007. |
| §9 Testabilidade | PASSA | PASSA | [quickstart.md](quickstart.md). *(26/10/2026: cenários Q1–Q10.)* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | — |
| §12 Plataforma e arquitetura | — | PASSA | `lib.php` mínimo e lógica em classes autocarregáveis [REQ spec-003]. Os callbacks necessários e o namespace ficam em Q-003-03 e Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* *(26/10/2026: Q-003-03 e Q-003-04 resolvidas — ver "Layout das classes" e "Callbacks".)* |
| §13 Uso de LLM | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5, §7. Questões Bloqueador: nenhum. Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(26/10/2026: só §5, validação técnica que não bloqueia o planejamento; §7 passa.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa (CL-41, CL-37, CL-02 e CL-07 Fechados); nenhum princípio BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/003-api-interna-de-persist-ncia-e-lib-php/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/persistence-ports.md
```

### Source Code (repository root)

```text
assistente/
├── lib.php            # somente callbacks necessários (spec-003 §Objetivo)
└── [classes autocarregáveis]   # repositories/gateways; namespace e caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: layout aprovado em 26/10/2026 (`domain/`, `application/`, `infrastructure/` no namespace `local_assistente`); implementado em `assistente/classes/infrastructure/persistence/`)
```

**Structure Decision**: só `lib.php` é nomeado pela spec. O layout das classes está em Q-003-04. *(26/10/2026: Q-003-04 resolvida na seção seguinte.)*

### Layout das classes *(incluído em 26/10/2026; Q-003-04)*

Namespace raiz `local_assistente`, com autoload do Moodle a partir de `classes/`. Os itens marcados com (M) têm local exigido pelo Moodle; os demais seguem a separação em camadas da constituição §12 e são `[ESCOLHA]` de planejamento.

```text
assistente/
├── version.php                       (M) versão do plugin (Q-002-02)
├── lib.php                           (M) só callbacks sem mecanismo moderno (ver "Callbacks")
├── settings.php                      (M) se houver página de administração; nenhuma prevista além da página por curso (plano 020)
├── db/
│   ├── install.xml                   (M) esquema (spec-002)
│   ├── upgrade.php                   (M) depois da primeira instalação (DEC-29)
│   ├── access.php                    (M) capabilities view e manage (DEC-30, DEC-45)
│   ├── events.php                    (M) observers do catálogo (plano 022)
│   ├── tasks.php                     (M) Scheduled Tasks (DEC-32, 36, 39, 44)
│   └── hooks.php                     (M) se o mecanismo de navegação for hook (CL-41)
├── classes/
│   ├── domain/                       regras puras, sem `$DB` nem API do Moodle
│   ├── application/                  serviços de aplicação (casos de uso, fila, sincronização)
│   ├── infrastructure/
│   │   ├── persistence/              repositórios dos agregados próprios (DML)
│   │   ├── moodle/                   readers de dados do Moodle (spec-004)
│   │   └── llm/                      porta e adaptadores LLM, simulado e real (spec-009)
│   ├── observer/                     observers finos (plano 022)
│   ├── task/                         (M) Scheduled Tasks
│   ├── privacy/provider.php          (M) Privacy API (DEC-42)
│   ├── form/                         moodleform (plano 020)
│   └── output/                       (M) renderables e renderer (plano 021)
├── templates/                        (M) Mustache
├── amd/src/                          (M) só se houver necessidade de JS (Q-021-03)
├── lang/en/local_assistente.php      (M) obrigatório
├── lang/pt_br/local_assistente.php   pacote pt_br (DEC-46)
├── index.php e páginas do curso      entradas das abas (plano 021)
└── tests/                            (M) PHPUnit; tests/behat/ (M); tests/generator/ (M) (DEC-48)
```

Os nomes de classes e arquivos dentro de cada diretório são definidos nas tasks.

## Dependências entre specs

- Upstream: spec-002 [REQ §Dependências].
- Downstream: 004–019 usam esta camada. *(26/10/2026: também 020–022, pelo layout.)*

## Agregados *(incluído em 26/10/2026; Q-003-01)*

| Agregado | Tabelas | Plano de origem |
|---|---|---|
| Configuração do curso | `config`, `criteria` | 005, 020 |
| Evento e processamento | `event`, `processing`, `processing_attempt`, `processing_context` | 006, 007 |
| Material | `material`, `material_version` | 008 |
| Análise inicial de questionário | `analysis` (`quiz_initial`), `questionnaire_analysis`, `questionnaire_question` | 010 |
| Resultados de questionário | `analysis` (`quiz_results`), `question_result`, `questionnaire_metric` | 011 |
| Desempenho | `analysis` (`grade_performance`), `performance` | 012 |
| Análise de fórum | `analysis` (`forum_analysis`), `forum_difficulty` | 013 |
| Frequência | `analysis` (`frequency`), `frequency`, `frequency_activity` | 014 |
| Critérios aplicados | `analysis` (`criteria_check`), `criteria_result` | 015 |
| Síntese | `analysis` (`section_synthesis`), `pedagogical_synthesis` | 016 |
| Relatório | `report`, `report_version` | 019 |

Cada `analysis` pertence a um único agregado, conforme o `analysis_type`, e é gravada junto com as linhas dependentes.

## Unidades transacionais *(incluído em 26/10/2026; Q-003-02)*

Regra geral: transação DML curta, aberta só para gravar; nunca durante chamada à LLM (spec-007). Lock API onde indicado.

| Unidade | Lock | Fonte |
|---|---|---|
| Transição de estado do evento/processamento e registro da tentativa | por evento | DEC-16, DEC-22, DEC-32 |
| Nova versão de material e troca de `current_version`: **três unidades independentes**, cada uma com a transação curta própria e o mesmo lock: (a) criar a versão `pending`; (b) gravar o resumo e marcar `completed`; (c) trocar `current_version` para versão `completed` de número maior que o da corrente *(alterado em 01/11/2026 por DEC-61 e DEC-62; texto original: "Nova versão de material e troca de `current_version`", lido como uma só unidade; a LLM fica entre (a) e (b), fora de qualquer transação)* | por material (`material_<id>`) | DEC-15, DEC-20, DEC-29, DEC-50, DEC-61, DEC-62 |
| Gravação de uma `analysis` e de todas as suas linhas dependentes (tudo ou nada) | — | planos 010–015 |
| Snapshot completo do fórum | — | DEC-38 D1 |
| Nova versão de síntese (`max + 1`) | por curso e seção | DEC-41 |
| Nova versão de relatório e troca de `current_version`; reabertura | por relatório | DEC-23, DEC-44 |
| Gravação da configuração e dos critérios | — | DEC-45 |
| Exclusão de dados por estudante (Privacy API) | — | DEC-42 D1 |
| Exclusão dos dados do curso | — | DEC-42 D2 |

## Callbacks *(incluído em 26/10/2026; Q-003-03)*

| Necessidade | Mecanismo preferido | Callback em `lib.php` só se | Validação |
|---|---|---|---|
| Entrada na navegação do curso | hook, se existir no Moodle 5.2 | não houver hook adequado (`extend_navigation_course`) | CL-41 |
| Exclusão de curso | evento ou hook | não houver evento nem hook adequado | CL-37 |
| Observers | `db/events.php` | — (não usa `lib.php`) | CL-02 |
| Scheduled Tasks | `db/tasks.php` | — | CL-02 |
| Privacidade | `classes/privacy/provider.php` | — | CL-37 |

Nenhuma outra função em `lib.php`.

## Fases de planejamento & gates

1. Definir os agregados e os readers derivados (bloqueado por Q-003-01/05). *(26/10/2026: definidos — ver "Agregados" e Q-003-05.)*
2. Definir as interfaces de repositório e gateway ([contracts/persistence-ports.md](contracts/persistence-ports.md)). *(26/10/2026: contrato atualizado.)*
3. Definir a política transacional para operações multi-tabela (Q-003-02). *(26/10/2026: definida — ver "Unidades transacionais".)*
4. **Gate G-003**: nenhuma ocorrência de `$DB` fora da infraestrutura; nenhum prefixo físico em DML; `lib.php` sem lógica de negócio [ESCOLHA: gate verificável por inspeção/teste]. *(26/10/2026: também nenhuma transação aberta durante chamada à LLM; cenários Q1–Q10.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-003-01 | Resolvido | Quais tabelas formam cada agregado próprio. **Resolvido (26/10/2026):** onze agregados, derivados dos planos aprovados (ver "Agregados"). *(Tipo original: "Aberto".)* | §Contratos; planos 005–019 |
| Q-003-02 | Resolvido | Critério de "transações quando necessário". Candidatos citados em outras specs: troca de versão corrente (008/019) e preservação de histórico (017). **Resolvido (26/10/2026):** unidades transacionais enumeradas a partir das decisões aprovadas, com transação curta só para gravar e sem LLM dentro dela (ver "Unidades transacionais"). *(Tipo original: "Aberto".)* | §Regras; DEC-15, 20, 22, 23, 32, 38, 41, 42, 44, 45 |
| Q-003-03 | Resolvido | Quais callbacks `lib.php` serão necessários; o que é "mecanismo moderno adequado" (verificar no Moodle 5.2). **Resolvido no nível funcional (26/10/2026):** só navegação do curso e, se necessário, exclusão de curso, e apenas se não houver hook ou evento adequado; observers, tarefas e privacidade usam os mecanismos próprios (ver "Callbacks"). Verificação em CL-41 e CL-37. *(Tipo original: "Aberto".)* | §Contratos, §Conformidade |
| Q-003-04 | Resolvido | Namespace e caminhos das classes autocarregáveis. **Resolvido (26/10/2026):** namespace `local_assistente`, autoload a partir de `classes/`, camadas de domínio, aplicação e infraestrutura e diretórios exigidos pelo Moodle (ver "Layout das classes"); nomes de classes nas tasks. *(Tipo original: "Aberto".)* | §Conformidade |
| Q-003-05 | Resolvido | Fronteira entre "aggregate próprio" e "dado derivado" do gateway/reader. **Resolvido (26/10/2026):** repositórios gravam e leem as tabelas próprias do plugin; readers leem dados do Moodle (spec-004) e montam os modelos de leitura das consultas e relatórios (018, 019), sem gravar. *(Tipo original: "Aberto".)* | §Contratos |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |
| R-G3 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | Marcadores `citeturn…` não resolvíveis. *(26/10/2026: mantido; `spec.md` não alterado.)* *(04/11/2026, etapa 5a: Aceito como limitação, por decisão do responsável: as citações não são resolvíveis; os marcadores (`cite` e `turn` com caracteres Unicode de uso privado U+E200, U+E202 e U+E201, que `grep citeturn` não encontra) são mantidos nos 8 `spec.md` e no `research.md` porque o texto original é preservado; as regras dessas frases foram confirmadas por decisões e validações, não pelas citações.)* | §Conformidade |

## Revisão do plano 003 (26/10/2026)

Revisão sem decisão humana nova. Nada foi executado nem testado.

| Item | Fonte | Efeito no plano | Estado |
|---|---|---|---|
| Agregados | planos 005–019 | Q-003-01 resolvida | Incorporada |
| Unidades transacionais e Lock API | DEC-15, 16, 20, 22, 23, 32, 38, 41, 42, 44, 45 | Q-003-02 resolvida; §7 passa | Incorporada; validação em CL-07 |
| Callbacks | planos 021, 022; DEC-42 | Q-003-03 resolvida no nível funcional | Incorporada; validação em CL-41, CL-37, CL-02 |
| Layout das classes | convenções do Moodle; constituição §12; DEC-46, DEC-48 | Q-003-04 resolvida | Incorporada |
| Repositórios × readers | spec-004; planos 018, 019 | Q-003-05 resolvida | Incorporada |

O `spec.md` desta feature não foi alterado. As referências a Q-003-04 nos demais planos passam a apontar para a seção "Layout das classes" deste plano, sem necessidade de alterá-los.

## Complexity Tracking

Nenhuma violação justificável.
