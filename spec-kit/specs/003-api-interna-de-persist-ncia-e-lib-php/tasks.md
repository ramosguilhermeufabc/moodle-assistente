# Tasks: spec-003 — API Interna de Persistência e `lib.php`

**Input**: [spec.md](spec.md), [plan.md](plan.md) (aprovado em 26/10/2026, inclusive o layout de Q-003-04), [data-model.md](data-model.md), [contracts/persistence-ports.md](contracts/persistence-ports.md), [research.md](research.md), [quickstart.md](quickstart.md)

**Ordem global**: entrega E1 de [`../README.md`](../README.md) (seção "Ordem de entregas das tasks"). IDs globais. Os repositórios de cada agregado (plano 003, "Agregados") são criados na entrega da feature que os usa: T039 (005), T042 (006), T061 (008), T067 (010), T071 (011), T077 (012), T081 (013), T086 (014), T092 (015), T096 (016), T113 (019).

**Requisitos (AT)**: AT-009, AT-010, AT-011.

**Decisões**: DEC-15, DEC-16, DEC-20, DEC-22, DEC-23, DEC-29, DEC-32, DEC-38, DEC-41, DEC-42, DEC-44, DEC-45 (unidades transacionais), DEC-46 (`lang/pt_br`), DEC-48.

**Validações técnicas**: CL-07 (transação e Lock API, T020); CL-41, CL-37, CL-02 (callbacks e mecanismos, nas features 021, 017, 006/007).

**Testes (DEC-48)**: PHPUnit. Cenários Q1–Q9 automatizados; Q10 é VT (CL-07, T020).

## Format: `[ID] [P?] [Entrega] Descrição`

---

## Entrega E1 — Fundação da persistência

**Objetivo**: camada de infraestrutura que encapsula DML, unidade transacional com Lock API, validação de domínio e gerador de dados de teste.

**Verificação independente**: gate G-003 — nenhum `$DB` fora da infraestrutura, nenhum prefixo físico, `lib.php` sem regra de negócio, nenhuma transação aberta durante chamada à LLM (Q1–Q10).

- [x] T010 [P] [E1] Criar `assistente/lang/en/local_assistente.php` e `assistente/lang/pt_br/local_assistente.php` com `pluginname`, e PHPUnit `assistente/tests/lang_parity_test.php` verificando que os dois pacotes têm as mesmas chaves (depende de T009) **[arquivos escritos em 26/10/2026; teste NÃO executado — conclui só após T013]** **[29/10/2026, evidência: suíte `local_assistente_testsuite` EXECUTADA no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13: OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped (travas reentrantes no mesmo processo), 4 notices]**
  - Refs: plano 003 "Layout das classes"; DEC-46 (interface com `lang/en` e `lang/pt_br`)
- [x] T014 [P] [E1] Criar os domínios de valores em `assistente/classes/domain/`: `event_status.php` (`pending`, `processing`, `processed`, `failure`), `attempt_status.php` (`processing`, `processed`, `failure`), `analysis_type.php` (`quiz_initial`, `quiz_results`, `grade_performance`, `forum_analysis`, `frequency`, `criteria_check`, `section_synthesis`), `material_type.php` (`pdf`, `text`, `youtube`), `criteria_status.php` (1 = atende, 0 = abaixo, 2 = `no_data`), `result_state.php` (`valid`, `no_data`, `error`); sem `summary_status` e sem semântica de `availability`, que aguardam Q-008-05 e Q-008-07 (T057, T058); PHPUnit `assistente/tests/domain/vocabulary_test.php` **[implementado em `classes/domain/*.php` (enums); teste escrito em `tests/domain/vocabulary_test.php`; NÃO executado]** **[29/10/2026, evidência: suíte `local_assistente_testsuite` EXECUTADA no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13: OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped (travas reentrantes no mesmo processo), 4 notices]**
  - Refs: plano 002 Q-002-03, Q-002-04; DEC-32 D4; DEC-33 D2; DEC-40; DEC-24; P-06
- [x] T015 [P] [E1] Criar `assistente/classes/infrastructure/persistence/unit_of_work.php`: transação DML delegada curta, Lock API do Moodle com nomes de recurso por evento, material, (curso, seção) e relatório, e verificação que impede chamar a porta LLM com transação aberta; PHPUnit `assistente/tests/infrastructure/persistence/unit_of_work_test.php` (Q5 falha no meio → nenhum estado parcial; Q7 chamada à LLM antes da transação; Q8 duas gravações concorrentes de versão serializadas) **[implementado em `classes/infrastructure/persistence/unit_of_work.php` e 3 exceções do mesmo diretório; teste escrito em `tests/infrastructure/persistence/unit_of_work_test.php`; NÃO executado. O teste de Q8 cobre só a semântica no processo (exclusão do lock, liberação após commit e rollback); a concorrência real e o backend de lock ficam em T020 (CL-07)]** **[29/10/2026, evidência: suíte `local_assistente_testsuite` EXECUTADA no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13: OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped (travas reentrantes no mesmo processo), 4 notices]** **[Q8: a concorrência real fica em T020 (CL-07); 2 testes de lock skipped no mesmo processo]**
  - Refs: plano 003 "Unidades transacionais"; quickstart 003 Q5, Q7, Q8; spec-007 (sem transação durante LLM)
- [x] T016 [P] [E1] Criar `assistente/classes/infrastructure/persistence/repository.php` (base abstrata: DML com nomes de tabela sem prefixo físico; validação dos valores de domínio antes de gravar; tratamento de violação de índice único sem duplicar) e PHPUnit `assistente/tests/infrastructure/persistence/repository_test.php` (Q4 violação de único tratada; Q6 valor fora do domínio rejeitado — `analysis_type` desconhecido, `*_status` = 3) **[implementado em `repository.php` e `schema_domains.php` (domínios aprovados; `summary_status` e `availability` fora, por Q-008-05/Q-008-07); teste escrito em `tests/infrastructure/persistence/repository_test.php`; NÃO executado. A detecção de violação de único lê o texto do erro do banco e deve ser confirmada em CL-07]** **[29/10/2026, evidência: suíte `local_assistente_testsuite` EXECUTADA no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13: OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped (travas reentrantes no mesmo processo), 4 notices]**
  - Refs: quickstart 003 Q4, Q6; quickstart 002 Q9; plano 003 Q-003-05
- [x] T017 [P] [E1] Criar `assistente/tests/generator/lib.php` (`local_assistente_generator`) para as tabelas próprias, a ser estendido em cada entrega, e PHPUnit `assistente/tests/generator_test.php` **[implementado em `tests/generator/lib.php` (config, criteria, event, material); teste escrito em `tests/generator_test.php`; NÃO executado]** **[29/10/2026, evidência: suíte `local_assistente_testsuite` EXECUTADA no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13: OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped (travas reentrantes no mesmo processo), 4 notices]**
  - Refs: plano 023 Q-023-05; DEC-48
- [x] T018 [E1] Criar PHPUnit `assistente/tests/architecture_test.php`: (Q1) nenhum `$DB` ou SQL em `classes/domain/` e `classes/application/`; (Q2) nenhum prefixo físico de tabela em chamadas DML; (Q3) `lib.php`, quando existir, só com os callbacks previstos (navegação e, se necessário, exclusão de curso); (Q9) classes no namespace `local_assistente` sob `classes/` nos diretórios do layout aprovado. Regras adicionais são acrescentadas por T094, T119 e T131 **[implementado em `tests/architecture_test.php` (análise por tokens, ignora comentários); NÃO executado. Lista de callbacks permitidos em `lib.php`: só a navegação; o callback de exclusão de curso será incluído se CL-37 o exigir]** **[29/10/2026, evidência: suíte `local_assistente_testsuite` EXECUTADA no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13: OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped (travas reentrantes no mesmo processo), 4 notices]**
  - Refs: quickstart 003 Q1, Q2, Q3, Q9; gate G-003; plano 003 "Layout das classes", "Callbacks"

**Checkpoint E1 (003)**: G-003 verificável por T018; CL-07 registrado em T020 (002).

---

## Dependencies & Execution Order

- Todas dependem de T001 e T009; a execução dos testes depende de T013 (001).
- T014, T015, T016, T017 em paralelo; T018 depois delas.

## Parallel Example

```text
T014 assistente/classes/domain/*.php
T015 assistente/classes/infrastructure/persistence/unit_of_work.php
T016 assistente/classes/infrastructure/persistence/repository.php
T017 assistente/tests/generator/lib.php
```

## Notes

- `lib.php` não é criado nesta entrega; ele surge só em T123 (021), se a navegação exigir callback.
