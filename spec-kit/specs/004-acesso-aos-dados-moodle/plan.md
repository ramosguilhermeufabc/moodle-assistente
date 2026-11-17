# Implementation Plan: spec-004 — Camada de Acesso aos Dados do Moodle

**Branch**: `feature/sdd-ajustado` (feature dir `004-acesso-aos-dados-moodle`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/004-acesso-aos-dados-moodle/spec.md`

**Aprovação**: aprovado explicitamente pelo responsável em 26/10/2026, sem decisão humana nova. Confirmados: Q-004-01 — APIs de leitura definidas funcionalmente, com as APIs concretas e o eventual uso de DML sujeitos à validação técnica por reader; Q-004-05 — questionários por `mod_quiz` e banco de questões, incluindo slots fixos e aleatórios, versões, tentativas, estados, frações, método de avaliação e `timeclose` (DEC-35, DEC-36); Q-004-07 — contrato de resolução de seção definido funcionalmente, com CL-16 pendente de validação técnica; demais questões resolvidas pelas decisões aprovadas já identificadas. As validações técnicas não são consideradas aprovadas por antecipação. A geração de tasks foi autorizada em 26/10/2026, com os 23 planos aprovados (`closure-register.md` §8.1); tarefas desta feature em [tasks.md](tasks.md).

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado.

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Isolar, em readers/adapters, a leitura dos dados originais do Moodle usados pelas análises: curso/seção, usuário/estudante, módulos de curso, questionários/questões/tentativas/respostas, fóruns/discussões/mensagens, gradebook, conclusão/participação e materiais/arquivos [REQ spec-004 §Objetivo, §Contratos]. A preferência é pelas APIs de domínio Moodle, com DML apenas quando não houver API apropriada [REQ §Contratos, §Conformidade]. Não copiar dados Moodle genericamente para tabelas próprias. `cmid` identifica o módulo, as notas vêm do gradebook e as questões são identificadas por `question_id + question_version` [REQ §Regras].

*(Incluído em 26/10/2026, na revisão do plano 004, sem decisão humana nova.)* O que cada reader precisa ler está definido pelas decisões aprovadas: questionário = `mod_quiz` (DEC-35); materiais `mod_resource` PDF, `mod_page` e `mod_url` do YouTube (DEC-33); estudante = matrícula ativa com papel avaliado do gradebook (DEC-37, DEC-47); grupos (DEC-43); participação (DEC-39); nota final por item e total do curso (DEC-37); disponibilidade por usuário (DEC-26); idioma e fuso do curso/site (DEC-44, DEC-46). As APIs concretas do Moodle 5.2 são candidatas, verificadas nas validações técnicas indicadas, sem presumir existência.

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: APIs de domínio Moodle 5.2 para cada fonte. As APIs concretas estão NEEDS CLARIFICATION (Q-004-01) (04/11/2026: resolvido: Q-004-01 resolvida no plano 004; readers em `assistente/classes/infrastructure/moodle/`, validados nas CL indicadas (todas Fechadas)). DML como último nível [REQ §Conformidade]. File API para arquivos [REQ spec-008 §Conformidade]. *(26/10/2026: candidatas por reader na seção "Readers e validação"; verificação nas CL indicadas.)*

**Storage**: somente leitura de dados Moodle; nada é copiado genericamente [REQ §Regras].

**Testing**: integração com dados Moodle (spec-023). Runner em Q-023-01. *(26/10/2026: PHPUnit com os geradores de dados do Moodle, DEC-48.)*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, camada anticorrupção de leitura.

**Performance Goals**: N/A.

**Constraints**: `slot` não é requisito [REQ §Regras]; notas "conforme configuração do Moodle" [REQ §Regras].

**Scale/Scope**: 8 readers lógicos [REQ §Contratos].

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | — |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | — |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Nenhuma API Moodle 5.2 foi verificada (constituição §5: "não pode ser presumida sem evidência") (Q-004-01). *(26/10/2026: validação técnica que não bloqueia o planejamento, distribuída por reader nas CL-02, 16, 33, 34, 35, 36, 38, 39 e 41; as APIs são candidatas.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-02, CL-16, CL-33, CL-34, CL-35, CL-36, CL-38, CL-39 e CL-41, todos com estado Fechado no `closure-register.md`, com limitações aceitas registradas)* |
| §6 Materiais | BLOQUEADO | PASSA | Os tipos funcionais foram definidos por DEC-33 D2 (`mod_resource` PDF, `mod_page`, `mod_url` do YouTube); Book/Folder fora do escopo (constituição §6). *(Alterado em 26/10/2026; texto original: "BLOQUEADO \| O mapeamento PDF/TEXT/YouTube → recursos Moodle está indefinido (Q-004-02). Book/Folder fora do escopo (constituição §6).")* |
| §7 Integridade | N/A | N/A | Somente leitura. |
| §8 Idempotência | N/A | N/A | — |
| §9 Testabilidade | PASSA | PASSA | [quickstart.md](quickstart.md). *(26/10/2026: cenários Q1–Q17.)* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | PASSA | PASSA | — |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* *(26/10/2026: readers em `classes/infrastructure/moodle/`, plano 003.)* |
| §13 Uso de LLM | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5, §6. Questões Bloqueador: Q-004-01. Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(26/10/2026: só §5, validação técnica que não bloqueia o planejamento; §6 passa; questões bloqueadoras: nenhuma — Q-004-01 passa a validação técnica por reader.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle passa (CL-02, 16, 33, 34, 35, 36, 38, 39 e 41 Fechados); nenhum princípio BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/004-acesso-aos-dados-moodle/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/moodle-readers.md
```

### Source Code (repository root)

```text
assistente/
└── [classes autocarregáveis: readers/adapters]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: layout aprovado em 26/10/2026; readers em `assistente/classes/infrastructure/moodle/`)
```

**Structure Decision**: a spec não nomeia caminhos. *(26/10/2026: `classes/infrastructure/moodle/`, layout aprovado no plano 003.)*

## Readers e validação *(incluído em 26/10/2026)*

As APIs listadas são **candidatas**, a verificar no Moodle 5.2 (constituição §5); se não houver API apropriada, o reader usa DML com justificativa registrada.

| Reader | O que precisa ler (decisões) | APIs candidatas | Validação |
|---|---|---|---|
| CourseSectionReader | seções, seção delegada, resolução e validação de seção (spec-004 §Regras); idioma forçado do curso (DEC-46); fuso do site (DEC-44); `course.enddate` (DEC-39) | modinfo do curso e informações de seção | CL-16, CL-39, CL-41 |
| StudentReader | matrícula ativa com papel avaliado do gradebook (DEC-37, DEC-47); grupos, modo de grupos e grupos visíveis ao usuário (DEC-43) | API de matrícula, papéis avaliados do gradebook, API de grupos | CL-38 |
| CourseModuleReader | módulo, seção, visibilidade e disponibilidade por usuário (DEC-26, DEC-39); datas de entrega/encerramento (DEC-39, DEC-44); distinção atividade × recurso (DEC-39) | modinfo por usuário e API de disponibilidade condicional | CL-02, CL-36 |
| QuestionnaireReader | `mod_quiz`: slots fixos e aleatórios, versão vigente, candidatas do filtro, enunciado, alternativas, resposta do professor (DEC-35); tentativas, estados, frações, método de avaliação (DEC-36); `timeclose` | API do banco de questões e de tentativas do `mod_quiz` | CL-33 |
| ForumReader | fóruns, discussões e mensagens existentes; papel genérico de quem postou, sem identificadores para a LLM (DEC-34 D3, DEC-38); postagens por usuário (DEC-39, DEC-40) | API do `mod_forum` | CL-35 |
| GradebookReader | nota final por item com `cmid`, mínimo e máximo, escalas, substituições; total final do curso depois da reavaliação (DEC-37) | API de notas do gradebook | CL-34 |
| ParticipationReader | conclusão (completa, aprovada, reprovada), submissão, tentativa concluída, postagem, último acesso ao curso (DEC-39) | API de conclusão, API de submissão, registro de acesso ao curso | CL-36 |
| MaterialReader | `mod_resource` com PDF, `mod_page`, `mod_url` do YouTube; conteúdo processável (DEC-33) | File API; dados do `mod_page` e do `mod_url` | CL-02; extração para a LLM em CL-19 (Q-008-03) |

## Dependências entre specs

- Upstream: spec-003 [REQ §Dependências].
- Downstream: 005, 006, 008, 012, 014 (por dependência declarada) e, indiretamente, 010–016. *(26/10/2026: também 018, 019, 021 e 022, pelos readers de grupos, matrícula, datas e idioma.)*

## Fases de planejamento & gates

1. Verificar no Moodle 5.2 a API apropriada para cada reader (bloqueante, Q-004-01) [ESCOLHA: ordem]. *(26/10/2026: deixa de bloquear o planejamento; a verificação é validação técnica por reader, na seção "Readers e validação".)*
2. Definir os contratos de leitura ([contracts/moodle-readers.md](contracts/moodle-readers.md)). *(26/10/2026: contrato atualizado.)*
3. **Gate G-004**: cada reader tem evidência documentada da API Moodle usada, ou justificativa do uso de DML [ESCOLHA: gate derivado de §Contratos]. *(26/10/2026: cenários Q1–Q17; Q11–Q17 são validação técnica.)*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| Q-004-01 | Resolvido | APIs Moodle 5.2 concretas por reader, não verificadas. **Resolvido no nível funcional (26/10/2026):** o que cada reader lê está definido pelas decisões aprovadas e as APIs candidatas estão listadas por reader; a verificação no Moodle 5.2 é validação técnica nas CL indicadas, sem presumir existência e sem bloquear o planejamento. *(Tipo original: "Bloqueador".)* | §Contratos, §Conformidade |
| Q-004-02 | Resolvido | Quais recursos/módulos Moodle representam PDF, texto e YouTube. **Resolvido por DEC-33 D2:** `mod_resource` com PDF, `mod_page`, `mod_url` do YouTube. *(Tipo original: "Aberto".)* | §Contratos; spec-008; DEC-33 |
| Q-004-03 | Resolvido | Definição de "estudante" (critério Moodle para distinguir estudante de outros usuários). **Resolvido por DEC-37 e DEC-47:** usuário com matrícula ativa e papel avaliado do gradebook, lido no momento da consulta. *(Tipo original: "Aberto".)* | §Contratos; DEC-37; DEC-47 |
| Q-004-04 | Resolvido | Fontes concretas de "completion/participation" (ver spec-014). **Resolvido por DEC-39 D1/D3:** submissão, tentativa concluída ou nota, conclusão completa/aprovada/reprovada, postagem no fórum; último acesso ao curso como evidência complementar. *(Tipo original: "Aberto".)* | §Contratos; spec-014; DEC-39 |
| Q-004-05 | Resolvido | Qual módulo Moodle corresponde a "questionário" (a spec não nomeia o módulo). **Resolvido por DEC-35:** `mod_quiz`, com o banco de questões (slots fixos e aleatórios, versões). *(Tipo original: "Aberto".)* | §Contratos; DEC-35 |
| Q-004-06 | Resolvido | "Notas conforme configuração do Moodle": qual valor final (por atividade × total) — ver Q-012-03. **Resolvido por DEC-37:** nota final por item com `cmid`, convertida em percentual do intervalo do item, e total final do curso do gradebook. *(Tipo original: "Aberto".)* | §Regras; DEC-37 |
| Q-004-07 | Resolvido | O contrato funcional da resolução de seção está aprovado (resolução por `course_id` + `cmid`, validação por `course_id` + `section_id`, seção delegada retorna a delegada, condições de não resolvido distintas, sem gravação em `local_assistente_*`). Falta validar no Moodle 5.2: a API que fornece a seção diretamente associada ao módulo, o comportamento para `cmid` de outro curso, módulo sem seção válida e módulo em remoção, e a seção delegada. As referências oficiais consultadas são em parte de versões anteriores à 5.2. A Q-004-01 permanece Bloqueador e cobre as APIs dos oito readers. **Resolvido no nível funcional (26/10/2026):** contrato aprovado e consumido por 010, 013 e 016 (DEC-41 D3); a validação no Moodle 5.2 fica em CL-16 (cenários Q5–Q10 e Q11); Q-004-01 deixou de ser bloqueadora. *(Tipo original: "Aberto".)* | spec-004 §Regras; Q-004-01; CL-16 |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão do plano 004 (26/10/2026)

Revisão sem decisão humana nova. Nada foi executado nem testado.

| Item | Decisão aplicada | Efeito no plano | Estado |
|---|---|---|---|
| Materiais funcionais | DEC-33 D2 | Q-004-02 resolvida; §6 passa | Incorporada |
| Estudante, grupos e matrícula | DEC-37, DEC-43, DEC-47 | Q-004-03 resolvida; StudentReader | Incorporada; validação em CL-38 |
| Participação e acesso ao curso | DEC-39 | Q-004-04 resolvida; ParticipationReader | Incorporada; validação em CL-36 |
| Questionário = `mod_quiz` | DEC-35, DEC-36 | Q-004-05 resolvida; QuestionnaireReader | Incorporada; validação em CL-33 |
| Nota final e total do curso | DEC-37 | Q-004-06 resolvida; GradebookReader | Incorporada; validação em CL-34 |
| Resolução de seção | contrato da spec-004; DEC-41 D3 | Q-004-07 resolvida no nível funcional | Incorporada; validação em CL-16 |
| APIs por reader | constituição §5 | Q-004-01 resolvida no nível funcional; seção "Readers e validação" | Incorporada; validação nas CL indicadas |
| Disponibilidade por usuário; datas; idioma e fuso | DEC-26, DEC-39, DEC-44, DEC-46 | CourseModuleReader e CourseSectionReader | Incorporada; validação em CL-36, CL-39, CL-41 |

O `spec.md` desta feature não foi alterado.

## Complexity Tracking

Nenhuma violação justificável.
