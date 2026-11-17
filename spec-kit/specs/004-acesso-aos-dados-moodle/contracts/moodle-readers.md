# Contract — Readers de dados Moodle (spec-004)

Interfaces lógicas consumidas pelas specs 005–016. A API Moodle subjacente de cada uma está NEEDS CLARIFICATION (Q-004-01) (04/11/2026: resolvido: Q-004-01 resolvida no plano 004; readers em `assistente/classes/infrastructure/moodle/`, validados em CL-02, 16, 33, 34, 35, 36, 38, 39 e 41 (todas Fechadas)). *(26/10/2026: APIs candidatas e validação por reader na seção "Readers e validação" do plano; consumidores também 018–022.)*

| Reader | Entrada | Saída | Consumidores |
|---|---|---|---|
| CourseSectionReader | `course_id` | curso, seções | 005, 008, 014 *(26/10/2026: também resolução e validação de seção (010, 013, 016), idioma forçado do curso (DEC-46), fuso do site (DEC-44) e `course.enddate` (DEC-39))* |
| StudentReader | `course_id` | estudantes (Q-004-03) | 012, 014, 015 *(26/10/2026: estudantes = matrícula ativa com papel avaliado (DEC-37, DEC-47); grupos e grupos visíveis ao usuário (DEC-43); consumidores também 018–021)* |
| CourseModuleReader | `course_id` / `cmid` | módulos, seção, disponibilidade, datas | 008, 014, 019 *(26/10/2026: disponibilidade por usuário (DEC-26); atividade × recurso (DEC-39); datas de entrega/encerramento)* |
| QuestionnaireReader | `cmid` | questões (`question_id`, `question_version`), alternativas, resposta do professor; tentativas/respostas | 010, 011 *(26/10/2026: `mod_quiz` (DEC-35); slots fixos e aleatórios, versão vigente, candidatas do filtro; tentativas, estados, frações, método de avaliação, `timeclose` (DEC-36))* |
| ForumReader | fórum / discussão | discussões, mensagens | 013, 014 *(26/10/2026: mensagens existentes para a reconstrução (DEC-38); papel genérico de quem postou; postagens por usuário (DEC-39, DEC-40))* |
| GradebookReader | estudante, `cmid` | nota final conforme configuração Moodle | 012 *(26/10/2026: mínimo e máximo do item, escalas, substituições; total final do curso (DEC-37); consumidor também 015)* |
| ParticipationReader | estudante, `cmid` | participação/conclusão/entrega, acesso ao curso | 014 *(26/10/2026: conclusão completa/aprovada/reprovada, submissão, tentativa concluída, postagem; último acesso ao curso (DEC-39); consumidor também 015)* |
| MaterialReader | `cmid` | tipo e conteúdo via File API | 008 *(26/10/2026: só `mod_resource` PDF, `mod_page`, `mod_url` do YouTube (DEC-33 D2))* |

Regra: nenhum reader grava dados Moodle em tabelas próprias [REQ §Regras].

## Regras adicionais *(incluído em 26/10/2026)*

- Resolução de seção: conforme spec-004 §Regras — condições de não resolvido distintas (`cmid` inexistente; `cmid` de outro curso; `section_id` inexistente ou de outro curso), sem valor padrão e sem gravação.
- Nenhum reader devolve identificadores de estudantes para montagem de contexto da LLM; a minimização é aplicada pelos serviços consumidores (DEC-34 D3).
- APIs candidatas são verificadas no Moodle 5.2 antes do uso; sem API apropriada, DML com justificativa registrada.
