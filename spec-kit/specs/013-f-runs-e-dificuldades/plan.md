# Implementation Plan: spec-013 — Fóruns e Dificuldades

**Branch**: `feature/sdd-ajustado` (feature dir `013-f-runs-e-dificuldades`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/013-f-runs-e-dificuldades/spec.md`

**Aprovação**: aprovado pelo responsável em 25/10/2026. A validação técnica do §5 (CL-02, CL-35) e os riscos R-013-01 e R-013-02 continuam pendentes e não bloqueiam o planejamento; Q-013-07 segue para a revisão do plano 015. Nenhum cenário do quickstart foi executado nem validado no Moodle 5.2. A aprovação isolada não autoriza a geração de tasks. *(04/11/2026, revisão dos planos: R-013-02 resolvido pela DEC-65; CL-02 FECHADA em 02/11/2026; CL-35 Fechado (VT) em 03/11/2026.)*

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

A cada postagem ou interação no fórum (que gera um evento, DEC-03), produzir de forma incremental o resumo do fórum e as dificuldades por discussão (`local_assistente_forum_difficulty`). *(Alterado em 25/10/2026, conforme a spec propagada; texto original: "A cada atualização relevante de fórum (que gera um evento), produzir".)* A entrada é o resumo atual + a nova mensagem + os materiais elegíveis da mesma seção, e o histórico é preservado [REQ spec-013 §Objetivo, §Contratos, §Regras]. A interpretação usa a LLM (spec-009). Os eventos Moodle do fórum devem ser verificados na 5.2 [REQ §Conformidade].

*(Incluído em 25/10/2026 por DEC-38 e pelos tratamentos da revisão.)* Cada análise (`analysis_type = forum_analysis`, `cmid` do fórum) é um snapshot completo e imutável do fórum: o resumo em `analysis.summary` e as dificuldades de todas as discussões. A cada mensagem nova ou editada, a LLM recebe a mensagem, o resumo atual e as dificuldades atuais da discussão afetada, e as dificuldades dessa discussão são substituídas pelo resultado válido; as demais são copiadas sem alteração (D1). A exclusão de mensagem ou discussão reconstrói a análise completa com o que ainda existe (D2). O snapshot é gravado de forma atômica. Nenhum identificador do autor vai à LLM (DEC-34 D3).

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: APIs do módulo fórum e Events API [REQ §Conformidade]; ForumReader (spec-004); contexto (spec-008); porta LLM (spec-009), uso `forum_analysis`; tasks (spec-007). *(25/10/2026: incluído o uso da porta.)*

**Storage**: `local_assistente_analysis` (resumo? Q-013-01) + `local_assistente_forum_difficulty` (`discussion_id` → `forum_discussions`, `description` text) [spec-002]. *(25/10/2026: Q-013-01 resolvida — resumo em `analysis.summary`, `analysis_type = forum_analysis`; sem campo novo.)*

**Testing**: LLM mockada; histórico preservado [spec-023]. Runner em Q-023-01. *(25/10/2026: também substituição por discussão, cópia determinística, edição, reconstrução na exclusão, atomicidade e ausência de identificadores do autor.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, análise incremental assistida por LLM.

**Performance Goals**: N/A. Ver R-013-02. *(04/11/2026, revisão dos planos: R-013-02 resolvido pela DEC-65 (sem divisão do conteúdo).)*

**Constraints**: análise incremental; somente contexto da mesma seção; histórico preservado [REQ]. Sem transação aberta durante a chamada à LLM [spec-007]; gravação atômica do snapshot; sem autor, ID de usuário, nome ou e-mail no `context` (DEC-34 D3). *(25/10/2026: incluída a segunda frase.)*

**Scale/Scope**: por fórum/discussão. *(25/10/2026: todas as instâncias de `mod_forum` do curso, sem exclusões não previstas na spec.)*

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-35): eventos de mensagem criada, editada e excluída e de discussão excluída, leitura de mensagens e discussões e papel de quem postou no Moodle 5.2; identidade por evento em CL-02. *(Alterado em 25/10/2026; observação original: "Eventos e API do fórum não verificados (Q-006-01).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-35 (Fechado em 03/11/2026 com limitações aceitas; T084 [x]) e CL-02 (Fechada em 02/11/2026))* |
| §6 Materiais | BLOQUEADO | PASSA | Q-008-01 foi resolvida no nível funcional pela spec-008 (DEC-26). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Herda Q-008-01.")* |
| §7 Integridade/histórico | BLOQUEADO | PASSA | O resumo fica em `analysis.summary` de cada análise imutável; cada análise é um snapshot completo, gravado de forma atômica; dificuldades removidas do snapshot atual seguem nas análises históricas (DEC-38). O alcance da exclusão sobre o histórico fica em CL-15. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Não há tabela dedicada ao resumo do fórum, logo a preservação do histórico do resumo depende de Q-013-01.")* |
| §8 Idempotência | BLOQUEADO | PASSA | Q-006-04 e Q-007-05 foram resolvidas no nível funcional (DEC-21, DEC-32; planos 006 e 007 aprovados). A identidade por mensagem e tipo de evento fica na validação de CL-02. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Herda Q-006-04/Q-007-05 para eventos de fórum.")* |
| §9 Testabilidade | BLOQUEADO | PASSA | Q-013-02 foi resolvida pela spec (DEC-03: toda postagem ou interação). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| "Relevante" indefinido (Q-013-02).")* Cenários Q1–Q13 do [quickstart.md](quickstart.md). *(25/10/2026.)* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | Q-013-03 e Q-013-05 decididas pelo responsável (DEC-38); Q-013-01 resolvida com campo existente, sem alteração de esquema. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Bloqueadores sobre significado ou lacuna da spec dependem de decisão humana (ver Questões Bloqueador).")* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | O resumo e as dificuldades de fórum são interpretação linguística via porta LLM (spec-009). A cópia das dificuldades das outras discussões é determinística, sem LLM; nenhum identificador do autor é enviado (DEC-34 D3). *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0; segunda frase incluída no mesmo dia.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-02 e CL-35; não bloqueia o planejamento). Questões Bloqueador: nenhuma. *(Alterado em 25/10/2026 por DEC-38; texto anterior: "princípios BLOQUEADO: §5, §7, §8, §11. Questões Bloqueador: Q-013-01.")* *(Alterado em 25/10/2026; texto original: "§2, §5, §6, §7, §8, §9, §11".)* Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Reavaliado em 04/11/2026: passam todos os princípios, inclusive §5 Compatibilidade Moodle, validado em CL-35 (Fechado em 03/11/2026 com limitações aceitas; T084 [x]) e CL-02 (Fechada em 02/11/2026); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/013-f-runs-e-dificuldades/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/forum-analysis.md
```

### Source Code (repository root)

```text
assistente/
└── [handler de análise de fórum]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: Q-003-04 resolvida na seção "Layout das classes" de specs/003-*/plan.md (aprovada em 26/10/2026); código em assistente/classes/application/forum/forum_analysis_handler.php.)
```

## Dependências entre specs

- Upstream (spec.md): spec-008, spec-009, spec-007, spec-004 [REQ §Dependências]. Divergência com `specs/README.md` em Q-013-06. *(25/10/2026: `specs/README.md` corrigido para seguir o `spec.md`; Q-013-06 resolvida.)*
- Downstream: 015 (resultado de fórum), 016, 018. *(25/10/2026: 017 recebe o alcance da exclusão sobre o histórico (CL-15); 022, os eventos de fórum.)*

## Fases de planejamento & gates

1. Decidir onde persistir o resumo do fórum e seu histórico (bloqueante, Q-013-01; Q-002-07). *(25/10/2026: resolvido — `analysis.summary`.)*
2. Verificar o evento Moodle de postagem ou interação no fórum (Q-006-01). A regra de disparo já está na spec (DEC-03). *(Alterado em 25/10/2026; texto original: "Definir "atualização relevante" e o evento Moodle (Q-013-02, Q-006-01).")* *(25/10/2026: validação em CL-35.)*
3. Definir a semântica incremental das dificuldades (Q-013-03/04/05). *(25/10/2026: DEC-38 e tratamento de Q-013-04.)*
4. **Gate G-013**: cenários do [quickstart.md](quickstart.md). *(25/10/2026: Q1–Q13, com adapter simulado.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-013-01 | Resolvido | Não há tabela/campo dedicado ao "resumo do fórum". Uso possível de `analysis.summary`, a decidir (spec-002 proíbe inventar campos; Q-002-07). **Resolvido (25/10/2026):** `analysis.summary` (campo existente), `analysis_type = forum_analysis`, `cmid` do fórum; o resumo atual é o da análise bem-sucedida mais recente. Sem campo novo; CL-10 atualizado. *(Tipo original: "Bloqueador".)* | §Contratos; spec-002 |
| Q-013-02 | Resolvido | Critério de "atualização relevante" (herda Q-006-05). **Resolvido pela spec (25/10/2026):** toda postagem ou interação no fórum gera evento e dispara o fluxo (DEC-03). *(Tipo original: "Aberto".)* | §Regras; DEC-03 |
| Q-013-03 | Resolvido | Dificuldades por discussão: uma ou várias por discussão; a atualização incremental substitui ou acumula? **Resolvido por DEC-38 D1 (25/10/2026):** 0..N por discussão; snapshot completo; a discussão afetada é substituída pelo resultado válido da LLM, as demais são copiadas; o histórico guarda as removidas. *(Tipo original: "Aberto".)* | §Contratos; DEC-38 |
| Q-013-04 | Resolvido | Primeira mensagem (sem resumo corrente). **Resolvido (25/10/2026):** resumo atual vazio e nenhuma dificuldade anterior. *(Tipo original: "Aberto".)* | §Regras |
| Q-013-05 | Resolvido | Edição ou remoção de mensagens. **Resolvido por DEC-38 D2 (25/10/2026):** edição = evento incremental identificado como edição; exclusão de mensagem ou discussão = reconstrução completa com o que ainda existe; histórico preservado; alcance sobre o histórico em CL-15. *(Tipo original: "Aberto".)* | §Regras; spec-017; DEC-38 |
| Q-013-06 | Resolvido | O `spec.md` lista dependências 008, 009, 007 e 004; o `specs/README.md` lista 004, 008 e 009, mas ainda não inclui 007, que continua documentada no `spec.md` e neste plano. Este plano usa o `spec.md`. **Resolvido (25/10/2026):** `specs/README.md` corrigido para 008, 009, 007, 004, com o texto original preservado (CL-11). *(Tipo original: "Contradição".)* | §Dependências |
| Q-013-07 | Resolvido | Como o "resultado de fórum" por estudante, exigido por spec-015 (critério binário), é derivado: a análise aqui é por discussão, não por estudante. *(25/10/2026: não bloqueia a 013; decisão na revisão do plano 015, CL-05/CL-12.)* **Resolvido no plano 015 por DEC-40 D3 (25/10/2026):** o resultado por estudante vem das postagens do Moodle, não desta análise; CL-12 fechado. Referência incluída com autorização do responsável, sem alterar o conteúdo funcional deste plano aprovado. *(Tipo anterior: "Aberto".)* | spec-015; DEC-40 |
| Q-013-08 | Resolvido | Derivação da seção a partir do `cmid` do fórum pela regra da spec-004 (Q-004-07) e comportamento quando a derivação não é inequívoca. **Resolvido no nível funcional (25/10/2026):** o fórum tem um único `cmid`; seção pelo contrato da spec-004; não resolvida = erro técnico, a tentativa falha (P-06). API em Q-004-07. *(Tipo original: "Aberto".)* | spec-013 §Regras; spec-004 |
| R-013-01 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | *(Incluído em 25/10/2026.)* Se o evento de uma mensagem terminar em `failure` (3 tentativas), a mensagem não entra na análise atual até uma atualização posterior que a inclua ou uma reconstrução completa. Nenhum mecanismo adicional foi criado. *(04/11/2026, etapa 5a: Aceito como limitação (04/11/2026); evidência: decisão do responsável: volume, custo ou falha repetida, casos de exceção não tratados.)* | DEC-32; DEC-38 |
| R-013-02 | Resolvido *(tipo original: "Risco")* | *(Incluído em 25/10/2026.)* A reconstrução completa de um fórum grande pode exceder os limites do modelo, ainda não escolhido (CL-19). A divisão do conteúdo fica para as tasks do adapter real. *(04/11/2026, etapa 5a: Resolvido; evidência: DEC-65 (premissa de até cerca de 100 mensagens por fórum; fóruns maiores são caso de exceção, não tratado); T136 cancelada (`specs/009-*/tasks.md`).)* | DEC-38 D2; DEC-34 D1 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

Revisão deste plano depois da propagação das decisões aprovadas às specs (22/10/2026). Nada foi executado nem testado.

| Regra na spec | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Toda postagem ou interação no fórum gera evento [REQ spec-013 §Regras] | DEC-03 | Summary e fase 2 corrigidos; Q-013-02 resolvida; §9 passa | Incorporada |
| Seção derivada do `cmid` pela regra da spec-004 [REQ §Regras] | DEC-13 | Já refletida em Q-013-08 | Incorporada |
| Elegibilidade de material (spec-008) | DEC-26 | §6 passa | Incorporada |
| Dificuldades por discussão; edições e exclusões | DEC-38 | Q-013-03 e Q-013-05 resolvidas; §7 e §11 passam | Incorporada (25/10/2026); validação em CL-35 |
| Sem identificadores do autor no `context` | DEC-34 D3 | Constraints, §13, contrato; quickstart Q11 | Incorporada (25/10/2026) |

Continuam abertas: Q-013-01 (resumo do fórum sem campo, `closure-register.md` CL-10; mecanismo de alteração do esquema definido por DEC-29), Q-013-06 (dependências × `specs/README.md`, CL-11) e Q-013-07 (resultado por estudante, CL-12). *(25/10/2026: Q-013-01 e Q-013-06 resolvidas; continua aberta só Q-013-07, que pertence à revisão do plano 015.)*

O `spec.md` desta feature não foi alterado: DEC-38 afeta só o plano. Propagações pendentes, a aplicar na revisão de cada plano: 002 (Q-002-07: resumo do fórum em `analysis.summary`), 015 (resultado de fórum por estudante, Q-013-07), 016 (consumo do resumo e das dificuldades do snapshot atual; DEC-34 D3), 017 (CL-15: exclusão × histórico e chave estrangeira para discussão excluída), 018 (snapshot atual × histórico), 022 (eventos de fórum). *(Incluído em 25/10/2026.)* *(04/11/2026, revisão dos planos: propagação da DEC-38 aos planos-alvo 015 não feita; superada pela implementação e validação: 015: T091 [x] (critério de fórum pelas postagens do Moodle, DEC-40 D3), CL-12 Fechado em 25/10/2026; os planos-alvo não foram reabertos. Planos-alvo com citação: 002, 016, 017, 018, 022.)*

**Referência a DEC-46** *(incluída em 26/10/2026, na revisão do plano 021, com autorização do responsável; alteração somente referencial, sem mudança do conteúdo funcional deste plano aprovado)*: cada `analysis` `forum_analysis` (incremental ou reconstrução) preserva no seu contexto o idioma utilizado para gerar o resumo e as dificuldades, que é o idioma forçado do curso ou, na falta dele, o idioma padrão do site; análises existentes não são regeneradas nem traduzidas quando o idioma muda. Ver `decision-register-DEC-01-25.md`, DEC-46.

## Complexity Tracking

Nenhuma violação justificável.
