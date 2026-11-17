# Data Model — spec-004

Modelos de leitura, não persistidos. A spec proíbe cópia genérica [REQ §Regras].

| Read model | Identificador | Campos mínimos citados nas specs | Fonte |
|---|---|---|---|
| Course / Section | `course.id`, `course_sections.id` | — *(26/10/2026: seção delegada; idioma forçado; fuso do site; `enddate`)* | §Contratos; FKs de spec-002 |
| Student (User) | `user.id` | definição de estudante NEEDS CLARIFICATION (Q-004-03) (04/11/2026: resolvido: DEC-37, DEC-47 e DEC-43; `assistente/classes/infrastructure/moodle/student_reader.php`; validação em CL-38 (Fechado em 03/11/2026)) *(26/10/2026: matrícula ativa com papel avaliado, DEC-37/47; grupos, DEC-43)* | §Contratos |
| CourseModule | `cmid` | seção, disponibilidade (spec-008), data final/devida (spec-014/019) *(26/10/2026: disponibilidade por usuário; atividade × recurso)* | §Regras |
| Question | `question_id + question_version` | enunciado, alternativas, resposta do professor (spec-010) *(26/10/2026: `mod_quiz`; entrada do banco de questões entre versões, DEC-36 D5)* | §Regras; spec-010 |
| Attempt / Response | — | respostas por questão (spec-011) *(26/10/2026: estado da tentativa, fração, itens corretos, método de avaliação)* | §Contratos |
| Forum / Discussion / Message | `forum_discussions.id` | mensagens novas (spec-013) *(26/10/2026: também mensagens existentes para reconstrução; papel genérico de quem postou)* | §Contratos |
| Grade (final) | estudante + `cmid` | nota final do gradebook (spec-012) *(26/10/2026: mínimo e máximo do item; total final do curso)* | §Regras |
| Participation / Completion | estudante + `cmid` | participou (spec-014) *(26/10/2026: tipo de participação, DEC-39 D1; último acesso ao curso)* | §Contratos |
| Material file | `cmid` | tipo (PDF/TEXT/YouTube), conteúdo via File API (spec-008) | §Contratos |
