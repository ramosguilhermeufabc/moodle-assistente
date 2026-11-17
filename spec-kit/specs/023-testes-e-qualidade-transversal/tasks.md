# Tasks: spec-023 — Testes e Qualidade Transversal

**Input**: [spec.md](spec.md), [plan.md](plan.md) (aprovado em 26/10/2026, conforme DEC-48), [research.md](research.md), [data-model.md](data-model.md), [quickstart.md](quickstart.md) (matriz por plano); `quickstart.md` dos planos 001–022

**Ordem global**: entrega E12 de [`../README.md`](../README.md) (seção "Ordem de entregas das tasks"). Os testes de cada cenário são escritos nas próprias tarefas das features (DEC-48 D3); esta feature mantém a matriz completa cenário → tarefa e o fechamento do gate G-023. IDs globais.

**Requisitos (AT)**: AT-030, AT-031, AT-038, AT-066; correspondência AT → tarefa de todos os 66 na seção final.

**Decisões**: DEC-48 D1 (PHPUnit e Behat do Moodle; LLM pelo adaptador simulado), D2 (rastreabilidade de cada cenário, sem percentual mínimo; cobertura de linhas medida e reportada), D3 (entrega = tarefa que altera código, com seus testes; checklist do pesquisador depois de cada entrega de UI); DEC-49 D1 (dependências de teste no ambiente).

**Validações técnicas**: CL-22 (runners no ambiente) em T013 (001). Nenhuma VT conta como teste aprovado.

## Format: `[ID] [P?] [Entrega] Descrição`

---

## Entrega E12 — Fechamento da qualidade transversal

**Objetivo**: evidência de execução para cada cenário da matriz.

**Verificação independente**: gate G-023 — cada cenário com teste executado com sucesso (PHPUnit/Behat), registro do pesquisador ou VT registrada, conforme a classificação abaixo.

- [x] T139 [E12] Executar as suítes completas de PHPUnit e Behat do plugin no ambiente da spec-001, medir e reportar a cobertura de linhas (sem limiar), e registrar a evidência por cenário (comando, data, versão do Moodle, resultado) em `spec-kit/specs/023-testes-e-qualidade-transversal/checklists/test-evidence.md` (depende de E0–E11) **[04/11/2026, EXECUTADA: PHPUnit completo 1588 testes, 10389 asserções, 0 falhas e 0 erros, 5 ignorados; Behat completo 22 cenários e 233 passos aprovados; cobertura de linhas 91,91%; 289 cenários na matriz: 250 com teste aprovado, 35 CL com o estado da CL (não contam como teste aprovado) e 4 em "a conferir" (008 Q16–Q18 e 017 Q13: a tarefa não cita o arquivo de teste), para decisão do responsável na T140. Texto anterior preservado: "[ ]" até esta data. Ver `spec-kit/validation/t139/run.md` e `checklists/test-evidence.md`.]** *(04/11/2026, T140: os 4 cenários "a conferir" foram ligados, por decisão do responsável, à evidência do quickstart da 008 e da T142, conferida no `phpunit-junit.xml`; a lista ficou vazia: 254 com teste aprovado e 35 CL.)*
  - Refs: plano 023 fase 4; DEC-48 D2; constituição §10
- [x] T140 [E12] Verificar o gate G-023: todo cenário da matriz abaixo tem evidência na classificação atribuída; nenhum cenário **CL** contado como teste aprovado; registros do pesquisador presentes (T130); atualizar o estado dos itens de `spec-kit/closure-register.md` somente com evidência conferida (depende de T139; a matriz inclui o cenário 015 Q14, coberto pela T141, no gate G-015) **[04/11/2026, GATE G-023 VERIFICADO: os 289 cenários da matriz têm evidência na classificação atribuída (254 PHPUnit/Behat aprovados, 35 CL com o estado da CL, lista "a conferir" vazia) pelo `generate_evidence.py` repetido sobre a evidência da T139; nenhum cenário CL contado como teste aprovado; registros do pesquisador (T130) presentes e aprovados; busca de pendências com zero (iv). Registro de encerramento em `closure-register.md`. Texto anterior preservado: "[ ]" até esta data.]** **[04/11/2026, gate refeito: depois da inclusão dos 21 cenários que faltavam, o gate G-023 foi verificado de novo com 310 cenários (274 PHPUnit/Behat aprovados e 36 CL com o estado da CL, lista "a conferir" vazia); os 21 acrescentados já tinham teste aprovado na T139. Os 289 e 254 acima ficam como histórico.]**
  - Refs: plano 023 "Fases" 4; `closure-register.md` §0 (regra de estado)

---

## Matriz completa cenário → tarefa (DEC-48 D2)

Classificação única por cenário, conforme a regra do [quickstart.md](quickstart.md): **PHPUnit**, **Behat**, **Pesquisador** ou **CL**. A coluna "Complemento" lista verificações adicionais que **não** substituem a classificação. 271 cenários *(283 com 020 Q15–Q26, 29/10/2026; 284 com 017 Q13, 04/11/2026)* *(04/11/2026, T140: contagem das linhas da matriz = **289** cenários, 245 PHPUnit (um multiprocesso), 9 Behat e 35 CL; o total anterior, 284, estava desatualizado; texto anterior preservado)*; nenhum foi executado. *(27/10/2026: 015 Q14 acrescentado, T141.)* *(04/11/2026, inclusão de 21 cenários criados depois de 26/10/2026 e que faltavam na matriz: 008 Q12–Q14, 009 Q11–Q17 e 011 Q15–Q25; a contagem passa de 289 para **310** cenários: 265 PHPUnit, 9 Behat e 36 CL; a lista de cenários agora é conferida pelo `generate_evidence.py` contra todos os quickstarts; texto anterior preservado.)*

### 001 — Ambiente (12)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 001 Q1 | CL (CL-01) | T007 | — |
| 001 Q2 | CL (CL-01) | T013 | — |
| 001 Q3 | CL (CL-01) | T007 | — |
| 001 Q4 | CL (CL-01) | T007 | — |
| 001 Q5 | CL (CL-01) | T007 | — |
| 001 Q6 | CL (CL-01) | T007 | — |
| 001 Q7 | CL (CL-01) | T007 | — |
| 001 Q8 | CL (CL-01) | T007 | — |
| 001 Q9 | CL (CL-01) | T007 | — |
| 001 Q10 | CL (CL-01) | T007 | — |
| 001 Q11 | CL (CL-01) | T007 | CL-32 em T138 |
| 001 Q12 | CL (CL-22) | T013 | — |

### 002 — Esquema (9)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 002 Q1 | PHPUnit | T012 | — |
| 002 Q2 | PHPUnit | T012 | — |
| 002 Q3 | PHPUnit | T012 | — |
| 002 Q4 | PHPUnit | T012 | — |
| 002 Q5 | PHPUnit | T012 | — |
| 002 Q6 | PHPUnit | T019 | — |
| 002 Q7 | CL (CL-07) | T020 | — |
| 002 Q8 | CL (CL-37) | T106 | — |
| 002 Q9 | PHPUnit | T016 | — |

*(02/11/2026: Q7 continua `CL (CL-07) | T020`. A matriz de validação da CL-07, com os cenários I-xx e M-xx, está em `specs/002-esquema-xmldb/quickstart.md`; nenhum foi escrito nem executado.)* *(Correção de 02/11/2026: a anotação anterior estava desatualizada. A Etapa 2 já implementou e executou parte da matriz: I-12, M-01, M-02, M-03, M-05, M-06, M-07, M-08, M-09, M-10 e M-11, em `unit_of_work_concurrency_test`, `material_concurrency_test` e `report_concurrency_test` (13 testes, 217 asserções, sem falhas, erros ou skips), e a Etapa 3 aplicou as seis correções dos testes existentes e registrou a linha de base. Continuam sem implementação I-03, I-04, I-05, I-06, I-09, I-13, I-15, os complementares I-01, I-02, I-14, I-16 e U-06, e o registro de fechamento. Q7 continua `CL (CL-07) | T020`, e a CL-07 e a T020 continuam abertas.)* *(Atualização de 02/11/2026, Etapa 4: I-03, I-04 e I-05 também foram implementados e executados, com a verificação de I-09, em `material_failure_test` (6 testes, 167 asserções, sem falhas, erros ou skips). Continuam sem implementação I-06, I-13, I-15 e os complementares.)* *(Atualização de 02/11/2026, Etapa 5: I-06, I-13 e I-15 também foram implementados e executados, com a verificação de I-09, em `material_recovery_test` (5 testes, 189 asserções, sem falhas, erros ou skips). Continuam sem implementação os complementares I-01, I-02, I-14, I-16 e U-06, e o registro de fechamento.)* *(Atualização de 02/11/2026, Etapa 6: todos os cenários obrigatórios de Q7 foram reexecutados (31 classes, 351 testes, 2400 asserções, sem falhas, erros ou skips) e os CR-01 a CR-21 foram revisados e classificados com base nas evidências disponíveis; CR-02 permanece parcialmente comprovado e os outros 20 critérios estão classificados como comprovados, observadas as ressalvas registradas no quickstart da especificação 002; a proposta de encerramento está em `specs/002-esquema-xmldb/quickstart.md`. Q7 continua `CL (CL-07) | T020`, e a CL-07 e a T020 continuam abertas, à espera da decisão do responsável.)* *(Atualização de 02/11/2026, I-02: o I-02 foi implementado e executado em `install_schema_test` (6 testes, 232 asserções, sem falhas, erros ou skips) e o CR-02 passou a comprovado, com ressalva; os CR-01 a CR-21 estão, portanto, todos classificados como comprovados, observadas as ressalvas registradas no quickstart da especificação 002, o que substitui a frase anterior sobre o CR-02. Q7 continua `CL (CL-07) | T020`, e a CL-07 e a T020 continuam abertas, à espera de autorização expressa do responsável.)* *(Atualização de 02/11/2026, encerramento: por autorização expressa do responsável, a CL-07 foi encerrada com ressalvas e a T020 foi marcada como concluída; Q7 continua `CL (CL-07) | T020`, agora concluída. Os CR-01 a CR-21 estão comprovados, observadas as ressalvas registradas no quickstart da especificação 002; a T144 permanece aberta e CR-22 e CR-23 ficam fora da CL-07. As frases anteriores desta linha que dizem que a CL-07 e a T020 estão abertas valem para a data de cada uma.)* *(08/11/2026: CL-07 foi fechado em 02/11/2026, validação técnica com ressalvas; a pendência afirmada acima não está mais aberta. Texto anterior preservado, conforme a constituição §11.)*

### 003 — Persistência (10)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 003 Q1 | PHPUnit | T018 | — |
| 003 Q2 | PHPUnit | T018 | — |
| 003 Q3 | PHPUnit | T018 | — |
| 003 Q4 | PHPUnit | T016 | — |
| 003 Q5 | PHPUnit | T015 | — |
| 003 Q6 | PHPUnit | T016 | — |
| 003 Q7 | PHPUnit | T015 | — |
| 003 Q8 | PHPUnit | T015 | T061, T096, T113 |
| 003 Q9 | PHPUnit | T018 | — |
| 003 Q10 | CL (CL-07) | T020 | — |

### 004 — Acesso aos dados (17)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 004 Q1 | PHPUnit | T024 | — |
| 004 Q2 | PHPUnit | T026 | — |
| 004 Q3 | PHPUnit | T029 | — |
| 004 Q4 | PHPUnit | T029 | — |
| 004 Q5 | PHPUnit | T021 | — |
| 004 Q6 | PHPUnit | T021 | — |
| 004 Q7 | PHPUnit | T021 | — |
| 004 Q8 | PHPUnit | T021 | — |
| 004 Q9 | PHPUnit | T021 | — |
| 004 Q10 | PHPUnit | T021 | — |
| 004 Q11 | CL (CL-16) | T030 | T099 |
| 004 Q12 | CL (CL-38) | T031 | T111 |
| 004 Q13 | CL (CL-36) | T032 | T090 |
| 004 Q14 | CL (CL-33) | T033 | T069, T075 |
| 004 Q15 | CL (CL-34) | T034 | T080 |
| 004 Q16 | CL (CL-35) | T035 | T084 |
| 004 Q17 | CL (CL-02, CL-41) | T036 | T066, T129 |

### 005 — Acesso e critérios (9)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 005 Q1 | PHPUnit | T038 | Behat T128; CL-26 T041 |
| 005 Q2 | PHPUnit | T038 | Behat T128; CL-26 T041 |
| 005 Q3 | PHPUnit | T038 | Behat T128; CL-26 T041 |
| 005 Q4 | PHPUnit | T040 | — |
| 005 Q5 | PHPUnit | T040 | T119 |
| 005 Q6 | PHPUnit | T040 | — |
| 005 Q7 | PHPUnit | T038 | CL-26 T041 |
| 005 Q8 | PHPUnit | T038 | CL-26 T041 |
| 005 Q9 | PHPUnit | T038 | CL-26 T041 |

### 006 — Eventos e fila (10)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 006 Q1 | PHPUnit | T045 | CL-02 T047 |
| 006 Q2 | PHPUnit | T045 | CL-02 T047 |
| 006 Q3 | PHPUnit | T083 | — |
| 006 Q4 | PHPUnit | T131 | — |
| 006 Q5 | PHPUnit | T042 | — |
| 006 Q6 | PHPUnit | T045 | CL-02 T047 |
| 006 Q7 | PHPUnit | T045 | — |
| 006 Q8 | PHPUnit | T083 | — |
| 006 Q9 | PHPUnit | T044 | — |
| 006 Q10 | PHPUnit | T044 | CL-02 T047 |

### 007 — Tasks e retries (12)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 007 Q1 | PHPUnit | T048 | CL-02 T051 |
| 007 Q2 | PHPUnit | T048 | CL-02 T051 |
| 007 Q3 | PHPUnit | T050 | — |
| 007 Q4 | PHPUnit | T050 | — |
| 007 Q5 | PHPUnit | T050 | — |
| 007 Q6 | PHPUnit | T050 | CL-02 T051 |
| 007 Q7 | PHPUnit | T050 | CL-02 T051 |
| 007 Q8 | PHPUnit | T050 | — |
| 007 Q9 | PHPUnit | T050 | — |
| 007 Q10 | PHPUnit | T050 | — |
| 007 Q11 | PHPUnit | T050 | CL-02 T051 |
| 007 Q12 | PHPUnit | T050 | — |

### 008 — Materiais e contexto (19) *(04/11/2026: Q12–Q14 incluídos, DEC-51, T058; o cabeçalho anterior, "11", não contava Q15–Q19, que já constavam das linhas)*

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 008 Q1 | PHPUnit | T063 | — |
| 008 Q2 | PHPUnit | T063 | — |
| 008 Q3 | PHPUnit | T064 | — |
| 008 Q4 | PHPUnit | T064 | — |
| 008 Q5 | PHPUnit | T063 | T064 |
| 008 Q6 | PHPUnit | T064 | — |
| 008 Q7 | PHPUnit | T064 | CL-36 T032 |
| 008 Q8 | PHPUnit | T065 | T060; CL-02 T066 |
| 008 Q9 | PHPUnit | T065 | T060 |
| 008 Q10 | PHPUnit | T063 | T060; CL-02 T066 |
| 008 Q11 | PHPUnit | T059 | CL-02 T066 |
| 008 Q12 | PHPUnit | T058 | — |
| 008 Q13 | PHPUnit | T058 | — |
| 008 Q14 | PHPUnit | T058 | — |
| 008 Q15 | CL (CL-07) | T020 | — |
| 008 Q16 | PHPUnit | T144 | — |
| 008 Q17 | PHPUnit (multiprocesso) | T144 | — |
| 008 Q18 | PHPUnit | T144 | — |
| 008 Q19 | PHPUnit | T064 | — |

*(02/11/2026, depois da T144: Q16, Q17 e Q18 foram executados e aprovados na T144 (`material_repository_test`, `material_monotonic_concurrency_test` e `material_service_test`), com os limites de cobertura registrados em "Evidências de Q16, Q17 e Q18" no quickstart 008; esta matriz não tem coluna de estado e a classificação das linhas não mudou. Q15 não foi alterado.)* *(02/11/2026: Q17 passou de `CL (CL-07) | T020 | T144` para `PHPUnit (multiprocesso) | T144`, por separação entre CL-07 e T144 (opção A): Q15 continua na T020 e Q16 a Q18 pertencem à T144. Os cenários I-xx e M-xx da CL-07 estão na "Matriz de validação da CL-07" de `specs/002-esquema-xmldb/quickstart.md`.)* *(01/11/2026: Q15 a Q18 incluídos por DEC-61 e DEC-62, planejados e não executados. Lacuna anterior, não tratada aqui: Q12 a Q14 do quickstart 008 (31/10/2026) também não constam desta matriz, e o título da seção continua "(11)".)*

### 009 — Porta LLM (17) *(04/11/2026: Q11–Q17 incluídos, DEC-60/DEC-70, T134 e T138; eram 10)*

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 009 Q1 | PHPUnit | T054 | — |
| 009 Q2 | PHPUnit | T053 | — |
| 009 Q3 | PHPUnit | T054 | — |
| 009 Q4 | PHPUnit | T094 | — |
| 009 Q5 | PHPUnit | T056 | CL-32 T138 (parte do Git) |
| 009 Q6 | PHPUnit | T053 | — |
| 009 Q7 | PHPUnit | T053 | — |
| 009 Q8 | PHPUnit | T054 | — |
| 009 Q9 | PHPUnit | T055 | T068, T082, T097 |
| 009 Q10 | CL (CL-32) | T138 | — |
| 009 Q11 | PHPUnit | T134 | — |
| 009 Q12 | PHPUnit | T134 | — |
| 009 Q13 | PHPUnit | T134 | — |
| 009 Q14 | PHPUnit | T134 | — |
| 009 Q15 | PHPUnit | T134 | — |
| 009 Q16 | PHPUnit | T134 | — |
| 009 Q17 | CL (CL-32) | T138 | — |

### 010 — Análise inicial (12)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 010 Q1 | PHPUnit | T068 | — |
| 010 Q2 | PHPUnit | T068 | T053 |
| 010 Q3 | PHPUnit | T068 | T064 |
| 010 Q4 | PHPUnit | T068 | T024 |
| 010 Q5 | PHPUnit | T068 | — |
| 010 Q6 | PHPUnit | T068 | — |
| 010 Q7 | PHPUnit | T068 | — |
| 010 Q8 | PHPUnit | T068 | — |
| 010 Q9 | PHPUnit | T068 | — |
| 010 Q10 | PHPUnit | T068 | — |
| 010 Q11 | PHPUnit | T068 | T055 |
| 010 Q12 | CL (CL-33) | T069 | — |

### 011 — Métricas (25) *(04/11/2026: Q15–Q25 incluídos, DEC-64 e complemento `multianswer`, T075; eram 14)*

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 011 Q1 | PHPUnit | T070 | — |
| 011 Q2 | PHPUnit | T070 | — |
| 011 Q3 | PHPUnit | T072 | — |
| 011 Q4 | PHPUnit | T094 | — |
| 011 Q5 | PHPUnit | T070 | — |
| 011 Q6 | PHPUnit | T070 | — |
| 011 Q7 | PHPUnit | T072 | — |
| 011 Q8 | PHPUnit | T070 | — |
| 011 Q9 | PHPUnit | T072 | — |
| 011 Q10 | PHPUnit | T074 | — |
| 011 Q11 | PHPUnit | T072 | — |
| 011 Q12 | PHPUnit | T072 | — |
| 011 Q13 | PHPUnit | T072 | — |
| 011 Q14 | CL (CL-33) | T075 | — |
| 011 Q15 | PHPUnit | T075 | — |
| 011 Q16 | PHPUnit | T075 | — |
| 011 Q17 | PHPUnit | T075 | — |
| 011 Q18 | PHPUnit | T075 | — |
| 011 Q19 | PHPUnit | T075 | — |
| 011 Q20 | PHPUnit | T075 | — |
| 011 Q21 | PHPUnit | T075 | — |
| 011 Q22 | PHPUnit | T075 | — |
| 011 Q23 | PHPUnit | T075 | — |
| 011 Q24 | PHPUnit | T075 | — |
| 011 Q25 | PHPUnit | T075 | — |

### 012 — Notas (11)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 012 Q1 | PHPUnit | T078 | — |
| 012 Q2 | PHPUnit | T094 | — |
| 012 Q3 | PHPUnit | T078 | — |
| 012 Q4 | PHPUnit | T076 | — |
| 012 Q5 | PHPUnit | T076 | — |
| 012 Q6 | PHPUnit | T076 | — |
| 012 Q7 | PHPUnit | T078 | — |
| 012 Q8 | PHPUnit | T078 | — |
| 012 Q9 | PHPUnit | T078 | — |
| 012 Q10 | PHPUnit | T094 | — |
| 012 Q11 | CL (CL-34) | T080 | — |

### 013 — Fóruns (13)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 013 Q1 | PHPUnit | T082 | T083 |
| 013 Q2 | PHPUnit | T082 | — |
| 013 Q3 | PHPUnit | T082 | — |
| 013 Q4 | PHPUnit | T082 | — |
| 013 Q5 | PHPUnit | T082 | — |
| 013 Q6 | PHPUnit | T082 | — |
| 013 Q7 | PHPUnit | T082 | — |
| 013 Q8 | PHPUnit | T082 | — |
| 013 Q9 | PHPUnit | T082 | — |
| 013 Q10 | PHPUnit | T082 | — |
| 013 Q11 | PHPUnit | T082 | — |
| 013 Q12 | PHPUnit | T082 | — |
| 013 Q13 | CL (CL-35) | T084 | — |

### 014 — Frequência (14)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 014 Q1 | PHPUnit | T085 | — |
| 014 Q2 | PHPUnit | T085 | — |
| 014 Q3 | PHPUnit | T087 | — |
| 014 Q4 | PHPUnit | T086 | — |
| 014 Q5 | PHPUnit | T085 | — |
| 014 Q6 | PHPUnit | T085 | — |
| 014 Q7 | PHPUnit | T085 | — |
| 014 Q8 | PHPUnit | T085 | — |
| 014 Q9 | PHPUnit | T085 | — |
| 014 Q10 | PHPUnit | T085 | — |
| 014 Q11 | PHPUnit | T088 | — |
| 014 Q12 | PHPUnit | T087 | — |
| 014 Q13 | PHPUnit | T085 | — |
| 014 Q14 | CL (CL-36) | T090 | — |

### 015 — Critérios (14)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 015 Q1 | PHPUnit | T091 | — |
| 015 Q2 | PHPUnit | T091 | — |
| 015 Q3 | PHPUnit | T094 | — |
| 015 Q4 | PHPUnit | T094 | — |
| 015 Q5 | PHPUnit | T091 | — |
| 015 Q6 | PHPUnit | T091 | — |
| 015 Q7 | PHPUnit | T091 | — |
| 015 Q8 | PHPUnit | T091 | — |
| 015 Q9 | PHPUnit | T091 | — |
| 015 Q10 | PHPUnit | T091 | — |
| 015 Q11 | PHPUnit | T091 | — |
| 015 Q12 | PHPUnit | T091 | — |
| 015 Q13 | PHPUnit | T093 | — |
| 015 Q14 | PHPUnit | T141 | — |

### 016 — Síntese (14)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 016 Q1 | PHPUnit | T097 | — |
| 016 Q2 | PHPUnit | T097 | — |
| 016 Q3 | PHPUnit | T097 | — |
| 016 Q4 | PHPUnit | T096 | — |
| 016 Q5 | PHPUnit | T095 | — |
| 016 Q6 | PHPUnit | T097 | — |
| 016 Q7 | PHPUnit | T095 | — |
| 016 Q8 | PHPUnit | T097 | — |
| 016 Q9 | PHPUnit | T095 | — |
| 016 Q10 | PHPUnit | T098 | — |
| 016 Q11 | PHPUnit | T097 | — |
| 016 Q12 | PHPUnit | T097 | CL-16 T099 |
| 016 Q13 | PHPUnit | T097 | — |
| 016 Q14 | PHPUnit | T097 | Behat T128 (exibição escapada) |

Validação técnica do plano 016 (sem cenário numerado): CL-16 e CL-07 em T099.

### 017 — Histórico e privacidade (13) *(04/11/2026, revisão da propagação: Q13 acrescentado, DEC-58, T142; eram 12)*

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 017 Q1 | PHPUnit | T101 | — |
| 017 Q2 | PHPUnit | T101 | — |
| 017 Q3 | PHPUnit | T102 | — |
| 017 Q4 | PHPUnit | T101 | — |
| 017 Q5 | PHPUnit | T114 | — |
| 017 Q6 | PHPUnit | T102 | — |
| 017 Q7 | PHPUnit | T104 | — |
| 017 Q8 | PHPUnit | T104 | — |
| 017 Q9 | PHPUnit | T104 | Q-017-07 resolvida por DEC-56 (T100) |
| 017 Q10 | PHPUnit | T103 | — |
| 017 Q11 | PHPUnit | T105 | — |
| 017 Q12 | CL (CL-37) | T106 | — |
| 017 Q13 | PHPUnit | T142 | — |

*(04/11/2026, revisão da propagação, DEC-58: cenário 017 Q13 incluído; a validação do mesmo cenário no Moodle real está na CL-37, F1.)*

*(DEC-59, proposta de organização do registro, data de aprovação confirmada pelo responsável em 03/11/2026 (texto anterior, preservado: "data de aprovação [A CONFIRMAR]"). Cenários de corrida e de exclusão da reentrega, **sem ID de cenário nem de tarefa novos**, porque a matriz não os define; a tarefa de origem está descrita em 017/tasks.md, sem ID novo. Classificação: PHPUnit. `student_redelivery_erasure_test` (exclusão do aluno e do curso, aluno com somente tarefas de reentrega, esgotadas incluídas, atomicidade e ordem de remoção), `redelivery_task_repository_test`, `redeliver_event_test`, `event_service_deferral_test`, `redelivery_observers_test`, `unit_of_work_test`, `event_service_concurrency_test` e `redelivery_erasure_race_test` (processos independentes: a exclusão do curso primeiro, a tarefa primeiro, cinco rodadas do triângulo e o observer do curso com lock ocupado). **Ressalvas:** sinais de lock (`IS_USED_LOCK` e `PROCESSLIST`, estado `User lock`) validados somente no MariaDB 11.4.13; o autoteste dos sinais pode virar skip e um skip não conta como prova; não está provada a espera por linha do banco nem o estado interno de transação; nas cinco rodadas do triângulo o resultado foi sempre `task first`, e a ordem em que a exclusão do aluno vence não foi observada; a janela de 60 s contra 30 s não tem teste; o esgotamento da tarefa é simulado (`attemptsavailable` = 0), não produzido pelo cron; `recording_lock_factory` não trata aquisição repetida do mesmo recurso. Verificação informada pelo responsável: suíte completa do plugin com 1.335 testes, 7.110 asserções, 0 falhas, 5 skipped e 4 notices.)*

### 018 — Consultas (13)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 018 Q1 | PHPUnit | T109 | — |
| 018 Q2 | PHPUnit | T109 | — |
| 018 Q3 | PHPUnit | T109 | T110 |
| 018 Q4 | PHPUnit | T110 | Behat T128 |
| 018 Q5 | PHPUnit | T108 | Behat T128 |
| 018 Q6 | PHPUnit | T110 | T108 |
| 018 Q7 | PHPUnit | T108 | Behat T128 |
| 018 Q8 | PHPUnit | T108 | — |
| 018 Q9 | PHPUnit | T110 | — |
| 018 Q10 | PHPUnit | T107 | — |
| 018 Q11 | PHPUnit | T107 | — |
| 018 Q12 | PHPUnit | T110 | — |
| 018 Q13 | CL (CL-38) | T111 | — |

### 019 — Relatórios (16)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 019 Q1 | PHPUnit | T112 | T116 |
| 019 Q2 | PHPUnit | T112 | — |
| 019 Q3 | PHPUnit | T112 | — |
| 019 Q4 | PHPUnit | T115 | — |
| 019 Q5 | PHPUnit | T112 | — |
| 019 Q6 | PHPUnit | T112 | — |
| 019 Q7 | PHPUnit | T116 | — |
| 019 Q8 | PHPUnit | T112 | — |
| 019 Q9 | PHPUnit | T114 | — |
| 019 Q10 | PHPUnit | T115 | — |
| 019 Q11 | PHPUnit | T115 | — |
| 019 Q12 | PHPUnit | T115 | — |
| 019 Q13 | PHPUnit | T112 | — |
| 019 Q14 | PHPUnit | T112 | T116 |
| 019 Q15 | PHPUnit | T116 | — |
| 019 Q16 | CL (CL-39) | T117 | — |

### 020 — Formulários (26) *(29/10/2026: Q15–Q26 acrescentados, Q-020-02, T143; eram 14)*

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 020 Q1 | PHPUnit | T119 | — |
| 020 Q2 | PHPUnit | T119 | — |
| 020 Q3 | PHPUnit | T119 | — |
| 020 Q4 | PHPUnit | T119 | — |
| 020 Q5 | PHPUnit | T119 | — |
| 020 Q6 | PHPUnit | T119 | — |
| 020 Q7 | PHPUnit | T119 | Behat T128 |
| 020 Q8 | PHPUnit | T119 | T093 |
| 020 Q9 | PHPUnit | T121 | Behat T128 |
| 020 Q10 | PHPUnit | T121 | — |
| 020 Q11 | PHPUnit | T121 | — |
| 020 Q12 | PHPUnit | T121 | — |
| 020 Q13 | PHPUnit | T121 | — |
| 020 Q14 | CL (CL-40) | T122 | — |
| 020 Q15 | PHPUnit | T143 | — |
| 020 Q16 | PHPUnit | T143 | — |
| 020 Q17 | PHPUnit | T143 | — |
| 020 Q18 | PHPUnit | T143 | — |
| 020 Q19 | PHPUnit | T143 | — |
| 020 Q20 | PHPUnit | T143 | Behat T143 |
| 020 Q21 | PHPUnit | T143 | Behat T143 |
| 020 Q22 | PHPUnit | T143 | Behat T143 |
| 020 Q23 | PHPUnit | T143 | Behat T143 |
| 020 Q24 | PHPUnit | T143 | — |
| 020 Q25 | PHPUnit | T143 | — |
| 020 Q26 | PHPUnit | T143 | — |

### 021 — UI (14)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 021 Q1 | Behat | T128 | T124; Pesquisador T130 |
| 021 Q2 | Behat | T128 | T125; Pesquisador T130 |
| 021 Q3 | PHPUnit | T125 | Pesquisador T130 |
| 021 Q4 | Behat | T128 | Pesquisador T130 |
| 021 Q5 | Behat | T128 | Pesquisador T130 |
| 021 Q6 | Behat | T128 | Pesquisador T130 |
| 021 Q7 | PHPUnit | T125 | Pesquisador T130 |
| 021 Q8 | PHPUnit | T125 | Pesquisador T130 |
| 021 Q9 | Behat | T128 | T125; Pesquisador T130 |
| 021 Q10 | Behat | T128 | Pesquisador T130 |
| 021 Q11 | Behat | T128 | T127; Pesquisador T130 |
| 021 Q12 | PHPUnit | T125 | T097; Pesquisador T130 |
| 021 Q13 | Behat | T128 | T107; Pesquisador T130 |
| 021 Q14 | CL (CL-41) | T129 | — |

### 022 — Eventos/hooks (10)

| Cenário | Classificação | Tarefa | Complemento |
|---|---|---|---|
| 022 Q1 | PHPUnit | T131 | — |
| 022 Q2 | PHPUnit | T131 | — |
| 022 Q3 | PHPUnit | T131 | — |
| 022 Q4 | PHPUnit | T131 | T044 |
| 022 Q5 | PHPUnit | T131 | T044 |
| 022 Q6 | PHPUnit | T131 | T108 |
| 022 Q7 | PHPUnit | T131 | T108 |
| 022 Q8 | PHPUnit | T131 | T074 |
| 022 Q9 | PHPUnit | T131 | — |
| 022 Q10 | CL (CL-02, 33–37, 40, 41) | T132 | — |

**Totais**: 284 cenários *(04/11/2026, revisão da propagação: 283 mais 017 Q13; texto anterior: "283 cenários *(29/10/2026: 271 mais 12 de 020 Q15–Q26)* — 240 PHPUnit")* — 241 PHPUnit, 9 Behat, 34 CL e nenhum com "Pesquisador" como classificação única (a checklist do pesquisador complementa os cenários Q1–Q13 do 021). *(04/11/2026, T140: corrigido para **289** cenários, pela contagem das linhas da matriz: 245 PHPUnit (um multiprocesso), 9 Behat e 35 CL; o texto anterior, "284 cenários … 241 PHPUnit, 9 Behat, 34 CL", fica como histórico.)* *(04/11/2026, inclusão de 21 cenários: **310** cenários — 265 PHPUnit, 9 Behat e 36 CL (o 009 Q17 é CL, CL-32); os 289 anteriores ficam como histórico.)*

---

## Correspondência AT → tarefa

| AT | Tarefas |
|---|---|
| AT-001 | T002–T008 |
| AT-002 | T004, T007 |
| AT-003 | T002, T007, T008, T009 |
| AT-004 | T001, T004, T013 |
| AT-005 | T004, T006, T007 |
| AT-006 | T002–T008 |
| AT-007 | T011, T012 |
| AT-008 | T011, T012, T020 |
| AT-009 | T011, T016, T019 |
| AT-010 | T015, T016, T018 |
| AT-011 | T018, T123 |
| AT-012 | T021–T029 |
| AT-013 | T029, T030–T036 |
| AT-014 | T024, T026 |
| AT-015 | T037, T038, T041, T128 |
| AT-016 | T039, T040, T119, T121 |
| AT-017 | T039, T040, T119 |
| AT-018 | T040, T119 |
| AT-019 | T040, T092, T093, T119 |
| AT-020 | T042, T044 |
| AT-021 | T042, T043 |
| AT-022 | T042 |
| AT-023 | T045, T046, T047 |
| AT-024 | T083 |
| AT-025 | T046, T131 |
| AT-026 | T048, T050 |
| AT-027 | T042, T050 |
| AT-028 | T048, T051 |
| AT-029 | T050 |
| AT-030 | T050, T054 |
| AT-031 | T044, T050, T051 |
| AT-032 | T059, T062, T135 |
| AT-033 | T063 |
| AT-034 | T063, T065 |
| AT-035 | T060, T061, T063 |
| AT-036 | T061, T063, T144 |
| AT-037 | T064 |
| AT-038 | T064, T068, T082 |
| AT-039 | T064, T101 |
| AT-040 | T064 |
| AT-041 | T063, T064 |
| AT-042 | T064, T101 |
| AT-043 | T052 |
| AT-044 | T053, T054, T134 |
| AT-045 | T056, T133 |
| AT-046 | T063, T068, T082, T097 |
| AT-047 | T094 |
| AT-048 | T004, T056, T134, T138 |
| AT-049 | T053 |
| AT-050 | T068, T069 |
| AT-051 | T053, T068 |
| AT-052 | T070, T072 |
| AT-053 | T070, T072, T074 |
| AT-054 | T070 |
| AT-055 | T076, T078 |
| AT-056 | T081, T082 |
| AT-057 | T082 |
| AT-058 | T085, T087 |
| AT-059 | T085, T088 |
| AT-060 | T091, T093, T141 |
| AT-061 | T095–T098 |
| AT-062 | T101, T114 |
| AT-063 | T061, T102, T103, T104 |
| AT-064 | T107–T110, T124 |
| AT-065 | T112–T117 |
| AT-066 | T119, T125, T128, T139 |

---

## Pendências que permanecem explícitas

| Pendência | Natureza | Tratamento nas tasks |
|---|---|---|
| CL-24 / Q-001-08 | Origem `assistente/` inexistente | Gate T001, condição de entrada de todo código |
| CL-19 | Fornecedor, modelo, contrato e retenção | Gate T133, só para E11 (T134–T138) |
| Q-008-05 | Valores de `summary_status` | Gate T057, antes de T061 e T063 |
| Q-008-07 | Semântica de `availability` | Gate T058, antes de T061 e T064 |
| Q-008-03 | Extração de PDF e YouTube | Referência de conteúdo em T062; extração em T135 (depende de CL-19) |
| Q-017-07 | Dados identificáveis em textos preservados | Gate T100 (resolvido por DEC-56), antes de T104 |
| Q-011-07 | Tipos com múltiplos itens corretos | VT T075 (CL-33) |
| CL-01, 02, 07, 16, 22, 26, 32–41 | Validações técnicas | Tarefas VT, sem resultado antecipado |
| R-010-01, R-011-01, R-012-01, R-013-01, R-013-02, R-014-01, R-016-01, R-019-01, R-020-01, R-006-01 | Riscos | Medidos ou verificados nas tarefas indicadas em cada `tasks.md` |
