# Evidência por cenário (T139)

Gerado por `spec-kit/validation/t139/generate_evidence.py` (repetido na T140). Evidência bruta em `spec-kit/validation/t139/` (`run.md`, `phpunit-output.txt`, `phpunit-junit.xml`, `coverage.txt`, `behat-output.txt`).

## Resumo da execução

- **Data:** 04/11/2026 (PHPUnit iniciado às 02:14 e Behat às 10:06, horário do shell, UTC-3)
- **Commit testado:** `bb1f22aa5c2b35f0bdcd0b292007b2d386c859d5` (`git rev-parse HEAD`; árvore limpa, só os arquivos de `spec-kit/validation/t139/` não versionados)
- **Ambiente:** Moodle 5.2.4+ (Build: 20261030), PHP 8.3.36 (com PCOV 1.0.12), MariaDB 11.4.13, PHPUnit 11.5.55, Behat 3.29.0, Selenium 4.51.0 (Chrome), Composer 2.11.1; executado como `www-data` no contêiner `moodle`
- **PHPUnit** (`local_assistente_testsuite`, 20m35s no relógio, 20:24.560 pelo PHPUnit): 1588 testes, 10389 asserções, 0 falhas, 0 erros, 5 ignorados (skips), 0 warnings, 4 notices (`debugging()` do Moodle em `cl33_types_test`, variante "unknown seed")
- **Behat** (`--tags=@local_assistente`, 1m35s): 22 cenários (22 aprovados), 233 passos (233 aprovados)
- **Cobertura de linhas (pcov, sem limiar, só código do plugin sem `tests/`):** 91,91% (6114/6652 linhas); métodos 84,99% (719/846); classes 59,78% (107/179). Por diretório de classes (linhas): application 94,27%; domain 99,27%; infrastructure 94,53%; observer 94,85%; task 97,80%; output 92,99%; form 98,94%; privacy 98,85%; event 80,00%; hook_callbacks 86,67%. Detalhe em `coverage.txt`.

## Totais da matriz

- Cenários na matriz: 310
- Com evidência de teste aprovado (PHPUnit/Behat): 274
- CL, com a evidência da CL e o estado (não contam como teste aprovado): 36
- Em "a conferir": 0
- Cenários dos quickstarts 001 a 022 sem linha na matriz: 0
- Reprovados: 0

*(04/11/2026: a matriz passou de 289 para 310 cenários com a inclusão de 008 Q12–Q14, 009 Q11–Q17 e 011 Q15–Q25, que já tinham teste aprovado na T139. Por classificação: 265 PHPUnit, 9 Behat e 36 CL; com evidência de teste aprovado, 274 (265 PHPUnit e 9 Behat) e 36 CL; antes: 245 PHPUnit, 9 Behat e 35 CL, 254 aprovados.)*

**Granularidade:** a tarefa indica os arquivos de teste; o resultado é o do arquivo (PHPUnit: JUnit; Behat: feature), não o de cada método ou cenário Behat individual. Cenários CL nunca contam como teste aprovado.

## Evidência por cenário

| Cenário | Classificação | Evidência | Resultado | Data | Versão do Moodle |
|---|---|---|---|---|---|
| 001 Q1 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q2 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q3 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q4 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q5 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q6 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q7 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q8 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q9 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q10 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q11 | CL (CL-01) | closure-register.md CL-01: Fechado | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 001 Q12 | CL (CL-22) | closure-register.md CL-22: Fechado (VT) em 04/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 002 Q1 | PHPUnit | tests/db/install_schema_test.php | aprovado (6 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 002 Q2 | PHPUnit | tests/db/install_schema_test.php | aprovado (6 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 002 Q3 | PHPUnit | tests/db/install_schema_test.php | aprovado (6 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 002 Q4 | PHPUnit | tests/db/install_schema_test.php | aprovado (6 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 002 Q5 | PHPUnit | tests/db/install_schema_test.php | aprovado (6 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 002 Q6 | PHPUnit | tests/db/upgrade_test.php | aprovado (2 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 002 Q7 | CL (CL-07) | closure-register.md CL-07: Fechado (VT) em 02/11/2026, com ressalvas, por decisão expressa do responsável | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 002 Q8 | CL (CL-37) | closure-register.md CL-37: Fechado (VT) em 28/10/2026, com limitações residuais | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 002 Q9 | PHPUnit | tests/infrastructure/persistence/repository_test.php | aprovado (10 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q1 | PHPUnit | tests/architecture_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q2 | PHPUnit | tests/architecture_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q3 | PHPUnit | tests/architecture_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q4 | PHPUnit | tests/infrastructure/persistence/repository_test.php | aprovado (10 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q5 | PHPUnit | tests/infrastructure/persistence/unit_of_work_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q6 | PHPUnit | tests/infrastructure/persistence/repository_test.php | aprovado (10 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q7 | PHPUnit | tests/infrastructure/persistence/unit_of_work_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q8 | PHPUnit | tests/infrastructure/persistence/unit_of_work_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q9 | PHPUnit | tests/architecture_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 003 Q10 | CL (CL-07) | closure-register.md CL-07: Fechado (VT) em 02/11/2026, com ressalvas, por decisão expressa do responsável | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q1 | PHPUnit | tests/infrastructure/moodle/questionnaire_reader_test.php | aprovado (6 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q2 | PHPUnit | tests/infrastructure/moodle/gradebook_reader_test.php | aprovado (7 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q3 | PHPUnit | tests/infrastructure/moodle/readers_conformance_test.php | aprovado (4 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q4 | PHPUnit | tests/infrastructure/moodle/readers_conformance_test.php | aprovado (4 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q5 | PHPUnit | tests/infrastructure/moodle/course_section_reader_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q6 | PHPUnit | tests/infrastructure/moodle/course_section_reader_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q7 | PHPUnit | tests/infrastructure/moodle/course_section_reader_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q8 | PHPUnit | tests/infrastructure/moodle/course_section_reader_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q9 | PHPUnit | tests/infrastructure/moodle/course_section_reader_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q10 | PHPUnit | tests/infrastructure/moodle/course_section_reader_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q11 | CL (CL-16) | closure-register.md CL-16: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q12 | CL (CL-38) | closure-register.md CL-38: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q13 | CL (CL-36) | closure-register.md CL-36: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q14 | CL (CL-33) | closure-register.md CL-33: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q15 | CL (CL-34) | closure-register.md CL-34: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q16 | CL (CL-35) | closure-register.md CL-35: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 004 Q17 | CL (CL-02, CL-41) | closure-register.md CL-02: FECHADA em 02/11/2026, por autorização do responsável, com limitações residuais.; CL-41: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 005 Q1 | PHPUnit | tests/application/access_service_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 005 Q2 | PHPUnit | tests/application/access_service_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 005 Q3 | PHPUnit | tests/application/access_service_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 005 Q4 | PHPUnit | tests/application/course_config_service_test.php | aprovado (22 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 005 Q5 | PHPUnit | tests/application/course_config_service_test.php | aprovado (22 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 005 Q6 | PHPUnit | tests/application/course_config_service_test.php | aprovado (22 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 005 Q7 | PHPUnit | tests/application/access_service_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 005 Q8 | PHPUnit | tests/application/access_service_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 005 Q9 | PHPUnit | tests/application/access_service_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q1 | PHPUnit | tests/application/quiz/quiz_publication_service_test.php | aprovado (7 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q2 | PHPUnit | tests/application/quiz/quiz_publication_service_test.php | aprovado (7 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q3 | PHPUnit | tests/observer/forum_observer_test.php | aprovado (9 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q4 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q5 | PHPUnit | tests/infrastructure/persistence/event_repository_test.php, tests/domain/event/event_transitions_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q6 | PHPUnit | tests/application/quiz/quiz_publication_service_test.php | aprovado (7 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q7 | PHPUnit | tests/application/quiz/quiz_publication_service_test.php | aprovado (7 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q8 | PHPUnit | tests/observer/forum_observer_test.php | aprovado (9 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q9 | PHPUnit | tests/application/event_service_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 006 Q10 | PHPUnit | tests/application/event_service_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q1 | PHPUnit | tests/task/process_queue_test.php | aprovado (4 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q2 | PHPUnit | tests/task/process_queue_test.php | aprovado (4 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q3 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q4 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q5 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q6 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q7 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q8 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q9 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q10 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q11 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 007 Q12 | PHPUnit | tests/application/queue/queue_processor_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q1 | PHPUnit | tests/application/material/material_service_test.php | aprovado (36 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q2 | PHPUnit | tests/application/material/material_service_test.php | aprovado (36 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q3 | PHPUnit | tests/application/context/context_snapshot_builder_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q4 | PHPUnit | tests/application/context/context_snapshot_builder_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q5 | PHPUnit | tests/application/material/material_service_test.php | aprovado (36 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q6 | PHPUnit | tests/application/context/context_snapshot_builder_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q7 | PHPUnit | tests/application/context/context_snapshot_builder_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q8 | PHPUnit | tests/observer/material_observer_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q9 | PHPUnit | tests/observer/material_observer_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q10 | PHPUnit | tests/application/material/material_service_test.php | aprovado (36 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q11 | PHPUnit | tests/domain/material/material_classifier_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q12 | PHPUnit | material_observer_test::test_rename_visibility_and_restrictions_create_no_version; material_service_test::test_reevaluation_moves_the_material_both_ways_without_a_version; material_repository_test::test_availability_transitions_leave_versions_and_current_version_alone (ligação decidida pelo responsável em 04/11/2026) | aprovado (3 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q13 | PHPUnit | material_service_test::test_each_version_keeps_the_availability_observed_at_its_creation; material_repository_test::test_completing_persists_the_summary_and_keeps_the_availability (ligação decidida pelo responsável em 04/11/2026) | aprovado (2 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q14 | PHPUnit | material_service_test::test_no_students_keeps_the_value_held; material_service_test::test_availability_none_is_unavailable_and_no_students_is_pending; material_service_test::test_failed_evaluation_neither_changes_the_state_nor_stops_the_processing (ligação decidida pelo responsável em 04/11/2026) | aprovado (3 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q15 | CL (CL-07) | closure-register.md CL-07: Fechado (VT) em 02/11/2026, com ressalvas, por decisão expressa do responsável | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q16 | PHPUnit | tests/infrastructure/persistence/material_repository_test.php (ligação decidida pelo responsável em 04/11/2026) | aprovado (34 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q17 | PHPUnit (multiprocesso) | tests/infrastructure/persistence/material_monotonic_concurrency_test.php (ligação decidida pelo responsável em 04/11/2026) | aprovado (6 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q18 | PHPUnit | tests/application/material/material_service_test.php (ligação decidida pelo responsável em 04/11/2026) | aprovado (36 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 008 Q19 | PHPUnit | tests/application/context/context_snapshot_builder_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q1 | PHPUnit | tests/infrastructure/llm/fake_adapter_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q2 | PHPUnit | tests/infrastructure/llm/result_validator_test.php | aprovado (27 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q3 | PHPUnit | tests/infrastructure/llm/fake_adapter_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q4 | PHPUnit | tests/application/deterministic_no_llm_test.php, tests/architecture_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q5 | PHPUnit | tests/infrastructure/llm/adapter_factory_test.php | aprovado (5 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q6 | PHPUnit | tests/infrastructure/llm/result_validator_test.php | aprovado (27 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q7 | PHPUnit | tests/infrastructure/llm/result_validator_test.php | aprovado (27 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q8 | PHPUnit | tests/infrastructure/llm/fake_adapter_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q9 | PHPUnit | tests/application/llm/context_guard_test.php, tests/application/llm/instruction_builder_test.php | aprovado (21 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q10 | CL (CL-32) | closure-register.md CL-32: Fechado em 03/11/2026 (validação técnica, com ressalvas). | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q11 | PHPUnit | tests/infrastructure/llm/http_adapter_test.php | aprovado (63 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q12 | PHPUnit | tests/infrastructure/llm/http_adapter_test.php | aprovado (63 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q13 | PHPUnit | tests/infrastructure/llm/http_adapter_test.php | aprovado (63 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q14 | PHPUnit | tests/infrastructure/llm/http_adapter_test.php | aprovado (63 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q15 | PHPUnit | tests/infrastructure/llm/http_adapter_test.php | aprovado (63 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q16 | PHPUnit | tests/infrastructure/llm/http_adapter_test.php | aprovado (63 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 009 Q17 | CL (CL-32) | closure-register.md CL-32: Fechado em 03/11/2026 (validação técnica, com ressalvas). | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q1 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q2 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q3 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q4 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q5 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q6 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q7 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q8 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q9 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q10 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q11 | PHPUnit | tests/application/quiz/initial_analysis_handler_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 010 Q12 | CL (CL-33) | closure-register.md CL-33: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q1 | PHPUnit | tests/domain/quiz/metrics_calculator_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q2 | PHPUnit | tests/domain/quiz/metrics_calculator_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q3 | PHPUnit | tests/application/quiz/results_handler_test.php | aprovado (18 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q4 | PHPUnit | tests/application/deterministic_no_llm_test.php, tests/architecture_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q5 | PHPUnit | tests/domain/quiz/metrics_calculator_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q6 | PHPUnit | tests/domain/quiz/metrics_calculator_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q7 | PHPUnit | tests/application/quiz/results_handler_test.php | aprovado (18 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q8 | PHPUnit | tests/domain/quiz/metrics_calculator_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q9 | PHPUnit | tests/application/quiz/results_handler_test.php | aprovado (18 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q10 | PHPUnit | tests/task/detect_quiz_close_test.php | aprovado (6 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q11 | PHPUnit | tests/application/quiz/results_handler_test.php | aprovado (18 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q12 | PHPUnit | tests/application/quiz/results_handler_test.php | aprovado (18 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q13 | PHPUnit | tests/application/quiz/results_handler_test.php | aprovado (18 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q14 | CL (CL-33) | closure-register.md CL-33: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q15 | PHPUnit | tests/application/quiz/results_handler_parts_test.php (ligação decidida pelo responsável em 04/11/2026) | aprovado (27 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q16 | PHPUnit | tests/application/quiz/results_handler_parts_test.php (ligação decidida pelo responsável em 04/11/2026) | aprovado (27 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q17 | PHPUnit | results_handler_parts_test::test_other_types_keep_their_rules (ligação decidida pelo responsável em 04/11/2026) | aprovado (1 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q18 | PHPUnit | results_handler_parts_test::test_randomsamatch_is_out_of_the_indicator (ligação decidida pelo responsável em 04/11/2026) | aprovado (1 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q19 | PHPUnit | results_handler_parts_test::test_regrade_with_another_key_is_recounted (ligação decidida pelo responsável em 04/11/2026) | aprovado (1 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q20 | PHPUnit | tests/application/quiz/results_handler_parts_test.php (ligação decidida pelo responsável em 04/11/2026) | aprovado (27 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q21 | PHPUnit | results_handler_parts_test::test_the_count_of_parts_is_used_not_the_fraction (ligação decidida pelo responsável em 04/11/2026) | aprovado (1 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q22 | PHPUnit | results_handler_parts_test::test_cloze_counts_the_subquestions_graded_right (ligação decidida pelo responsável em 04/11/2026) | aprovado (1 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q23 | PHPUnit | results_handler_parts_test::test_cloze_never_answered_is_no_data; results_handler_parts_test::test_cloze_without_a_fraction_is_out (ligação decidida pelo responsável em 04/11/2026) | aprovado (2 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q24 | PHPUnit | results_handler_parts_test::test_cloze_mean_leaves_the_unanswered_out; results_handler_parts_test::test_cloze_percentage_is_truncated; results_handler_parts_test::test_cloze_with_one_subquestion_has_no_indicator (ligação decidida pelo responsável em 04/11/2026) | aprovado (3 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 011 Q25 | PHPUnit | results_handler_parts_test::test_cloze_with_a_numerical_left_out_gives_no_php_warning; results_handler_parts_test::test_other_warnings_of_the_count_stay_visible (ligação decidida pelo responsável em 04/11/2026) | aprovado (2 métodos de teste; nível de método) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q1 | PHPUnit | tests/application/grade/performance_handler_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q2 | PHPUnit | tests/application/deterministic_no_llm_test.php, tests/architecture_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q3 | PHPUnit | tests/application/grade/performance_handler_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q4 | PHPUnit | tests/domain/grade/grade_normalizer_test.php | aprovado (17 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q5 | PHPUnit | tests/domain/grade/grade_normalizer_test.php | aprovado (17 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q6 | PHPUnit | tests/domain/grade/grade_normalizer_test.php | aprovado (17 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q7 | PHPUnit | tests/application/grade/performance_handler_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q8 | PHPUnit | tests/application/grade/performance_handler_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q9 | PHPUnit | tests/application/grade/performance_handler_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q10 | PHPUnit | tests/application/deterministic_no_llm_test.php, tests/architecture_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 012 Q11 | CL (CL-34) | closure-register.md CL-34: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q1 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q2 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q3 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q4 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q5 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q6 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q7 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q8 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q9 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q10 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q11 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q12 | PHPUnit | tests/application/forum/forum_analysis_handler_test.php | aprovado (25 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 013 Q13 | CL (CL-35) | closure-register.md CL-35: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q1 | PHPUnit | tests/domain/frequency/frequency_calculator_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q2 | PHPUnit | tests/domain/frequency/frequency_calculator_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q3 | PHPUnit | tests/application/frequency/frequency_handler_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q4 | PHPUnit | tests/infrastructure/persistence/frequency_repository_test.php | aprovado (17 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q5 | PHPUnit | tests/domain/frequency/frequency_calculator_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q6 | PHPUnit | tests/domain/frequency/frequency_calculator_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q7 | PHPUnit | tests/domain/frequency/frequency_calculator_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q8 | PHPUnit | tests/domain/frequency/frequency_calculator_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q9 | PHPUnit | tests/domain/frequency/frequency_calculator_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q10 | PHPUnit | tests/domain/frequency/frequency_calculator_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q11 | PHPUnit | tests/task/detect_reference_dates_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q12 | PHPUnit | tests/application/frequency/frequency_handler_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q13 | PHPUnit | tests/domain/frequency/frequency_calculator_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 014 Q14 | CL (CL-36) | closure-register.md CL-36: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q1 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q2 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q3 | PHPUnit | tests/application/deterministic_no_llm_test.php, tests/architecture_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q4 | PHPUnit | tests/application/deterministic_no_llm_test.php, tests/architecture_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q5 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q6 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q7 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q8 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q9 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q10 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q11 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q12 | PHPUnit | tests/domain/criteria/criteria_evaluator_test.php | aprovado (37 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q13 | PHPUnit | tests/application/criteria/criteria_handler_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 015 Q14 | PHPUnit | tests/application/criteria/course_criteria_handler_test.php | aprovado (12 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q1 | PHPUnit | tests/application/synthesis/synthesis_handler_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q2 | PHPUnit | tests/application/synthesis/synthesis_handler_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q3 | PHPUnit | tests/application/synthesis/synthesis_handler_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q4 | PHPUnit | tests/infrastructure/persistence/synthesis_repository_test.php | aprovado (18 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q5 | PHPUnit | tests/domain/synthesis/fingerprint_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q6 | PHPUnit | tests/application/synthesis/synthesis_handler_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q7 | PHPUnit | tests/domain/synthesis/fingerprint_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q8 | PHPUnit | tests/application/synthesis/synthesis_handler_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q9 | PHPUnit | tests/domain/synthesis/fingerprint_test.php | aprovado (31 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q10 | PHPUnit | tests/application/synthesis/synthesis_trigger_test.php | aprovado (10 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q11 | PHPUnit | tests/application/synthesis/synthesis_handler_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q12 | PHPUnit | tests/application/synthesis/synthesis_handler_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q13 | PHPUnit | tests/application/synthesis/synthesis_handler_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 016 Q14 | PHPUnit | tests/application/synthesis/synthesis_handler_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q1 | PHPUnit | tests/application/history/lineage_reader_test.php | aprovado (10 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q2 | PHPUnit | tests/application/history/lineage_reader_test.php | aprovado (10 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q3 | PHPUnit | tests/application/history/source_status_resolver_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q4 | PHPUnit | tests/application/history/lineage_reader_test.php | aprovado (10 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q5 | PHPUnit | tests/application/report/report_builder_test.php | aprovado (13 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q6 | PHPUnit | tests/application/history/source_status_resolver_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q7 | PHPUnit | tests/privacy/provider_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q8 | PHPUnit | tests/privacy/provider_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q9 | PHPUnit | tests/privacy/provider_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q10 | PHPUnit | tests/application/privacy/course_data_eraser_test.php | aprovado (10 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q11 | PHPUnit | tests/application/history/history_growth_test.php | aprovado (3 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q12 | CL (CL-37) | closure-register.md CL-37: Fechado (VT) em 28/10/2026, com limitações residuais | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 017 Q13 | PHPUnit | tests/privacy/erasure_retry_test.php, tests/application/privacy/erasure_retry_scheduler_test.php (ligação decidida pelo responsável em 04/11/2026) | aprovado (22 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q1 | PHPUnit | tests/application/query/individual_queries_test.php | aprovado (9 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q2 | PHPUnit | tests/application/query/individual_queries_test.php | aprovado (9 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q3 | PHPUnit | tests/application/query/individual_queries_test.php | aprovado (9 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q4 | PHPUnit | tests/application/query/aggregate_queries_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q5 | PHPUnit | tests/application/query/student_scope_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q6 | PHPUnit | tests/application/query/aggregate_queries_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q7 | PHPUnit | tests/application/query/student_scope_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q8 | PHPUnit | tests/application/query/student_scope_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q9 | PHPUnit | tests/application/query/aggregate_queries_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q10 | PHPUnit | tests/application/query/presentation_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q11 | PHPUnit | tests/application/query/presentation_test.php | aprovado (16 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q12 | PHPUnit | tests/application/query/aggregate_queries_test.php | aprovado (14 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 018 Q13 | CL (CL-38) | closure-register.md CL-38: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q1 | PHPUnit | tests/domain/report/report_schedule_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q2 | PHPUnit | tests/domain/report/report_schedule_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q3 | PHPUnit | tests/domain/report/report_schedule_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q4 | PHPUnit | tests/application/report/report_view_model_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q5 | PHPUnit | tests/domain/report/report_schedule_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q6 | PHPUnit | tests/domain/report/report_schedule_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q7 | PHPUnit | tests/task/report_dispatcher_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q8 | PHPUnit | tests/domain/report/report_schedule_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q9 | PHPUnit | tests/application/report/report_builder_test.php | aprovado (13 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q10 | PHPUnit | tests/application/report/report_view_model_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q11 | PHPUnit | tests/application/report/report_view_model_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q12 | PHPUnit | tests/application/report/report_view_model_test.php | aprovado (15 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q13 | PHPUnit | tests/domain/report/report_schedule_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q14 | PHPUnit | tests/domain/report/report_schedule_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q15 | PHPUnit | tests/task/report_dispatcher_test.php | aprovado (11 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 019 Q16 | CL (CL-39) | closure-register.md CL-39: FECHADA em 28/10/2026, por autorização do responsável, com limitações residuais. | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q1 | PHPUnit | tests/architecture_test.php, tests/form/course_config_form_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q2 | PHPUnit | tests/architecture_test.php, tests/form/course_config_form_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q3 | PHPUnit | tests/architecture_test.php, tests/form/course_config_form_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q4 | PHPUnit | tests/architecture_test.php, tests/form/course_config_form_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q5 | PHPUnit | tests/architecture_test.php, tests/form/course_config_form_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q6 | PHPUnit | tests/architecture_test.php, tests/form/course_config_form_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q7 | PHPUnit | tests/architecture_test.php, tests/form/course_config_form_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q8 | PHPUnit | tests/architecture_test.php, tests/form/course_config_form_test.php | aprovado (20 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q9 | PHPUnit | tests/application/sync/initial_sync_service_test.php, tests/application/forum/forum_analysis_handler_test.php, tests/application/event_service_test.php, tests/domain/event/occurrence_identity_test.php, tests/application/deterministic_no_llm_test.php, tests/application/queue/handler_registry_test.php | aprovado (103 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q10 | PHPUnit | tests/application/sync/initial_sync_service_test.php, tests/application/forum/forum_analysis_handler_test.php, tests/application/event_service_test.php, tests/domain/event/occurrence_identity_test.php, tests/application/deterministic_no_llm_test.php, tests/application/queue/handler_registry_test.php | aprovado (103 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q11 | PHPUnit | tests/application/sync/initial_sync_service_test.php, tests/application/forum/forum_analysis_handler_test.php, tests/application/event_service_test.php, tests/domain/event/occurrence_identity_test.php, tests/application/deterministic_no_llm_test.php, tests/application/queue/handler_registry_test.php | aprovado (103 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q12 | PHPUnit | tests/application/sync/initial_sync_service_test.php, tests/application/forum/forum_analysis_handler_test.php, tests/application/event_service_test.php, tests/domain/event/occurrence_identity_test.php, tests/application/deterministic_no_llm_test.php, tests/application/queue/handler_registry_test.php | aprovado (103 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q13 | PHPUnit | tests/application/sync/initial_sync_service_test.php, tests/application/forum/forum_analysis_handler_test.php, tests/application/event_service_test.php, tests/domain/event/occurrence_identity_test.php, tests/application/deterministic_no_llm_test.php, tests/application/queue/handler_registry_test.php | aprovado (103 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q14 | CL (CL-40) | closure-register.md CL-40: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q15 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q16 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q17 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q18 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q19 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q20 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q21 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q22 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q23 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q24 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q25 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 020 Q26 | PHPUnit | tests/domain/report/version_filter_test.php, tests/application/report/report_view_model_test.php, tests/application/query/history_filter_test.php, tests/form/query_filter_form_test.php, tests/output/index_page_test.php, tests/output/renderables_test.php | aprovado (55 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q1 | Behat | tests/behat/access.feature | aprovado (4 cenários Behat da feature; nível de feature) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q2 | Behat | tests/behat/report_history.feature | aprovado (1 cenários Behat da feature; nível de feature) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q3 | PHPUnit | tests/output/renderables_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q4 | Behat | tests/behat/no_chat.feature | aprovado (1 cenários Behat da feature; nível de feature) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q5 | Behat | tests/behat/access.feature | aprovado (4 cenários Behat da feature; nível de feature) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q6 | Behat | tests/behat/disabled_course.feature | aprovado (2 cenários Behat da feature; nível de feature) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q7 | PHPUnit | tests/output/renderables_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q8 | PHPUnit | tests/output/renderables_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q9 | Behat | tests/behat/escape.feature | aprovado (1 cenários Behat da feature; nível de feature) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q10 | Behat | tests/behat/group_filters.feature | aprovado (3 cenários Behat da feature; nível de feature) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q11 | Behat | tests/behat/language.feature | aprovado (1 cenários Behat da feature; nível de feature) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q12 | PHPUnit | tests/output/renderables_test.php | aprovado (8 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q13 | Behat | tests/behat/ordering.feature | aprovado (1 cenários Behat da feature; nível de feature) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 021 Q14 | CL (CL-41) | closure-register.md CL-41: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q1 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q2 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q3 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q4 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q5 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q6 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q7 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q8 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q9 | PHPUnit | tests/observer/catalog_test.php, tests/architecture_test.php | aprovado (19 testes; nível de arquivo) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |
| 022 Q10 | CL (CL-02, 33–37, 40, 41) | closure-register.md CL-02: FECHADA em 02/11/2026, por autorização do responsável, com limitações residuais.; CL-33: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026; CL-34: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026; CL-35: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026; CL-36: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026; CL-37: Fechado (VT) em 28/10/2026, com limitações residuais; CL-40: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026; CL-41: Fechado (VT) em 03/11/2026, com limitações aceitas pelo responsável em 03/11/2026 | CL (não conta como teste aprovado) | 04/11/2026 | Moodle 5.2.4+ (Build: 20261030) |

## A conferir

Nenhum.
