# Revisão assistida da propagação (DEC-01 a DEC-71) — etapa 5a, TAREFA 2

**Data:** 04/11/2026
**Estado:** decisões do responsável de 04/11/2026 registradas na seção 4 e aplicadas na TAREFA 3 *(estado anterior, preservado: "folha para decisão do responsável. Nenhum `spec.md` foi alterado nesta tarefa" — vale para a TAREFA 2)*.
**Método:** (1) `grep -rn 'DEC-[0-9]' specs/*/spec.md` (114 trechos em 21 `spec.md`); cada trecho foi lido no arquivo real, com o contexto, e comparado com o texto vigente da decisão em `decision-register-DEC-01-25.md`, considerando as decisões que a alteraram (DEC-34 → DEC-69; DEC-60 → DEC-70 e DEC-71; T136 → DEC-65). A leitura foi dividida em três blocos (specs 001–006, 007–014 e 015–023) e as divergências apontadas foram **reconferidas por mim no arquivo**; os trechos conformes foram verificados por amostra (não um a um). (2) Para as 63 linhas da tabela §3 de `closure-register.md`: conferência mecânica de que a marca `DEC-nn` existe no `spec.md` (ou na pasta da spec, quando a §3 diz "no plano") citado em "Propagada em".
**Classes:** Conforme; Regra superada sem nota; Faltando (a §3 diz que foi propagada, mas o conteúdo não existe); Contradição (dois trechos incompatíveis).

## 1. Resumo

### 1.1 Trechos de `spec.md` com marca de DEC

| Classe | Trechos |
|---|---|
| Conforme | 108 |
| Regra superada sem nota | 6 (005:26, 011:35, 014:29, 015:25, 016:29, 016:49) |
| Faltando | 0 |
| Contradição | 0 (011:35 também contradiz 011:34; classificado como regra superada) |
| **Total classificado** | **114** |

### 1.2 Linhas da tabela §3 de `closure-register.md`

| Resultado | Linhas |
|---|---|
| Conferidas | 63 (todas) |
| Marca ou conteúdo presente onde a §3 diz | 61 |
| Faltando | 2 (DEC-55, DEC-58) |

As linhas da §3 que dizem "no plano" (DEC-30 a DEC-62, em geral) não prometem texto no `spec.md`; foi conferido que a DEC aparece na pasta da spec indicada. Na linha da DEC-64, o mecanismo automático acusou "052" e "054", que são AT-052 e AT-054 (existem em `requirements-traceability.md`, linhas 62 e 64): sem divergência.

## 2. Divergências (somente os não conformes)

| # | spec:linha | trecho atual | decisão vigente | problema | correção proposta |
|---|---|---|---|---|---|
| 1 | 005:26 (DEC-11) | "A semântica do critério binário de fórum continua em aberto (`closure-register.md`, CL-05)." A parte numérica (>=, <=) está correta. | DEC-40 (25/10/2026): fórum binário (D3) decidido; CL-05 Fechado. | Regra superada sem nota: a frase "continua em aberto" foi superada e não há nota de vigência. | Acrescentar nota: semântica do binário de fórum definida pela DEC-40 (D3); CL-05 fechada em 25/10/2026 (plano 015). Preservar o texto anterior. |
| 2 | 011:35 (DEC-01, DEC-24, P-06) | "Fração × percentual e arredondamento continuam em aberto (plano 011, Q-011-02)." | DEC-36 D1 e D2 (25/10/2026): percentual 0 a 100, truncado a 3 casas, exibido a 2. O 011:34 diz "resolve a Q-011-02". | Regra superada sem nota; contradiz 011:34 no mesmo spec. | Substituir a frase por remissão à DEC-36 D1 e D2, preservando o texto antigo como "texto anterior". |
| 3 | 014:29 (DEC-01, DEC-24, P-06) | "O arredondamento continua em aberto (plano 014, Q-014-05)." | DEC-39: percentual armazenado truncado a 3 casas e exibido truncado a 2 (como DEC-36 D2 e DEC-37 D2). | Regra superada sem nota. | Substituir por remissão à DEC-39 e à DEC-36 D2, com o texto antigo como "texto anterior". |
| 4 | 015:25 (DEC-11) | "A unidade de comparação do critério de atividade (Q-015-02) e o critério binário de fórum (Q-015-03, CL-05) continuam em aberto." | DEC-40 (25/10/2026): percentual 0–100; atividade = total final do curso; fórum binário por postagens; CL-05 e CL-12 Fechados. | Regra superada sem nota. | Substituir por remissão à DEC-40, com "Alterado em <data> por DEC-40; texto substituído: ...". |
| 5 | 016:29 (DEC-19) | "A normalização e o mecanismo de comparação ainda não estão especificados (CL-03)." | DEC-41 D2 (25/10/2026): fingerprint SHA-256 sobre JSON canônico; CL-03 Fechado no nível funcional. A regra da DEC-19 em si segue vigente. | Regra superada sem nota (só a oração final). | Manter a regra da DEC-19 e acrescentar nota remetendo à DEC-41 D2 e ao plano 016. |
| 6 | 016:49 (DEC-14, DEC-19, DEC-29) | "...a normalização e o algoritmo continuam em aberto (`closure-register.md`, CL-03)." A regra de versão (`max(version)+1`, unicidade, sem indicador de corrente) está conforme. | DEC-41 D2: algoritmo SHA-256 canônico definido; CL-03 Fechado no nível funcional. | Regra superada sem nota (só a oração final). | Mesma correção do item 5. |
| 7 | §3, DEC-55: "006 (catálogo de eventos), 015 (tarefas T093), 022 (encadeamentos internos)" | A §3 diz propagada ao catálogo de eventos da 006 e aos encadeamentos internos da 022. | DEC-55 (27/10/2026): pedidos por estudante a partir das análises individuais e um pedido por curso para `reference_date_reached`. | Faltando: não há `DEC-55` nem `reference_date_reached` nas pastas `specs/006-*` e `specs/022-*`. Existe só em `specs/015-*/tasks.md` e `quickstart.md`. | Duas opções para o responsável: (a) propagar a regra ao catálogo da 006 e aos encadeamentos da 022; (b) corrigir a §3 para "015 (tarefas, quickstart)", com o texto anterior preservado. |
| 8 | §3, DEC-58: "017 (data-model, quickstart Q13, tarefa T142), 023 (matriz)" | A §3 diz propagada à matriz da 023. | DEC-58 (28/10/2026): nova tentativa por tarefa ad hoc na exclusão que falha por lock; exceção ao DPO; três eventos de auditoria. | Faltando: `DEC-58`, `T142` e o conteúdo não existem em `specs/023-*`. Existe em `specs/017-*/tasks.md` e `quickstart.md`. | Duas opções: (a) acrescentar o cenário à matriz da 023; (b) corrigir a §3 para "017", preservando o texto anterior. |

### 2.1 Observação fora da contagem (trecho sem marca de DEC)

Achado durante a leitura; não entra nos 114 porque o trecho não cita DEC-nn. Proposto para decisão:

| spec:linha | trecho atual | decisão vigente | problema | correção proposta |
|---|---|---|---|---|
| 019:30 | "Fuso horário, reabertura de datas depois da atualização final e recuperação de falha da atualização final continuam em aberto (AT-065, `closure-register.md`, CL-06)." | DEC-44 (26/10/2026): fuso do site e 00:00 do dia seguinte, reabertura; CL-06 Fechado no nível funcional. A recuperação de falha da atualização final **não** consta como decidida na DEC-44. | Regra superada sem nota quanto a fuso e reabertura. A recuperação de falha continua sem decisão que eu tenha encontrado. | Nota remetendo à DEC-44 para fuso e reabertura; manter a recuperação de falha como em aberto. |

## 3. Trechos conformes (para aprovação em bloco)

Cada item é `spec:linha (DEC citadas)`. Total: 108.

001:11 (DEC-27); 001:12 (DEC-27); 001:27 (DEC-69); 001:28 (DEC-69); 001:29 (DEC-27); 001:36 (DEC-27); 001:40 (DEC-69); 002:31 (DEC-29); 002:133 (DEC-51); 002:134 (DEC-29/DEC-20); 002:141 (DEC-29/DEC-20); 002:150 (DEC-29 (via CL-45)); 002:153 (DEC-29); 002:154 (DEC-51); 002:161 (DEC-29); 002:310 (DEC-29/DEC-14); 002:311 (DEC-29/DEC-14); 002:312 (DEC-29/DEC-14); 002:314 (DEC-29/DEC-19); 002:319 (DEC-29/DEC-14); 002:320 (DEC-29/DEC-14); 002:322 (DEC-29/DEC-14); 002:329 (DEC-29/DEC-07/DEC-23); 002:330 (DEC-29/DEC-07/DEC-23); 002:331 (DEC-29/DEC-07/DEC-23); 002:332 (DEC-29/DEC-23); 002:333 (DEC-29/DEC-23); 002:337 (DEC-29/DEC-23); 002:348 (DEC-29); 002:354 (DEC-29); 004:33 (DEC-13); 005:21 (DEC-30); 006:32 (DEC-31); 006:33 (DEC-08); 006:34 (DEC-10/DEC-21); 006:35 (DEC-16/DEC-22); 006:36 (DEC-32 D4); 006:37 (DEC-03); 007:24 (DEC-32); 007:27 (DEC-32 (D2, D3, D5, D6), DEC-59); 007:28 (DEC-03, DEC-04); 007:29 (DEC-09, P-04); 007:30 (DEC-32 (D3, D4)); 007:33 (DEC-08); 007:34 (DEC-21); 007:35 (DEC-16); 007:36 (DEC-22, P-04); 008:23 (DEC-33 D2); 008:24 (DEC-05); 008:25 (DEC-26); 008:26 (DEC-26); 008:27 (DEC-26); 008:28 ((DEC-26 na nota)); 008:30 (DEC-33 D3, DEC-26); 008:33 (DEC-12, DEC-15, DEC-29); 008:34 (DEC-20, DEC-29); 008:35 (DEC-62, DEC-33, DEC-61); 008:37 (DEC-13); 009:40 (DEC-69, Constituição 1.2.0); 010:33 (DEC-31); 010:34 (DEC-13); 011:34 (DEC-36, DEC-52, DEC-64); 013:25 (DEC-03); 013:28 (DEC-13); 014:31 (DEC-25); 015:29 (DEC-25); 015:31 (DEC-02); 016:23 (DEC-13); 016:28 (DEC-03, DEC-04); 016:30 (DEC-13); 017:23 (DEC-05, DEC-26); 017:25 (DEC-12, DEC-15); 017:26 (DEC-04, DEC-13); 017:27 (DEC-14, DEC-20, DEC-29); 017:28 (DEC-33 (D3)); 017:29 (DEC-08, DEC-16, DEC-22); 018:27 (DEC-02); 019:22 (DEC-06); 019:25 (DEC-06); 019:26 (DEC-07, DEC-29); 019:27 (DEC-23, DEC-29); 019:28 (DEC-17); 019:29 (DEC-18); 019:32 (DEC-24); 019:33 (DEC-02); 020:24 (DEC-11); 021:28 (DEC-24); 021:29 (DEC-02); 022:26 (DEC-10, DEC-21); 022:27 (DEC-08); 022:28 (DEC-31); 022:29 (DEC-16, DEC-22); 022:31 (DEC-03); 023:27 (DEC-09); 023:28 (DEC-32); 023:30 (DEC-08, DEC-10, DEC-16, DEC-21, DEC-22); 023:32 (DEC-12, DEC-15); 023:33 (DEC-14, DEC-20, DEC-23, DEC-29); 023:34 (DEC-03, DEC-04, DEC-13, DEC-19); 023:36 (DEC-05, DEC-26); 023:37 (DEC-33); 023:40 (DEC-11); 023:41 (DEC-02); 023:42 (DEC-01, DEC-24); 023:43 (DEC-25); 023:44 (DEC-31); 023:47 (DEC-06, DEC-07, DEC-17, DEC-18); 023:48 (DEC-23, DEC-29).

Observações sobre conformes (sem divergência): 004:33, 008:37, 010:34 e 013:28 citam "DEC-13 e decisões relacionadas, conforme aplicável" (citação genérica, conteúdo compatível com a DEC-13); 007:36 pede a validação do backend de lock "antes da implementação" e a T051 já a cumpriu (a decisão não mudou).

## 4. Decisão do responsável (04/11/2026) e aplicação (TAREFA 3)

**Aprovação em bloco dos conformes:** os 108 trechos conformes (seção 3) foram aprovados pelo responsável em 04/11/2026.

| # | spec:linha | Decisão | Aplicada como | Onde |
|---|---|---|---|---|
| 1 | 005:26 | Aprovada | Nota "(04/11/2026, revisão da propagação: ...)" citando a DEC-40 (D3) e a CL-05 fechada; texto anterior citado e preservado | `specs/005-*/spec.md` |
| 2 | 011:35 | Aprovada | Nota citando a DEC-36 D1/D2 (e a DEC-39 para a exibição); Q-011-02 resolvida; texto anterior preservado | `specs/011-*/spec.md` |
| 3 | 014:29 | Aprovada | Nota citando a DEC-39 e a DEC-36 D2; Q-014-05 resolvida; texto anterior preservado | `specs/014-*/spec.md` |
| 4 | 015:25 | Aprovada | Nota citando a DEC-40 (D1, D2, D3); CL-05 e CL-12 fechadas; texto anterior preservado | `specs/015-*/spec.md` |
| 5 | 016:29 | Aprovada | Nota citando a DEC-41 (D2 e tratamentos confirmados: SHA-256 sobre JSON canônico); texto anterior preservado | `specs/016-*/spec.md` |
| 6 | 016:49 | Aprovada | Mesma nota do item 5 para o `input_fingerprint`; texto anterior preservado | `specs/016-*/spec.md` |
| 7 | 019:30 (observação fora da contagem) | Aprovada, ajustada pelo responsável: fuso e reabertura pela DEC-44 (D2 e D3); recuperação de falha da atualização final pela DEC-17, como a CL-06 já indica | Nota com as duas decisões; texto anterior preservado | `specs/019-*/spec.md` |
| 8 | §3, DEC-58 (Faltando) | Aprovada: propagar. Conferidos antes de gravar o quickstart 017 (Q13: "DEC-58; T142") e a T142 (testes PHPUnit `erasure_retry_test` e outros; validação no Moodle real em CL-37): sem divergência de classificação ou de tarefa | Linha "017 Q13 \| PHPUnit \| T142 \| —" na matriz, título da seção 017 (13; eram 12) e totais da matriz (284; eram 283; PHPUnit 241; eram 240), com nota | `specs/023-*/tasks.md` |
| 9 | §3, DEC-55 (Faltando) | Aprovada: propagar à 022; a parte da 006 fica coberta na TAREFA 4(c) (contrato de eventos) | Nota na linha "Encadeamentos internos" (`criteria_check_requested` e `course_criteria_check_requested`, conferidos em `event_type.php`) | `specs/022-*/plan.md` (linha 108) |

**Tabela §3 de `closure-register.md`:** as 56 decisões em "Aguardando revisão" passaram a "Revisado pelo responsável em 04/11/2026", com o texto anterior preservado; nenhuma divergência foi rejeitada ou deixada pendente, portanto nenhuma DEC permaneceu em "Aguardando revisão". Não alteradas por não estarem em "Aguardando revisão": DEC-19 (Bloqueado, CL-03), DEC-55, DEC-56, DEC-57, DEC-58, DEC-59 e DEC-62. As observações de DEC-01, DEC-11, DEC-55 e DEC-58 receberam nota.

**Limite da revisão:** para as decisões propagadas apenas aos planos e a outros artefatos (linhas "no plano" da §3), a folha conferiu a presença da decisão na pasta da spec, não o conteúdo.
