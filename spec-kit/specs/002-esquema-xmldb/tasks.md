# Tasks: spec-002 — Esquema XMLDB

**Input**: [spec.md](spec.md), [plan.md](plan.md) (aprovado em 26/10/2026), [data-model.md](data-model.md), [research.md](research.md), [quickstart.md](quickstart.md)

**Ordem global**: entrega E1 de [`../README.md`](../README.md) (seção "Ordem de entregas das tasks"). IDs globais.

**Requisitos (AT)**: AT-007, AT-008, AT-009.

**Decisões**: DEC-29 (alteração governada; 21 tabelas, 205 itens), DEC-14, DEC-20, DEC-23, DEC-42 (chaves para fontes Moodle), DEC-48.

**Validações técnicas**: CL-07 (XMLDB, chaves circulares, transação, Lock API), CL-37 (chaves não impostas pelo banco). **Pendências explícitas**: Q-002-03 (`material_version.summary_status`) e Q-002-04 (`availability`) dependem de Q-008-05 e Q-008-07, condições de entrada de T061 (008).

**Testes (DEC-48)**: PHPUnit do Moodle. Cenários Q1–Q6 e Q9 automatizados; Q7 (CL-07) e Q8 (CL-37) são VT.

## Format: `[ID] [P?] [Entrega] Descrição`

---

## Entrega E1 — Esquema

**Objetivo**: `db/install.xml` idêntico ao inventário da spec-002 (205 itens), sem acréscimos.

**Verificação independente**: instalação num Moodle 5.2 limpo cria exatamente as 21 tabelas (gate G-002, cenários Q1–Q5).

- [x] T009 [E1] Criar `assistente/version.php` (`component = 'local_assistente'`, versão no formato `AAAAMMDDXX`, requisito Moodle 5.2) — depende de T001 (CL-24)
  - Refs: plano 002 Q-002-02; plano 003 "Layout das classes"
  - Testes: cobertos pela instalação em T012 e T013
- [x] T011 [E1] Transcrever `spec-kit/install_auditado.xml` para `assistente/db/install.xml` sem acréscimos nem remoções: 21 tabelas `local_assistente_*`, 127 campos, 60 chaves, 18 índices; `material.current_version` → `material_version.id`; `report.current_version` → `report_version.id`; sem `is_current`; `pedagogical_synthesis` com `course_id`, `section_id`, `version`, `input_fingerprint` char(64) e índice único `course_id,section_id,version`; datas de `report` anuláveis e `final_completed_at` (o arquivo de origem em `spec-kit/` não é alterado) *(26/10/2026, CL-45: o `install.xml` e o `install_auditado.xml` ganharam `material_version.content_hash` char(64), passando a 128 campos e 206 itens; DEC-51: `availability` de `material` e `material_version` passou de `int(1)` a `char(20)`, sem mudar a contagem; o teste de esquema foi atualizado, não executado.)*
  - Refs: spec-002 §Inventário, §Regras; plano 002 "Revisão do plano 002"; DEC-29; AT-007, AT-008
- [x] T012 [E1] PHPUnit `assistente/tests/db/install_schema_test.php`: (Q1) exatamente 21 tabelas; (Q2) campos, tipos, `LENGTH`, `DECIMALS`, `NOTNULL`, `SEQUENCE` iguais ao inventário; (Q3) chaves e índices únicos exatamente onde definidos; (Q4) duplicata em índice único (ex.: `material(course_id,cmid)`) rejeitada via DML; (Q5) nenhum campo ou índice fora do inventário (depende de T011) **[arquivo escrito em 26/10/2026; NÃO executado — conclui só com execução bem-sucedida após T013]** **[29/10/2026, evidência: suíte `local_assistente_testsuite` EXECUTADA no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13: OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped (travas reentrantes no mesmo processo), 4 notices]** *(02/11/2026, correção 5 da CL-07: os testes Q2, Q3 e Q5 comparam o **XML** (`install.xml` lido pelo XMLDB) com o inventário, e não consultam o esquema físico do banco; a verificação física é o cenário I-16 da matriz da CL-07, em `quickstart.md`. Nenhum critério da T012 foi ampliado.)*
  - Refs: quickstart 002 Q1–Q5; gate G-002
- [x] T019 [E1] Criar `assistente/db/upgrade.php` com `xmldb_local_assistente_upgrade($oldversion)` sem passos, só depois da primeira instalação validada (T013), e PHPUnit `assistente/tests/db/upgrade_test.php` verificando a função e a regra de savepoint para mudanças futuras (Q6) (depende de T013) **[29/10/2026, evidência: suíte `local_assistente_testsuite` EXECUTADA no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13: OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped (travas reentrantes no mesmo processo), 4 notices]** **[29/10/2026: criados `db/upgrade.php` (sem passos) e `tests/db/upgrade_test.php`, OK 2 testes/4 asserções. `version.php` NÃO foi incrementado de propósito: o incremento exige `upgrade.php` no site compartilhado e é decisão de implantação]**
  - Refs: quickstart 002 Q6; plano 002 Q-002-02; DEC-29
- [x] T020 [E1] VT CL-07: no Moodle 5.2, validar a persistência, as transações, a integridade, os locks e a concorrência real do plugin, no MariaDB e com a Lock Factory efetiva do ambiente, conforme a **matriz de validação da CL-07** (`quickstart.md` desta feature, seção "Matriz de validação da CL-07"): instalação do `install.xml` com as chaves circulares `material` ↔ `material_version` e `report` ↔ `report_version`; **no relatório**, nova versão com troca de `current_version` numa única transação com Lock API e rollback provocado; **no material**, as três etapas independentes da DEC-61 (`add_version`, `complete_summary`, `switch_current`), cada uma sob Lock API por material e em transação própria, com falha de banco provocada em cada uma, recuperação do estado "versão `completed` e não corrente", repetição das tentativas e concorrência real em `material_<id>`; aquisição, liberação e tempo limite dos locks; encerramento abrupto de processo e perda de conexão. **Fora desta tarefa:** a monotonicidade de `current_version` (DEC-62) e o cenário Q17 da 008, que pertencem à T144; a CL-49; a CL-37. Registrar comandos, saídas e divergências em `spec-kit/closure-register.md` CL-07, sem resultado antecipado (usa T015, T016) (depende de T013, T015, T016) *(Alterado em 01/11/2026 por DEC-61 e, em 02/11/2026, pela separação entre CL-07 e T144, opção A confirmada pelo responsável: a cláusula da DEC-62 incluída em 01/11/2026 saiu desta tarefa. Texto original: "no Moodle 5.2, instalar o `install.xml` com as chaves circulares `material` ↔ `material_version` e `report` ↔ `report_version` e gravar uma versão nova com troca de `current_version` numa transação com Lock API e rollback provocado (usa T015, T016); registrar comandos, saídas e divergências em `spec-kit/closure-register.md` CL-07, sem resultado antecipado (depende de T013, T015, T016)". A tarefa continua aberta: só pode ser marcada depois de os critérios de fechamento da CL-07 serem atendidos e de autorização expressa do responsável.)* **[02/11/2026, concluída por autorização expressa do responsável; a frase "A tarefa continua aberta" acima era verdadeira até esta data: a matriz de validação da CL-07 foi executada nas Etapas 2 a 6 e no I-02, com CR-01 a CR-21 comprovados (21 de 21), cada um com a ressalva registrada em `quickstart.md`, "Encerramento formal da CL-07 e conclusão da T020"; resultados em `spec-kit/closure-register.md`, CL-07 (Fechado com ressalvas). Ressalvas preservadas: CR-02 sem nova instalação limpa; CR-19 com "processo morto" por estado equivalente; CR-20 sem distinguir qual comando falhou no worker; saídas brutas históricas não integralmente preservadas; total acumulado de 352 testes e 2.410 asserções sem reexecução conjunta das 31 classes depois do I-02; complementares I-01, I-14, I-16 e U-06 não executados. Fora desta tarefa e abertos: T144, CR-22, CR-23, CL-37, CL-49, CL-50, CL-51 e CL-52]**
  - Refs: quickstart 002 Q7 e "Matriz de validação da CL-07"; quickstart 003 Q10; quickstart 008 Q15; CL-07; DEC-29; DEC-61

**Checkpoint E1 (002)**: G-002 com evidência de T012; CL-07 registrado em T020.

O cenário Q8 (CL-37) é executado em T106 (017), junto com o restante de CL-37. O cenário Q9 é coberto por T016 (003).

---

## Dependencies & Execution Order

- T009 → T011 → T012 → T013 (001) → T019; T020 depende de T013, T015, T016.

## Notes

- `install_auditado.xml` não é alterado; a transcrição ocorre só em `assistente/db/install.xml`, na execução da tarefa.
