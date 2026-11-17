# Quickstart — Matriz de validação (spec-023)

> Planejado, **não executado**. Os comandos dependem de Q-023-01 e Q-001-xx. *(26/10/2026: Q-023-01 resolvida por DEC-48 — PHPUnit e Behat do Moodle; comandos e ambiente dependem do plano 001 e de CL-22.)*

## Regra de rastreabilidade *(incluída em 26/10/2026, DEC-48 D2)*

Cada cenário de cada `quickstart.md` recebe, nas tasks, exatamente uma destas classificações (podendo ter mais de um teste):

| Classificação | Significado | Conta como teste aprovado? |
|---|---|---|
| **PHPUnit** | teste automatizado unitário, de integração, persistência, eventos/tarefas, privacidade ou regras | sim, quando executado com sucesso e com evidência |
| **Behat** | teste automatizado de fluxo de interface | sim, quando executado com sucesso e com evidência |
| **Pesquisador** | verificação de usabilidade pela checklist do plano 021 (DEC-48 D3) | não é teste automatizado; registro próprio |
| **CL** | validação técnica no Moodle 5.2 (CL-xx) | **não**; nunca contabilizada como teste aprovado |

A cobertura de linhas é medida e reportada, sem ser critério de aprovação.

## Matriz por plano *(reconstruída em 26/10/2026)*

| Plano | Cenários | Tipo principal | Cenários de validação técnica (CL) | Situação do plano | Tarefas *(26/10/2026)* |
|---|---|---|---|---|---|
| 001 Ambiente | Q1–Q6; Q7–Q12 | CL | Q7–Q11 (CL-01); Q12 (CL-22) | aprovado *(26/10/2026; antes: "Q1–Q6 \| CL \| ambiente (CL-01) \| pendente de revisão — provisório")* | T001–T008, T013 |
| 002 Esquema | Q1–Q6; Q9; Q7–Q8 | PHPUnit (persistência/XMLDB) | Q7 (CL-07); Q8 (CL-37) | aprovado *(26/10/2026; antes: "Q1–Q6 \| PHPUnit (persistência/XMLDB) \| XMLDB (CL-07) \| pendente de revisão — provisório")* | T009, T011, T012, T019, T020 |
| 003 Persistência | Q1–Q9; Q10 | PHPUnit (unitário, integração) | Q10 (CL-07) | aprovado *(26/10/2026; antes: "Q1–Q5 \| PHPUnit (unitário, integração) \| — \| pendente de revisão — provisório")* | T010, T014–T018 |
| 004 Acesso aos dados | Q1–Q10; Q11–Q17 | PHPUnit (integração) | Q11 (CL-16); Q12 (CL-38); Q13 (CL-36); Q14 (CL-33); Q15 (CL-34); Q16 (CL-35); Q17 (CL-02, CL-41) | aprovado *(26/10/2026; antes: "Q1–Q10 \| PHPUnit (integração) \| APIs dos readers (Q-004-01) \| pendente de revisão — provisório")* | T021–T036 |
| 005 Acesso e critérios | Q1–Q9 | PHPUnit (acesso); Behat (acesso) | CL-26 | aprovado | T037–T041 |
| 006 Eventos e fila | Q1–Q10 | PHPUnit (eventos) | CL-02 | aprovado | T042–T047 |
| 007 Tasks e retries | Q1–Q12 | PHPUnit (tarefas) | CL-02 | aprovado | T048–T051 |
| 008 Materiais e contexto | Q1–Q11 | PHPUnit (integração, persistência) | CL-02 | aprovado | T057–T066 |
| 009 Porta LLM | Q1–Q9; Q10 | PHPUnit (adaptador simulado) | Q10 (CL-32) | aprovado | T052–T056 (E4); T133–T138 (E11) |
| 010 Análise inicial | Q1–Q11; Q12 | PHPUnit (LLM simulada) | Q12 (CL-33) | aprovado | T067–T069 |
| 011 Métricas | Q1–Q13; Q14 | PHPUnit (regras) | Q14 (CL-33) | aprovado | T070–T075 |
| 012 Notas | Q1–Q10; Q11 | PHPUnit (regras, integração) | Q11 (CL-34) | aprovado | T076–T080 |
| 013 Fóruns | Q1–Q12; Q13 | PHPUnit (LLM simulada) | Q13 (CL-35) | aprovado | T081–T084 |
| 014 Frequência | Q1–Q13; Q14 | PHPUnit (regras) | Q14 (CL-36) | aprovado | T085–T090 |
| 015 Critérios | Q1–Q13 | PHPUnit (regras) | — | aprovado | T091–T094 |
| 016 Síntese | Q1–Q14 | PHPUnit (LLM simulada, persistência) | CL-16, CL-07 | aprovado | T095–T099 |
| 017 Histórico e privacidade | Q1–Q11; Q12 | PHPUnit (histórico, privacidade) | Q12 (CL-37) | aprovado | T100–T106 |
| 018 Consultas | Q1–Q12; Q13 | PHPUnit (acesso, regras); Behat (filtros por grupo) | Q13 (CL-38) | aprovado | T107–T111 |
| 019 Relatórios | Q1–Q15; Q16 | PHPUnit (tarefas, relógio simulado) | Q16 (CL-39) | aprovado | T112–T117 |
| 020 Formulários | Q1–Q13; Q14 | PHPUnit (validação, acesso); Behat (permissões, curso desabilitado) | Q14 (CL-40) | aprovado | T119–T122 |
| 021 UI | Q1–Q13; Q14 | Behat (acesso, escape, grupos); Pesquisador (checklist) | Q14 (CL-41) | aprovado | T118, T123–T130 |
| 022 Eventos/hooks | Q1–Q9; Q10 | PHPUnit (eventos) | Q10 (CL-02, 33–37, 40, 41) | aprovado | T131, T132 |

## Ênfases da spec-023 e decisões *(mantidas e ampliadas em 26/10/2026)*

| Ênfase | Tipo | Specs cobertas | Cenários de referência |
|---|---|---|---|
| Máximo de 3 tentativas totais, incluindo a inicial (DEC-09) *(alterado em 25/10/2026; texto original: "Máximo de 3 retries")* | eventos/tasks | 007 | 007/quickstart Q4 |
| Duplicidade, ocorrências legítimas, concorrência e recuperação (DEC-08, 16, 21, 22) | eventos/tasks, integração | 006, 007, 017, 022 | a definir nas tasks *(26/10/2026: também 022 Q5)* |
| Publicação única de questionário: criado visível, tornado visível, ocultar/mostrar, edição posterior, duplicidade e concorrência (DEC-31) | eventos/tasks | 006, 010, 022 | 006 Q1, Q2, Q6, Q7, Q9, Q10 |
| Versão corrente única de material e transição atômica (DEC-12, 15) | persistência, histórico | 008, 017 | a definir nas tasks |
| Síntese sem versão redundante e isolada por seção (DEC-03, 04, 13, 19) | integração, LLM mockada | 016, 017 | a definir nas tasks; normalização em CL-03 *(26/10/2026: 016 Q2, Q5, Q8, Q9, Q13; CL-03 fechado no nível funcional por DEC-41)* |
| Elegibilidade de material para o contexto (DEC-05, 26) | integração | 008, 017 | a definir nas tasks; API em Q-004-01 |
| Critérios no limite e imediatamente acima e abaixo (DEC-11) | regras determinísticas | 005, 015 | a definir nas tasks *(26/10/2026: 015 Q5)* |
| Desempate alfabético (DEC-02) | regras determinísticas, UI | 015, 018, 019, 021 | a definir nas tasks *(26/10/2026: 021 Q13)* |
| Zero com `no_data`, zero `valid` e `error` (DEC-01, 24) | regras determinísticas, UI | 011, 014, 019, 021 | a definir nas tasks *(26/10/2026: 011 Q13; 012 Q7; 014 Q13; 021 Q7)* |
| Agendamento de relatório: sem data final, mudança de data, coincidência, falha (DEC-06, 07, 17, 18) | eventos/tasks | 019 | a definir nas tasks *(26/10/2026: 019 Q5–Q8)* |
| Referência `current_version` (material, relatório), unicidade da síntese por curso, seção e versão, despachante de relatórios (DEC-14, 20, 23, 29) | persistência, eventos/tasks | 002, 008, 016, 019 | a definir nas tasks; validação XMLDB em CL-07 |
| Idempotência | eventos/tasks, integração | 006, 007 | 006 Q2; 007 Q5–Q6 |
| Versionamento | persistência, histórico | 008, 017, 019 | 008 Q2; 017 Q2; 019 Q1 |
| Isolamento por seção | integração | 008, 010, 013 | 008 Q4; 010 Q3; 013 Q4 |
| Escopo Professor/Admin | acesso | 005, 018, 019, 021 | 005 Q1–Q3; 018 Q1–Q2 |
| Cálculos | regras determinísticas | 011, 012, 014, 015 | 011 Q1–Q2; 014 Q1; 015 Q1 |
| Falha da API LLM | LLM mockada | 009, 010, 013, 016 | 009 Q2–Q3; 016 Q3 |
| Atualização final de relatório | eventos/tasks | 019 | 019 Q2–Q3 |
| Esquema | persistência/XMLDB | 002 | 002 Q1–Q5 |
| Ambiente | — | 001 | 001 Q1–Q6 |
| UI | UI | 020, 021 | 020 Q1–Q3; 021 Q1–Q4 |
| *(26/10/2026)* Porta LLM, validação de saída e privacidade dos dados enviados (DEC-34) | PHPUnit (LLM simulada) | 009, 010, 013, 016 | 009 Q1–Q9; 010 Q11; 013 Q11; 016 Q6 |
| *(26/10/2026)* Análise inicial e "sem materiais" (DEC-35) | PHPUnit | 010, 018, 021 | 010 Q5–Q10; 018 Q9; 021 Q8 |
| *(26/10/2026)* Métricas de questionário, truncamento e versões sem linha analisada (DEC-36) | PHPUnit (regras) | 011 | 011 Q5–Q13 |
| *(26/10/2026)* Notas em percentual e total do curso (DEC-37) | PHPUnit (regras) | 012, 015 | 012 Q4–Q10; 015 Q5–Q6 |
| *(26/10/2026)* Fórum: snapshot, substituição, edição e exclusão (DEC-38) | PHPUnit (LLM simulada) | 013, 017 | 013 Q5–Q12; 017 Q6 |
| *(26/10/2026)* Frequência: elegibilidade, datas e acesso (DEC-39) | PHPUnit (regras) | 014, 015 | 014 Q5–Q13; 015 Q11–Q12 |
| *(26/10/2026)* Aplicação dos critérios e fórum binário (DEC-40) | PHPUnit (regras) | 015 | 015 Q5–Q13 |
| *(26/10/2026)* Síntese: agregados, fingerprint e gatilhos (DEC-41) | PHPUnit | 016 | 016 Q4–Q14 |
| *(26/10/2026)* Privacidade, exclusão de curso e fonte removida (DEC-42) | PHPUnit (privacidade); CL | 017, 018, 019 | 017 Q5–Q11; 018 Q12; 019 Q12 |
| *(26/10/2026)* Turma = grupo e estudantes visíveis (DEC-43) | PHPUnit; Behat | 018, 019, 021 | 018 Q5–Q8; 019 Q11; 021 Q10 |
| *(26/10/2026)* Relatório: manifesto, fuso e reabertura (DEC-44) | PHPUnit (relógio simulado) | 019 | 019 Q9–Q15 |
| *(26/10/2026)* Permissão, imutabilidade, habilitação e sincronização (DEC-45) | PHPUnit; Behat | 020, 021, 022 | 020 Q5–Q13; 021 Q5–Q6; 022 Q4 |
| *(26/10/2026)* Idioma do conteúdo gerado e escape (DEC-41, 46) | PHPUnit; Behat | 021 | 021 Q9, Q11, Q12 |
| *(26/10/2026)* Sem gatilho de matrícula; filtro na leitura (DEC-47) | PHPUnit | 022, 018 | 022 Q6–Q7, Q9 |

Usabilidade: verificação pelo pesquisador, sem professores/estudantes [REQ §Regras]. Protocolo: Q-023-03. *(26/10/2026: Q-023-03 resolvida por DEC-48 D3 — checklist do plano 021 depois de cada entrega que afete a UI, com data, versão do Moodle, cenários e resultados.)*

## Cobertura das specs 003, 004 e 022

A matriz acima é organizada por ênfase de §Regras e não lista as specs 003, 004 e 022. A spec-023 valida as specs 001 a 022 (§Dependências). Os cenários dos quickstarts dessas specs são referenciados abaixo sem atribuí-los a uma ênfase. *(26/10/2026: a "Matriz por plano" acima passou a listar todas as specs, inclusive 003, 004 e 022.)*

| Spec | Cenários de referência | Fonte dos cenários |
|---|---|---|
| 003 | 003 Q1–Q5 *(26/10/2026: o quickstart aprovado tem Q1–Q10)* | `specs/003-api-interna-de-persist-ncia-e-lib-php/quickstart.md` |
| 004 | 004 Q1–Q4 | `specs/004-acesso-aos-dados-moodle/quickstart.md` *(26/10/2026: o quickstart atual tem Q1–Q10)* *(26/10/2026, plano 004 aprovado: Q1–Q17, sendo Q11–Q17 validação técnica)* |
| 022 | 022 Q1–Q3 | `specs/022-integra-o-events-hooks/quickstart.md` *(26/10/2026: o quickstart atual tem Q1–Q10)* |

*(Incluído em 26/10/2026.)* Com os 23 planos aprovados, nenhum cenário desta matriz é provisório. A classificação única de cada um dos 271 cenários e a tarefa que o cobre estão em [tasks.md](tasks.md) ("Matriz completa cenário → tarefa"); a coluna "Tarefas" da matriz por plano indica o intervalo de tarefas de cada feature.
