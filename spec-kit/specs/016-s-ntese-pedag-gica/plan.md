# Implementation Plan: spec-016 — Síntese Pedagógica

**Branch**: `feature/sdd-ajustado` (feature dir `016-s-ntese-pedag-gica`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/016-s-ntese-pedag-gica/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026, conforme DEC-41. As validações técnicas CL-16 (seção) e CL-07 (Lock API) continuam pendentes e R-016-01 fica como risco documentado; nenhum deles bloqueia o planejamento. Nenhum cenário do quickstart foi executado. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: validação técnica resolvida: CL-16 Fechado (VT) 03/11/2026; CL-07 Fechado (VT) 02/11/2026; a afirmação acima é do momento da aprovação.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Consolidar desempenho, frequência, fóruns/dificuldades, resumos de materiais e resultados relevantes numa síntese pedagógica (`local_assistente_pedagogical_synthesis.content`) produzida pela LLM. A síntese só é gerada quando há nova informação relevante, reflete a informação mais recente e fica associada ao processing que a gerou [REQ spec-016 §Objetivo, §Contratos, §Regras, §Conformidade]. Todo evento relevante, inclusive toda postagem ou interação no fórum, dispara o fluxo de forma assíncrona. Disparar o fluxo não obriga criar versão: só há nova versão quando muda o conjunto normalizado de informações pedagógicas efetivamente usado, e não por simples diferença textual da saída da LLM. Mudança em uma seção não altera a síntese de outra [REQ §Regras; DEC-03, DEC-04, DEC-13, DEC-19]. *(Incluído em 25/10/2026, conforme a spec propagada em 22/10/2026.)*

*(Incluído em 25/10/2026 por DEC-41 e pelos tratamentos da revisão.)* O conjunto enviado à LLM, que é também o conjunto comparável, reúne só agregados e textos já produzidos (DEC-34 D3): estatísticas por atividade da seção (média, mínimo, máximo, `valid`, `no_data`) e as mesmas estatísticas de frequência da seção (D1); cobertura e complexidade (010); métricas do questionário (011); resumo e dificuldades do snapshot atual do fórum (DEC-38); resumos dos materiais elegíveis (D2). Critérios da spec-015 ficam fora (âmbito de curso). Fingerprint SHA-256 sobre JSON canônico; igual ao atual ⇒ sem LLM e sem versão. Eventos de síntese por (curso, seção) após cada análise a montante, sem aguardar resultados ausentes; só há síntese com pelo menos um resultado pedagógico (D3).

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: porta LLM (spec-009); resultados de 011, 012, 013, 014 e 015. *(25/10/2026: efetivamente 008 (resumos), 010, 011, 012 (notas atuais por atividade), 013 (snapshot atual), 014 (frequência por seção); a 015 não entra (critérios de âmbito de curso). Uso `pedagogical_synthesis` da porta; Lock API para a versão.)*

**Storage**: `pedagogical_synthesis` (`analysis_id`, `content` text, `created_at`) → `analysis.processing_id` [spec-002]. *(25/10/2026: também `course_id`, `section_id`, `version`, `input_fingerprint` char(64), único curso+seção+versão (DEC-29); `analysis.analysis_type = section_synthesis`.)*

**Testing**: LLM mockada; falha LLM [spec-023]. Runner em Q-023-01. *(25/10/2026: também fingerprint estável e sensível, agregados sem dados individuais, gatilhos por seção, ausência de resultados e escape do conteúdo.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, consolidação assistida por LLM.

**Performance Goals**: N/A. Ver R-016-01.

**Constraints**: nova versão só com mudança no conjunto normalizado de informações pedagógicas usado (DEC-04, DEC-19); associar ao processing [REQ]. *(Alterado em 25/10/2026; texto original: "gerar só com nova informação relevante; associar ao processing [REQ].")* Só agregados e textos já produzidos vão à LLM, sem identificadores, nomes ou notas individuais (DEC-34 D3; DEC-41 D1); sem índice único de desempenho da seção (spec-012). *(25/10/2026: incluída a última frase.)*

**Scale/Scope**: escopo da síntese (curso/seção) em Q-016-02. *(25/10/2026: Q-016-02 resolvida — curso + seção, DEC-13.)*

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — *(25/10/2026: entradas restritas às elegíveis da spec-016; sem ponderação própria.)* |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | N/A | N/A | — *(25/10/2026: a resolução de seção usa o contrato da spec-004, validação em CL-16; Lock API em CL-07.)* |
| §6 Materiais | BLOQUEADO | PASSA | Q-008-01 foi resolvida no nível funcional pela spec-008 (DEC-26). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Herda Q-008-01 (resumos usados).")* |
| §7 Integridade | BLOQUEADO | PASSA | Falha ou resposta inválida da LLM não cria versão; a vigente é a de maior sequência, então a última síntese válida permanece (spec-016; DEC-41). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Uma falha da LLM não pode apagar a última síntese válida; o comportamento não está na spec (Q-016-04).")* |
| §8 Idempotência | BLOQUEADO | PASSA | Conjunto comparável e fingerprint definidos (DEC-41 D2): fingerprint igual ⇒ sem versão; versão por `max + 1` sob Lock API; evento pendente reutilizado por (curso, seção) (DEC-21). *(Alterado em 25/10/2026; texto anterior: "BLOQUEADO \| A regra funcional que evita versão redundante está na spec (DEC-04, DEC-19), mas a normalização e o mecanismo de comparação (fingerprint) continuam indefinidos (Q-016-01, `closure-register.md` CL-03)."; observação original: ""Nova informação relevante" indefinida, logo há risco de sínteses duplicadas (Q-016-01).")* |
| §9 Testabilidade | BLOQUEADO | PASSA | Cenários Q1–Q14 do [quickstart.md](quickstart.md). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Idem Q-016-01.")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | Q-016-01 e o desempenho por seção decididos pelo responsável (DEC-41); CL-03 fechado no nível funcional. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Q-016-01 (definição de "nova informação relevante") depende de decisão humana.")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | A síntese pedagógica é interpretação linguística via porta LLM (spec-009). As estatísticas e o fingerprint são determinísticos, sem LLM; só agregados são enviados (DEC-34 D3). *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0; segunda frase incluída no mesmo dia.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: nenhum. Questões Bloqueador: nenhuma. *(Alterado em 25/10/2026 por DEC-41; texto anterior: "princípios BLOQUEADO: §7, §8, §9, §11. Questões Bloqueador: Q-016-01.")* *(Alterado em 25/10/2026; texto original: "§2, §6, §7, §8, §9, §11".)* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: nenhuma linha da tabela tem a coluna Pós em BLOQUEADO; nenhum princípio BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/016-s-ntese-pedag-gica/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/pedagogical-synthesis.md
```

### Source Code (repository root)

```text
assistente/
└── [serviço de síntese]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: Q-003-04 resolvida na seção "Layout das classes" de specs/003-*/plan.md (aprovada em 26/10/2026); código em assistente/classes/application/synthesis/synthesis_handler.php.)
```

## Dependências entre specs

- Upstream: spec-004, 008, 009, 010, 011, 012, 013, 014, 015 [REQ §Dependências]. *(25/10/2026: a 015 continua listada pela spec, mas seus resultados não entram na síntese por seção — DEC-41.)*
- Downstream: 017, 018, 019. *(25/10/2026: também 021 (exibição escapada do conteúdo).)*

## Fases de planejamento & gates

1. Especificar a normalização e o mecanismo de comparação do conjunto de informações pedagógicas (bloqueante, Q-016-01). O gatilho e a regra funcional de versão já estão na spec (DEC-03, DEC-04, DEC-19). *(Alterado em 25/10/2026; texto original: "Definir "nova informação relevante" e o gatilho (bloqueante, Q-016-01).")* *(25/10/2026: definidos por DEC-41.)*
2. Definir o escopo e o formato da síntese (Q-016-02/03) e a política de falha (Q-016-04). *(25/10/2026: curso + seção; texto simples; falha não cria versão.)*
3. **Gate G-016**: sem nova informação não há nova síntese; com nova informação a síntese é nova e associada ao processing. *(25/10/2026: cenários Q1–Q14, com adapter simulado.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-016-01 | Resolvido | Definição operacional de "nova informação relevante" e mecanismo de detecção. **Parcial (25/10/2026):** a definição funcional está na spec: mudança no conjunto normalizado de informações pedagógicas usado, excluindo metadados transitórios e diferença textual da LLM (DEC-04, DEC-19). Continuam bloqueados a normalização e o mecanismo de comparação (fingerprint), que precisam de decisão humana sobre o conjunto comparável (`closure-register.md` CL-03). **Resolvido por DEC-41 D2 (25/10/2026):** conjunto comparável = conjunto enviado à LLM (incluindo o texto do resumo do fórum); fingerprint SHA-256 sobre JSON canônico; igual ao atual ⇒ sem LLM e sem versão. CL-03 fechado no nível funcional. *(Tipo original: "Bloqueador".)* | §Regras; DEC-04, DEC-19, DEC-41 |
| Q-016-02 | Resolvido | Escopo: por curso, por seção ou por atividade (`analysis.cmid` é nullable). Decisão funcional de 21/10/2026 e DEC-13: escopo por curso e seção, com `analysis` própria por síntese. Mantida Aberta: a resolução da seção em cada fluxo está em Q-016-08 e o `analysis_type` em Q-016-09. **Resolvido (25/10/2026):** Q-016-08 e Q-016-09 resolvidas abaixo. *(Tipo original: "Aberto".)* | §Objetivo; DEC-13 |
| Q-016-03 | Resolvido | Formato e estrutura de `content`. **Resolvido (25/10/2026):** texto simples; HTML devolvido pela LLM não é confiável e é exibido escapado; a estrutura interna vem da instruction (Q-009-05). *(Tipo original: "Aberto".)* | §Contratos; plano 009 |
| Q-016-04 | Resolvido | Em falha da LLM, a síntese anterior permanece como vigente? (constituição §7). **Resolvido (25/10/2026):** sim; falha ou resposta inválida não cria versão e a vigente é a de maior sequência (spec-016). *(Tipo original: "Aberto".)* | constituição §7; spec-016 |
| Q-016-05 | Resolvido | Dados identificáveis de estudantes enviados à LLM (herda Q-009-06). **Resolvido por DEC-34 D3 e DEC-41 D1 (25/10/2026):** só agregados e textos já produzidos; sem identificadores, nomes ou notas individuais. *(Tipo original: "Risco".)* | spec-009; DEC-34; DEC-41 |
| Q-016-08 | Resolvido | `pedagogical_synthesis.section_id` será NOT NULL por decisão conceitual aprovada (opção A, DEC-13). A decisão funcional de 21/10/2026 define o percurso: seção derivada de `cmid` quando a relação for inequívoca (regra da spec-004); caso contrário, `section_id` explícito no contexto do processamento; validação de que a seção existe e pertence ao curso; sem seção válida, a síntese por seção não é criada e a condição fica como pendência de processamento. Falta: a regra de `cmid` → seção (Q-004-07), a definição da origem e da validação do contexto explícito, e os eventos e a política de resultados ausentes (Q-006-07). `analysis.cmid` aceita nulo e `analysis` não tem `section_id`. A alteração do XMLDB e o upgrade continuam fora de escopo. O contrato funcional de resolução de seção da spec-004 está aprovado (Q-004-07); a validação técnica no Moodle 5.2 segue pendente. **Resolvido no nível funcional (25/10/2026, DEC-41 D3):** a origem do `section_id` explícito é a análise a montante que afetou a seção (uma por seção afetada); eventos e resultados ausentes definidos; validação técnica em CL-16. *(Tipo original: "Aberto".)* | spec-016 §Regras; spec-004; DEC-13; DEC-41 |
| Q-016-09 | Resolvido | Valor de `analysis_type` da `analysis` própria da síntese, compatível com o modelo existente (Q-002-03). **Resolvido (25/10/2026):** `section_synthesis`. *(Tipo original: "Aberto".)* | spec-016 §Contratos; spec-002 |
| R-016-01 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | *(Incluído em 25/10/2026.)* Cada nova versão custa uma chamada à LLM; como o texto do resumo do fórum entra no fingerprint (DEC-41 D2), fóruns movimentados geram chamadas frequentes. Nenhuma limitação adicional foi introduzida. *(04/11/2026, etapa 5a: Aceito como limitação (04/11/2026); evidência: decisão do responsável: volume, custo ou falha repetida, casos de exceção não tratados.)* | DEC-41 D2 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

### Decisões conceituais aprovadas (sem autorização de implementação)

- `pedagogical_synthesis.section_id` será NOT NULL (opção A, interpretação literal de DEC-13); não há síntese no nível do curso sem seção nesta modelagem.
- A primeira versão será `1`; o incremento será `max(version) + 1` por `(course_id, section_id)`, protegido pela Lock API (registro §4, P-03).
- Isto não autoriza alterar `install_auditado.xml`, `db/upgrade.php` nem a versão do plugin. Pendentes: regra de resolução da seção (Q-016-08), `input_fingerprint`, mecanismo de upgrade e validação no Moodle 5.2. *(25/10/2026: a alteração do esquema foi autorizada por DEC-29 e já está no `install_auditado.xml`, com `course_id`, `section_id`, `version`, `input_fingerprint` char(64) e unicidade por curso, seção e versão. Continuam pendentes: Q-016-08, a normalização do fingerprint (CL-03) e a validação no Moodle 5.2.)* *(25/10/2026: Q-016-08 e CL-03 resolvidos no nível funcional por DEC-41; resta a validação no Moodle 5.2.)*

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas ao `spec.md` (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Todo evento relevante, inclusive fórum, dispara o fluxo; disparar não obriga criar versão [REQ spec-016 §Regras] | DEC-03, DEC-04 | Summary, Constraints e fase 1 atualizados | Incorporada |
| Mudança medida pelo conjunto normalizado de informações pedagógicas, não pelo texto da LLM [REQ §Regras] | DEC-19 | Q-016-01 parcial; §8 e §9 continuam BLOQUEADO até definir a normalização e o fingerprint (CL-03) | Parcial *(25/10/2026: Incorporada por DEC-41 D2)* |
| Síntese por curso e seção; uma seção não altera a outra [REQ §Regras] | DEC-13 | Já refletida em Q-016-02 e Q-016-08 | Incorporada |
| Versão sequencial e imutável por curso e seção, com campos novos no esquema | DEC-14, DEC-29 | Campos no esquema desde 25/10/2026 e regra na spec-016 | Incorporada *(estado original: "Bloqueada (esquema)")* |
| Elegibilidade de material (spec-008) | DEC-26 | §6 passa | Incorporada |
| Agregados por seção, conjunto comparável, gatilhos e resultados ausentes | DEC-41 | Q-016-01, 02, 08 resolvidas; §7, §8, §9 e §11 passam | Incorporada (25/10/2026) |
| Só agregados e textos já produzidos à LLM | DEC-34 D3 | Q-016-05 resolvida; Constraints; quickstart Q6 | Incorporada (25/10/2026) |
| Snapshot atual do fórum | DEC-38 | Entrada de fórum | Incorporada (25/10/2026) |
| Notas atuais por atividade; frequência por seção | DEC-37, DEC-39 | Entradas de D1 | Incorporada (25/10/2026) |
| Critérios de âmbito de curso | DEC-40 | Excluídos da síntese por seção | Incorporada (25/10/2026) |

Q-016-04 (síntese anterior em falha da LLM) continua aberta: nenhuma DEC trata dela. *(25/10/2026: deixou de valer — resolvida pela regra da spec, "vigente = maior sequência".)*

O `spec.md` desta feature não foi alterado: DEC-41 afeta só o plano. Propagações pendentes, a aplicar na revisão de cada plano: 017 (histórico de versões e vínculo com o contexto), 018 e 019 (consulta e relatório da síntese vigente), 021 (exibição escapada do `content`). *(Incluído em 25/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-41 feita: todos os planos-alvo (017, 018, 019, 021) citam a decisão nos artefatos de plano.)*

**Referência a DEC-46** *(incluída em 26/10/2026, na revisão do plano 021, com autorização do responsável; alteração somente referencial, sem mudança do conteúdo funcional deste plano aprovado)*: cada versão de síntese pedagógica preserva no contexto da `analysis` `section_synthesis` o idioma utilizado, que é o idioma forçado do curso ou, na falta dele, o idioma padrão do site; versões existentes não são regeneradas nem traduzidas quando o idioma muda. Ver `decision-register-DEC-01-25.md`, DEC-46.

## Complexity Tracking

Nenhuma violação justificável.
