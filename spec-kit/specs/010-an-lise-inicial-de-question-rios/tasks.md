# Tasks: spec-010 — Análise Inicial de Questionários

**Input**: [spec.md](spec.md), [plan.md](plan.md) (aprovado em 25/10/2026), [data-model.md](data-model.md), [contracts/questionnaire-initial-analysis.md](contracts/questionnaire-initial-analysis.md), [research.md](research.md), [quickstart.md](quickstart.md)

**Ordem global**: entrega E6 de [`../README.md`](../README.md) (seção "Ordem de entregas das tasks"). Consumidor da porta LLM pelo adaptador simulado (DEC-33 D1); depende de E5 (snapshot de contexto). IDs globais.

**Requisitos (AT)**: AT-023, AT-049, AT-050, AT-051.

**Decisões**: DEC-31 (evento de publicação), DEC-35 D1 (questões analisadas), D2 (`coverage = 0.0` sem materiais, sem chamada), DEC-34 D3, DEC-46, DEC-16, P-06, DEC-48.

**Validações técnicas**: CL-33 (T069). **Risco**: R-010-01 (muitas candidatas aleatórias) — duração medida e registrada em T068, sem limite novo.

**Testes (DEC-48)**: PHPUnit com adaptador simulado. Q1–Q11 automatizados; Q12 é VT.

## Format: `[ID] [P?] [Entrega] Descrição`

---

## Entrega E6 — Análise inicial de questionário

**Objetivo**: ao evento de publicação, `coverage` do questionário e `complexity` por questão, validados e gravados numa única transação.

**Verificação independente**: gate G-010 — cenários Q1–Q11 com o simulado.

- [x] T067 [E6] Criar `assistente/classes/infrastructure/persistence/quiz_initial_repository.php` (agregado `analysis` `quiz_initial` + `questionnaire_analysis` + `questionnaire_question`, gravado tudo ou nada, uma linha por questão e versão sem duplicata); estender `assistente/tests/generator/lib.php`; PHPUnit `assistente/tests/infrastructure/persistence/quiz_initial_repository_test.php` (depende de T015, T016, T017) **[26/10/2026: implementado em `classes/infrastructure/persistence/quiz_initial_repository.php` (`save` grava `analysis` `quiz_initial`, `questionnaire_analysis` e `questionnaire_question` numa transação, sob o lock do evento do processamento; uma análise por processamento, duplicata rejeitada sem alterar a primeira; uma linha por questão e versão; `coverage` e `complexity` em {0.0 a 1.0, passo 0.1}; leituras por processamento e por curso e questionário) e `create_processing` em `tests/generator/lib.php`. Teste `tests/infrastructure/persistence/quiz_initial_repository_test.php` EXECUTADO: OK (15 testes, 54 asserções), incluindo reversão total quando o banco recusa a segunda questão (gatilho de teste). Regressão OK: generator, repository, install_schema, architecture, event_repository, material_repository. Os 2 testes pulados de `unit_of_work_test` já existiam]**
  - Refs: plano 003 "Agregados"; spec-002 (`questionnaire_question`); DEC-35 D1
- [x] T068 [E6] Criar `assistente/classes/application/quiz/initial_analysis_handler.php` e registrá-lo para o evento de publicação: seção resolvida por T021 (não resolvida → erro técnico e tentativa falha, nunca `coverage = 0.0`); snapshot por T064; questões de qualquer tipo exceto `description`, posições fixas na versão vigente e candidatas do filtro aleatório, sem duplicata; chamadas `question_complexity` por questão e `quiz_coverage` (omitida com `coverage = 0.0` quando não há material elegível) fora de transação; validação por T053; gravação só se todos os resultados forem válidos; contexto sem tentativas, respostas ou identificadores de estudantes (T055); instructions de cobertura e complexidade (Q-009-05) com o idioma de geração; duração registrada (R-010-01); PHPUnit `assistente/tests/application/quiz/initial_analysis_handler_test.php` (Q1–Q11) (depende de T045, T050, T054, T055, T064, T067, T024) **[26/10/2026: implementado em `classes/application/quiz/initial_analysis_handler.php` e registrado em `handler_registry::DEFAULT_HANDLERS` (`quiz_published`). Seção por `resolve_module_section` (não resolvida: erro técnico, nunca `coverage = 0.0`); snapshot por T064 gravado uma vez por processamento e reaproveitado nas retentativas; questões de qualquer tipo menos `description`, posição fixa na versão vigente e candidatas do filtro, sem duplicata; `question_complexity` por questão e `quiz_coverage` (omitida com `coverage = 0.0` sem material elegível) sem transação aberta; grava tudo ou nada pelo repositório de T067; contexto com enunciado, alternativas, resposta do professor e resumos, sem dados de estudante; duração e número de chamadas na resposta da tentativa (R-010-01); idempotente por processamento. Teste `tests/application/quiz/initial_analysis_handler_test.php` EXECUTADO: OK (13 testes, 55 asserções; Q1 a Q11 mais idempotência e registro); a remoção da exclusão de `description` derrubou 1 teste. Q4: a posição fixa segue a versão vigente, então duas versões aparecem em análises sucessivas, não na mesma. Textos das instruções provisórios (Q-009-05 aberta). Regressão: `process_queue_test` ajustado (usava `quiz_published` como tipo sem handler; agora `quiz_closed`) e OK; quiz_publication_service, context_snapshot_builder, repositórios, architecture e install_schema OK. FORA DO ESCOPO, já falhavam antes (verificado sem esta mudança): 4 testes de `queue_processor_test` (T050) e avisos de `questionnaire_reader_test` (T024)]**
  - Refs: quickstart 010 Q1–Q11; DEC-35 D1, D2; DEC-31; DEC-34 D3; DEC-16; P-06; spec-004 §Regras (seção); AT-049–AT-051
- [x] T069 [E6] VT CL-33 (Q12): no Moodle 5.2, questionário com posições fixas e aleatórias e várias versões; o reader resolve versões vigentes e candidatas do filtro e extrai enunciado, alternativas e resposta por tipo; registrar em `spec-kit/closure-register.md` CL-33, sem resultado antecipado (depende de T068) **[26/10/2026: executada no Moodle 5.2.4; resultados em `closure-register.md` CL-33 (parte de leitura). `cl33_quiz_reading_test` OK (5 testes, 35 asserções); `questionnaire_reader_test` e `initial_analysis_handler_test` OK após a correção do reader (o aviso restante de `questionnaire_reader_test` é uma depreciação de `quiz_attempt::process_finish` na fixture, de T024). Limitações: resposta do professor de multichoice e match e pares de match não disponíveis; CL-33 segue aberta para tentativas e estados (T033 a T036)]**
  - Refs: quickstart 010 Q12; CL-33

**Checkpoint E6 (010)**: G-010 com evidência de T068.

---

## Dependencies & Execution Order

- T067 → T068 → T069. Depende de E3 (T045, T050), E4 (T054, T055) e E5 (T064).
- Downstream: T072 (011, vínculo `questionnaire_question_id`), T098 (016, gatilho de síntese).
