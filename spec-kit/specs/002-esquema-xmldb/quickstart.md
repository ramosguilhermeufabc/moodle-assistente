# Quickstart (validação) — spec-002

> Nada foi executado nesta fase. Os comandos de instalação e teste dependem de Q-001-xx e Q-023-01 (NEEDS CLARIFICATION) (04/11/2026: resolvido: Q-001-xx por DEC-28 e DEC-49 e Q-023-01 por DEC-48; ambiente e runners executados em CL-01 (Fechado em 31/10/2026) e T013 [x]). *(26/10/2026: ambiente por DEC-49; runners PHPUnit e Behat por DEC-48. Q7–Q9 são validação técnica, CL-07 e CL-37.)*

## Pré-requisitos
- Ambiente spec-001 funcional (Gate G-001).
- Q-002-01 resolvida (inventário conferido).

## Cenários
| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Instalar o plugin num Moodle 5.2 limpo | Existem exatamente as 21 tabelas `local_assistente_*` | §Objetivo |
| Q2 | Comparar campos, tipos, `LENGTH`, `DECIMALS`, `NOTNULL` e `SEQUENCE` com o inventário | Igualdade campo a campo | §Inventário, §Regras |
| Q3 | Comparar chaves e índices únicos | Únicos exatamente onde definidos, nenhum a mais | §Regras |
| Q4 | Inserir duplicata em índice único (ex.: `material(course_id,cmid)`) via DML | Rejeitada | §Regras |
| Q5 | Procurar campos ou índices fora do inventário | Nenhum | §Regras |
| Q6 | Simular mudança de esquema | Exige XMLDB + mecanismo de upgrade | §Regras *(26/10/2026: depois da primeira instalação, `db/upgrade.php` com savepoint)* |
| Q7 | Validação técnica (CL-07): instalar o `install.xml` com as chaves circulares `material` ↔ `material_version` e `report` ↔ `report_version`; no relatório, gravar uma versão nova com troca de `current_version` numa única transação; no material, gravar a versão (`pending`), concluir o resumo e trocar `current_version` em três etapas independentes, cada uma com lock e transação *(alterado em 01/11/2026; texto original: "... e gravar uma versão nova com troca de `current_version` numa transação")* | A verificar, sem resultado antecipado: a instalação aceita as chaves circulares; a troca do ponteiro é atômica em cada caso; no material, a falha de qualquer etapa preserva a versão corrente anterior e uma versão `completed` pode ficar não corrente; registrar comandos, saídas e divergências em CL-07 | DEC-29; DEC-61 |
| Q8 | Validação técnica (CL-37): remover no Moodle um `course_modules` referenciado por `material` e por `performance` | A verificar, sem resultado antecipado: se o banco não impõe a chave (sem cascata nem bloqueio) e se a ausência é detectável na leitura | DEC-42 D3 |
| Q9 | Inserir valores fora dos domínios definidos (`analysis_type` desconhecido, `*_status` = 3) pela camada de persistência | Rejeitados pela validação da aplicação (spec-003), já que o XMLDB não restringe valores | Q-002-03, Q-002-04 |

*(Q7–Q9 incluídos em 26/10/2026.)*

## Matriz de validação da CL-07 *(congelada em 02/11/2026; o estado da execução está em "Estado da validação após a Etapa 2", ao fim desta seção)*

Documento canônico da matriz da CL-07: não há outro. A tarefa é a T020 (`tasks.md`); o registro de fechamento é `closure-register.md`, CL-07. *(Texto do congelamento, em 02/11/2026: "planejada, nada foi executado" e "Os testes aqui listados **não existem ainda** e só serão escritos em etapa posterior, com autorização". Isso descrevia o momento do congelamento. Depois dele, a Etapa 2 implementou e executou parte dos cenários; a situação atual de cada um está em "Estado da validação após a Etapa 2". Os critérios objetivos desta matriz não mudaram, salvo onde há uma anotação "texto original".)*

**Ambiente de referência:** Moodle 5.2, MariaDB 11.4.13, Lock Factory efetivamente usada (a premissa é verificada por I-12, não presumida). Outros bancos, outras fábricas de lock e vários servidores não são generalizados a partir deste ambiente. Todos os cenários rodam no PHPUnit, nas tabelas de teste, sem tocar o site.

### Escopo

| Escopo | O que contém |
|---|---|
| **CL-07 / T020** | CR-01 a CR-21: persistência, transações, integridade, locks, concorrência real, recuperação, repetição segura, falhas de gravação e evidência de execução |
| **T144** (008, adiada *— situação na data do congelamento da matriz, 02/11/2026; a T144 foi concluída depois, ver a nota ao fim desta linha*) | CR-22 e CR-23: monotonicidade de `current_version` (DEC-62): U-07 a U-12, M-12, M-13, S-01 e Q17. **Não bloqueia o fechamento da CL-07** *(02/11/2026, depois do encerramento da CL-07: a T144 foi implementada e testada fora da CL-07; M-12, M-13 e Q17 foram executados, e U-07 a U-12 e S-01 não foram implementados com esses nomes. Ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)* |
| **Fora** | CL-49 (`update_details`; M-04, U-02, U-03) e CL-37 (semântica de exclusão) |

### Critérios

| CR | Critério | Situação após a Etapa 6 (02/11/2026) | Cenário que fecha |
|---|---|---|---|
| 01 | Instalação do XMLDB no Moodle 5.2 | Comprovado *(evidência histórica de 29/10/2026; ver "Evidências históricas de CR-01 e CR-02")* | I-16 (complementar) |
| 02 | Pares circulares aceitos na instalação | **Comprovado**, com ressalva *(I-02 executado e consultas diretas ao banco, 02/11/2026; histórico: Comprovado na auditoria, rebaixado a Parcialmente comprovado na revisão documental de 02/11/2026 por falta de evidência direta, e restabelecido agora com evidência direta; ver "Estado do I-02 e avaliação de encerramento")* | I-02 (complementar, executado em 02/11/2026) |
| 03 | Integridade do ponteiro (aplicação) | **Comprovado** (nível da aplicação) *(Etapa 6, 02/11/2026; antes: Parcialmente comprovado)* | I-09 |
| 04 | Compatibilidade com o Moodle 5.2 | Comprovado | — |
| 05 | Unicidade de versão no banco | Comprovado | M-03 (complementar) |
| 06 | Relatório: versão, ponteiro e agenda atômicos | Comprovado | exige não-skip do teste existente |
| 07 | Material: falha de banco em cada etapa | **Comprovado** *(Etapa 6, 02/11/2026; antes: Parcialmente comprovado)* | I-03, I-04, I-05 |
| 08 | Síntese sob lock e transação | Comprovado | — |
| 09 | Lock Factory efetiva | **Comprovado** (forma forte, neste ambiente) *(Etapa 6, 02/11/2026; antes: Parcialmente comprovado (só dedução por configuração e código; sem observação))* | I-12, forma forte |
| 10 | Nomes dos recursos de lock | Comprovado | — |
| 11 | Aquisição e liberação | **Comprovado** *(Etapa 6, 02/11/2026; antes: Parcialmente comprovado)* | M-06 |
| 12 | Tempo limite do lock | **Comprovado**, com ressalva *(Etapa 6, 02/11/2026; antes: Parcialmente comprovado)* | M-07 |
| 13 | Lock após a morte do processo | **Comprovado** *(Etapa 6, 02/11/2026; antes: Não comprovado)* | M-08 |
| 14 | Concorrência real no mesmo material | **Comprovado**, com ressalva *(Etapa 6, 02/11/2026; antes: Não comprovado)* | M-01, M-02 |
| 15 | Concorrência real no mesmo relatório | **Comprovado**, com ressalva *(Etapa 6, 02/11/2026; antes: Parcialmente comprovado)* | M-05 |
| 16 | Recursos diferentes sem bloqueio indevido | **Comprovado** *(Etapa 6, 02/11/2026; antes: Não comprovado)* | M-11 |
| 17 | Versões da síntese sob concorrência | Comprovado | — |
| 18 | Morte do processo com transação aberta | **Comprovado** *(Etapa 6, 02/11/2026; antes: Não comprovado)* | M-09 |
| 19 | Recuperação e repetição | **Comprovado**, com ressalva *(Etapa 6, 02/11/2026; antes: Parcialmente comprovado)* | I-06, I-13, I-15 |
| 20 | Perda de conexão pelo servidor | **Comprovado**, com ressalva *(Etapa 6, 02/11/2026; antes: Não comprovado)* | M-10, condicionado ao privilégio `KILL` |
| 21 | Execução efetiva, sem skip | **Comprovado** (neste ambiente, com ressalva) *(Etapa 6, 02/11/2026; antes: Inconclusivo)* | execução com `--display-skipped` |
| 22 | Monotonicidade no repositório (DEC-62) | **Comprovado, com ressalva** *(T144, 02/11/2026; antes: Não comprovado, pendente da T144)* | Evidências diretas do repositório: Q16 e teste por número em `material_repository_test`; evidência complementar, da camada de serviço: Q18 em `material_service_test` (U-07 a U-12 não foram implementados com esses nomes) |
| 23 | Monotonicidade sob concorrência (DEC-62) | **Comprovado, com ressalva** *(T144, 02/11/2026; antes: Não comprovado, pendente da T144)* | M-12, M-13, Q17 em `material_monotonic_concurrency_test` |

*(Texto original do cabeçalho da coluna da situação: "Situação em 02/11/2026". Na Etapa 6 a coluna passou a mostrar a classificação depois da reexecução final; a classificação anterior de cada linha reclassificada está ao lado, entre parênteses. As linhas sem anotação (CR-01, 04, 05, 06, 08, 10 e 17) mantêm a classificação anterior; CR-02 foi rebaixado de Comprovado a Parcialmente comprovado na revisão documental de 02/11/2026 e restabelecido como Comprovado, com ressalva, depois da execução do I-02 (anotação na própria linha); CR-22 e CR-23 não foram reclassificados *(afirmação válida na data da Etapa 6 e do I-02; reclassificados depois pela T144, em 02/11/2026: ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento)*. *(Texto original deste trecho: "As linhas sem anotação (CR-01, 02, 04, 05, 06, 08, 10 e 17) mantêm a classificação anterior".)* A evidência concreta e a ressalva de cada CR estão em "Estado da Etapa 6".)*

### A. Obrigatórios da CL-07

| ID | Categoria | Critério objetivo de aprovação | CR |
|---|---|---|---|
| I-03 | Integração (falha de banco) | Falha injetada na troca: linhas do material e das versões idênticas às de antes; `current_version` anterior; versão `completed`; sem transação aberta; lock livre; a exceção traz o marcador da falha injetada; sem a falha injetada, a mesma troca funciona | 07, 19 |
| I-04 | Integração (falha de banco) | Falha em `add_version`: nenhuma linha nova; o número não é consumido (a tentativa seguinte recebe o mesmo) | 07 |
| I-05 | Integração (falha de banco) | Falha ao passar para `completed`, no repositório: versão `pending`, sem resumo, ponteiro anterior | 07 |
| I-06 | Recuperação (cenário C) | Troca falhou depois de `completed`; nova execução do serviço: ponteiro na versão, **nenhuma chamada à LLM a mais**, nenhum número novo | 19 |
| I-13 | Recuperação (cenário A2) | Versão `pending` deixada para trás (processo morto ou exceção do extrator); nova execução: a mesma versão, contagem de versões inalterada, **uma** chamada à LLM, `completed` e corrente | 19 |
| I-15 | Recuperação (cenário B) | Falha ao persistir o resumo; o serviço deixa a versão `failed`, sem resumo; nova execução: a mesma versão, **duas chamadas à LLM no total**, `completed` e corrente. Valida a limitação aceita, não uma garantia de idempotência | 19 |
| I-09 | Integração (invariante) | Em todo cenário: `current_version` nulo, ou apontando para versão `completed` **do mesmo material** | 03 |
| I-12 | Integração | A classe que o `lock_config` escolhe é `mysql_lock_factory`; a fábrica real em uso também é, **por trás** do `timing_wrapper_lock_factory` quando ele existe (`get_real_factory()`), e o wrapper só é aceito se `MDL_PERF` ou `perfdebug > 7` o justifica; `supports_timeout` e `supports_auto_release` valem; ao adquirir um lock pela fábrica do plugin, `IS_USED_LOCK` da chave esperada devolve a conexão do próprio teste e a chave de outro recurso fica livre. Um segundo teste, com `perfdebug = 8`, exige o wrapper e passa pelas mesmas verificações. *(Texto original: "Ao adquirir um lock pela fábrica do plugin, `IS_USED_LOCK` da chave esperada devolve a conexão do próprio teste; a classe é `mysql_lock_factory` (desembrulhando o `timing_wrapper_lock_factory`)")* | 09 |
| M-01 | Multiprocesso | 4 processos × 8 `add_version` no mesmo material: 32 versões novas, números contíguos, sem repetição, sem exceção de duplicata | 14 |
| M-02 | Multiprocesso | Ciclo completo concorrente (criar, concluir, trocar): I-09 vale ao final; versões contíguas | 14 |
| M-05 | Multiprocesso | 4 × 8 `add_version` do relatório: 32 versões contíguas; `current_version` na maior | 15 |
| M-06 | Multiprocesso | Enquanto a unidade de trabalho de outro processo está aberta, o lock pertence à conexão dele, a escrita não é visível e este processo não consegue adquiri-lo; depois do commit e depois do rollback, **com a conexão do worker ainda viva**, o lock está livre e outro processo o adquire; a linha existe (commit) ou não (rollback). *(Texto original: "Depois de commit e depois de rollback num processo, outro processo adquire o lock")* | 11 |
| M-07 | Multiprocesso | Lock de `material_<id>` segurado por outro processo: `lock_unavailable_exception` depois do tempo limite configurado; nada escrito; depois da liberação a operação funciona | 12 |
| M-08 | Multiprocesso | `SIGKILL` do processo que segura o lock: outro processo o adquire | 13 |
| M-09 | Recuperação | `SIGKILL` com transação aberta e escrita não confirmada: nenhuma linha parcial, lock livre, próxima gravação funciona | 18 |
| M-11 | Multiprocesso | Com `material_a` segurado, uma operação em `material_b` e outra em `report_x` concluem; no mesmo recurso, espera | 16 |
| M-10 | Recuperação (condicionado) | `KILL` da conexão de um worker com unidade em curso: falha de conexão no worker, sem estado parcial, retentativa funciona | 20 |
| Correções | Testes existentes | Correções 1 a 6 da tabela "Correções dos testes existentes" (seis correções, onze métodos) | 07, 11, 12, 15, 19 |

### B. Complementares (não bloqueiam automaticamente)

I-01 (índices e ausência de constraints físicas; sem fixar exclusão); I-02 (só a ordem de gravação do par circular, **sem** fixar a semântica de exclusão de versões, que é da CL-37; **implementado e executado em 02/11/2026** em `install_schema_test::test_circular_pairs_can_be_written_in_order`, ver "Estado do I-02 e avaliação de encerramento"); I-14 (versão `completed` superada permanece histórica e nunca vira corrente); I-16 (esquema físico contra `install.xml`); M-03 (implementado e executado na Etapa 2; controle sem lock com **barreira determinística**: na primeira rodada os quatro processos leem `MAX(version)` e calculam a mesma versão candidata, a barreira só é liberada depois de todos chegarem a esse ponto, e o resultado esperado e observado é **uma inserção aceita e três recusadas por duplicidade** pelo índice único de (material, versão); as rodadas seguintes, sem barreira, não têm resultado fixo. *Texto original: "controle positivo: sem lock, o índice único recusa"*); U-06 (falha ao liberar o lock não mascara a exceção original).

### C. Adiados para a T144 (fora da CL-07 e da suíte principal até a T144)

*(02/11/2026: seção executada na T144, com os limites de "Evidências da T144 sobre CR-22 e CR-23"; M-12, M-13 e Q17 foram executados; U-07 a U-12 e S-01 não foram implementados com esses identificadores.)* U-07 a U-12 (monotonicidade no repositório: ponteiro nulo, avanço, número igual, número menor, comparação por número e não por `id`, consulta histórica); M-12 e M-13 (concorrência da monotonicidade, definida por invariantes, sem exigir tipo de exceção); S-01 (tratamento da rejeição no serviço, sem contrato definido); Q17 da 008. Não entram na suíte principal antes da implementação da T144, porque falhariam.

### D. Fora do escopo

CL-49 (M-04, U-02, U-03) e CL-37. Dispensáveis: U-05; as versões dos cenários no site (V-02 a V-07). O reinício do MariaDB não é requisito: `SIGKILL` (M-08, M-09) e `KILL` (M-10) cobrem o que o plugin controla; a recuperação de queda do InnoDB é do servidor e vira ressalva.

### Correções dos testes existentes (obrigatórias)

São **seis correções**, que alcançam **onze métodos** de teste já existentes. A identificação vem da lista "Testes a corrigir" da auditoria da CL-07 (itens 1 a 6), com os nomes conferidos nos arquivos de teste em 02/11/2026. As correções são documentais ou de rótulo, exceto a 1, que muda a forma de segurar o lock. **Situação (02/11/2026): as seis foram aplicadas na Etapa 3 e as classes foram reexecutadas; ver "Estado da Etapa 3" ao fim desta seção.** *(Texto original: "**Nenhuma foi feita**: dependem da etapa de implementação dos testes, com autorização.")* Nas correções 2 a 5 os nomes dos métodos foram mantidos (a matriz os cita) e o rótulo exibido pelo PHPUnit (`--testdox`) passou a ser dado por `#[TestDox]`.

| Corr. | Teste (arquivo `assistente/tests/…`::método) | Problema identificado na auditoria | Correção exigida | Critério objetivo de correção validada |
|---|---|---|---|---|
| 1 | `infrastructure/persistence/report_repository_test.php::test_version_is_not_written_without_the_lock_of_the_report` | Segura o lock **no mesmo processo**: a recusa vem do mapa interno da fábrica, não do GET_LOCK, e o ramo `markTestSkipped` é uma saída silenciosa | Segurar o lock por **processo real** (`hold_resource`), e o teste esperar `lock_unavailable_exception` | Executado **sem skip**; `lock_unavailable_exception` com o lock segurado por outro processo; nenhuma versão escrita |
| 2 | `infrastructure/persistence/unit_of_work_test.php::test_lock_is_exclusive_and_released_after_commit_and_rollback` e `::test_run_locked_is_excluded_by_a_held_lock_and_refuses_open_transactions` | Verificam a lógica do plugin **no processo**; o nome diz "exclusivo", mas a exclusão no banco não é provada | Ajustar docblock e rótulo para declarar o escopo; a exclusão no banco passa a ser de M-06 e M-07 | Docblock declara "lógica no processo"; nenhum CR cita estes métodos como prova de exclusão no banco; M-06 e M-07 executados sem skip |
| 3 | `application/material/material_service_test.php::test_failed_persistence_keeps_the_state_and_does_not_stop_the_processing` e `::test_real_lock_failure_on_the_material_keeps_the_state` | O primeiro usa subclasse que lança `RuntimeException` (dublê, não falha de banco); o segundo, apesar de "real", segura o lock no mesmo processo | **Reclassificar**: não servem de evidência de CR-07 nem de CR-12 | A matriz e o registro de execução não os citam como evidência de CR-07 nem de CR-12; rótulo ou docblock indica "falha simulada" e "lock no processo" |
| 4 | `infrastructure/persistence/material_repository_test.php::test_failed_write_leaves_the_status_unchanged` e `::test_failed_switch_keeps_the_previous_version` | Lançam **antes de qualquer escrita** (transição inválida; material ou versão inexistente ou de outro material); o nome sugere falha de gravação | Renomear ou comentar | Nome ou docblock indica falha lógica antes da escrita; não são citados como evidência de falha de gravação (CR-07 depende de I-03 a I-05) |
| 5 | `db/install_schema_test.php::test_field_counts_and_totals_match_inventory`, `::test_unique_indexes_exactly_where_defined` e `::test_no_fields_or_indexes_outside_inventory` | Comparam o **XML** com o inventário; não consultam o esquema físico (a T012 diz "campos, tipos… iguais ao inventário") | Documentar que comparam o XML; o esquema físico é de I-16 | Docblock declara "XML contra inventário"; a T012 e este quickstart remetem a I-16 para o esquema físico |
| 6 | `infrastructure/persistence/report_repository_test.php::test_failure_midway_keeps_the_last_valid_version_and_schedule` | Pula (`markTestSkipped`) se o usuário não puder criar trigger; um skip passaria despercebido | Exigir, no registro de execução, que **não foi pulado** | O teste deve ser executado sem skip, apresentar resultado aprovado no relatório PHPUnit e não aparecer na relação de testes pulados exibida por `--display-skipped` |

### Regras de aprovação

- **Skips.** Nos obrigatórios: zero falhas, zero erros e **zero skips**; a saída completa com `--display-skipped` é registrada. Um obrigatório pulado fica pendente e não é aprovado. Complementar pulado é registrado e não bloqueia.
- **M-10.** Se o ambiente permitir `KILL`, executar. Se não permitir, CR-20 permanece **não comprovado**, com a limitação explicada, e os demais testes continuam válidos. O encerramento com CR-20 não comprovado depende de autorização expressa. Ausência de privilégio não é aprovação.
- **Falhas injetadas (I-03 a I-06 e I-15).** A confirmação estática pedida antes foi feita (01/11/2026): `$DB->execute()` do driver do Moodle recusa qualquer SQL que contenha `;`, então um trigger de **corpo composto** (`BEGIN … ; … END`, `IF … ; END IF`) não pode ser criado por esse caminho, e o Moodle não deve ser contornado (nem `change_database_structure()` nem o handle bruto). Decisão do responsável: **(a) I-03 a I-06:** `SIGNAL SQLSTATE '45000'` como **instrução única**, sem corpo composto e sem `;`; por não poder ser condicionado por linha ou transição, é **incondicional**, e a restrição passa a ser **temporal**: o estado é preparado antes pelo repositório, o trigger é criado imediatamente antes da operação que deve falhar (na tabela do teste, onde essa operação é a única escrita da janela) e removido logo depois. **(b) I-15:** a falha deve atingir só a passagem para `completed`, e o serviço ainda precisa conseguir marcar a versão como `failed`; por isso usa **expressão condicional sem `;`** que viola `NOT NULL` (como o teste existente do relatório), o que depende de `sql_mode` estrito (o ambiente tem `STRICT_TRANS_TABLES`); o teste verifica essa condição. Essa estratégia **não** é a de I-03 a I-06, e a falha de I-15 não tem marcador próprio (identifica-se pelo erro de coluna e pelo estado). **Marcador do `SIGNAL`:** próprio e sem as palavras `duplicate` nem `unique constraint` (o repositório converteria a falha em `duplicate_record_exception`) e sem `:`, `?` nem `$` seguido de dígito (o analisador de parâmetros do Moodle os trata como parâmetros). Remoção em `finally`, e também em `setUp` e `tearDown`, de qualquer trigger de teste sobrado; nenhum trigger pode contaminar outro teste. **Obrigatórios não passam por `skip`:** se o trigger não puder ser criado ou o `sql_mode` não for estrito, o teste **falha** e o critério permanece pendente. *(Texto original: "`SIGNAL SQLSTATE '45000'` em trigger, abordagem recomendada, condicionada à confirmação estática de que o mecanismo de execução do Moodle aceita o corpo composto. Marcador próprio, sem as palavras `duplicate` nem `unique constraint` (...). Condição restrita ao material e à transição do teste. (...) Se o trigger não puder ser criado, o teste é pulado e conta como pendente." O corpo composto não é aceito; a condição por linha ficou como restrição temporal; e o skip foi substituído por falha.)*
- **Prova de ausência de escrita** (para o futuro U-09 e U-10 *— "futuro" na data do congelamento da matriz, 02/11/2026; a T144 foi concluída depois, sem que U-09 e U-10 tenham sido implementados com esses identificadores*): por estado (ponteiro, linha do material, linha da versão alvo e demais versões iguais às de antes); contador de escritas só como reforço.
- **Fixtures.** `queue_worker.php` e `lock_observer.php` foram **estendidos e corrigidos na Etapa 2**, incluindo C1 (encerramento limitado dos processos, com estados `running`, `ended` e `unknown`, conferência do `PROCESSLIST` em três estados e falha de limpeza levantada num hook `#[After]` que não pula o reset do Moodle), C2 (inicialização com falha rápida e diagnóstico) e C3 (barreira determinística do M-03). Ver "Estado da validação após a Etapa 2". *(Texto original, de antes da Etapa 2: "A extensão de `queue_worker.php` e `lock_observer.php` está autorizada para a etapa de implementação dos testes, e não foi feita.")*

### Critérios de fechamento

- **CL-07 pode ser proposta para fechamento** quando: todos os obrigatórios foram executados, com zero falhas, erros e skips; a saída com `--display-skipped` está registrada; CR-09 está comprovado por I-12 na forma forte; a invariante I-09 vale; ambiente, comandos, resultados e divergências estão registrados; as ressalvas estão documentadas (CR-22 e CR-23, CL-49, CL-37, isolamento transacional, recuperação de queda do InnoDB, reinicialização do MariaDB, vários servidores, outras fábricas de lock e outros bancos, cada uma explicitada em "Ressalvas de ambiente"); se M-10 não pôde ser executado, CR-20 permanece não comprovado e o fechamento depende de autorização expressa.
- **T020** representa este escopo. Só pode ser marcada depois de esses critérios serem atendidos e com autorização expressa. Q17 não pertence a ela. *(Marcada como concluída em 02/11/2026, depois de os critérios serem atendidos e com autorização expressa do responsável; ver "Encerramento formal da CL-07 e conclusão da T020".)*
- **T144** permanece aberta e independente *(afirmação do congelamento da matriz, anterior à execução da T144)*. *(02/11/2026, depois do encerramento: a T144 foi implementada e testada; ver "Evidências da T144 sobre CR-22 e CR-23". Isso não altera o fechamento da CL-07 nem a T020.)*

### Ressalvas de ambiente (não são testes obrigatórios)

Cada ressalva é declarada **separadamente** no registro de fechamento da CL-07. **Nenhuma tem evidência de execução hoje** e nenhuma é dada como comprovada. Nenhuma vira teste obrigatório sem autorização expressa; a coluna "Se vier a ser exigida" é só uma indicação do que seria preciso, não uma tarefa.

| Ressalva | O que não está coberto | Evidência existente e seu limite | Se vier a ser exigida |
|---|---|---|---|
| **Recuperação de queda do InnoDB (crash recovery)** | O desfazimento das transações não confirmadas e a consistência dos dados na partida depois da queda do **próprio servidor** | Nenhuma. M-08, M-09 e M-10 provocam a perda de conexão com o servidor **em funcionamento**; não provam a recuperação na partida. É propriedade do servidor, não do plugin | Validação operacional em ambiente descartável: interromper o servidor com transação aberta e conferir o estado na partida |
| **Reinicialização do MariaDB** | Encerramento simultâneo de todas as sessões, indisponibilidade temporária, reconexão do Moodle e perda de todos os `GET_LOCK` de uma vez | Nenhuma. V-07 foi dispensado; M-10 simula o encerramento de **uma** conexão iniciado pelo servidor, não um reinício | Validação operacional com reinício controlado do serviço, em janela autorizada e ambiente descartável |
| **Isolamento transacional** | Níveis diferentes do de referência. A correção de `MAX(version)+1` e da leitura do ponteiro dentro da transação supõe que o lock é adquirido antes da primeira leitura da transação (inferência, não testada) | O ambiente de referência usa `REPEATABLE-READ`, lido na auditoria de 01/11/2026 (`@@transaction_isolation`); isso é uma leitura de configuração, não um teste | Repetir os cenários de concorrência com outro nível configurado |
| **Vários servidores** | Processos de **servidores web diferentes** ligados ao mesmo banco, réplicas e balanceadores | Todos os cenários usam processos da mesma máquina e o mesmo MariaDB. `GET_LOCK` é do servidor de banco, então servidores web distintos o compartilhariam (inferência, não testada) | Execução com dois servidores web contra o mesmo banco |
| **Outras fábricas de lock** | `file_lock_factory`, `db_record_lock_factory`, `postgres_lock_factory`, com tempo limite, liberação automática e reentrada diferentes | I-12 identifica a fábrica efetiva do ambiente; não valida as outras | Reexecução com `$CFG->lock_factory` configurada para cada fábrica |
| **Outros bancos suportados** | PostgreSQL, SQL Server e Oracle | Nenhuma. Os cenários com `SIGNAL` e `KILL` são específicos de MariaDB/MySQL. Nenhum resultado do MariaDB é generalizado | Ambiente com cada banco, e adaptação dos cenários |

### Limitações e riscos independentes (não são requisitos de fechamento da CL-07)

Cenário B (a chamada à LLM se repete; não há garantia de idempotência): limitação aceita, registrada em `closure-register.md` como CL-52. Versão `pending` obsoleta quando o conteúdo muda: CL-50. Falta de reconciliação depois de esgotar as tentativas de um evento: CL-51. Nenhum deles foi incorporado à T144.

### Ordem de implementação *(revista em 02/11/2026, depois da Etapa 2; a ordem prevista no congelamento está na anotação ao final desta seção)*

**Implementado, executado e aprovado na Etapa 2:**

1. Fixtures de worker: `queue_worker.php` e `lock_observer.php` (modos novos, protocolo `ready/go`, barreira, encerramento limitado, início com falha rápida, conferência da limpeza: C1, C2, C3).
2. I-12 (com o caso `perfdebug = 8`).
3. M-06, M-07, M-08, M-11, M-01, M-02, M-05, M-09 e M-10 (executado: o privilégio `KILL` está disponível); M-03, complementar, com barreira determinística.

**Executado na Etapa 3 (02/11/2026; aprovada pelo responsável em 02/11/2026):** *(texto original: "a revisão do responsável está pendente")*

4. Linha de base das três classes de persistência existentes com `--display-skipped` (CR-21): sem skips; ver "Estado da Etapa 3".
5. Correções 1 a 6 dos testes existentes, com reexecução das classes afetadas.

**Executado na Etapa 4 (02/11/2026; aprovada pelo responsável em 02/11/2026):** *(texto original: "a revisão do responsável está pendente")*

6a. I-03, I-04 e I-05 (falha real de banco por trigger, em `material_failure_test.php`), com a verificação de I-09 incorporada aos três; ver "Estado da Etapa 4".

**Executado na Etapa 5 (02/11/2026; aprovada pelo responsável em 02/11/2026):** *(texto original: "a revisão do responsável está pendente")*

6b. I-06 (cenário C), I-13 (cenário A2) e I-15 (cenário B), em `material_recovery_test.php`, com a verificação de I-09 em cada um; ver "Estado da Etapa 5".

**Executado na Etapa 6 (02/11/2026; a revisão do responsável está pendente):**

8a. Execução final de todos os obrigatórios, das correções dos testes existentes e da regressão; reclassificação documental dos CR com base nas evidências; matriz de rastreabilidade; proposta de encerramento; ver "Estado da Etapa 6" e "Proposta de encerramento da CL-07".

**Pendente, para etapas posteriores (nada abaixo foi implementado ou decidido):**

7. Complementares: I-01, I-14, I-16 e U-06 (não bloqueiam automaticamente o fechamento); o I-02 foi executado em 02/11/2026 *(texto anterior: "7. Complementares: I-01, I-02, I-14, I-16 e U-06")*.
8b. Decisão do responsável sobre o encerramento da CL-07 e a marcação da T020; o registro de fechamento só se conclui com essa autorização.
9. T144 e seus testes (U-07 a U-12, M-12, M-13, S-01, Q17), em etapa separada.

*(Texto original dos itens 7 e 8 da lista de pendências, de depois da Etapa 5: "7. Complementares: I-01, I-02, I-14, I-16 e U-06. 8. Execução de fechamento e registro da CL-07, com as ressalvas.")* *(Texto original do item 6b da lista de pendências, de depois da Etapa 4: "6b. I-06, I-13 e I-15 (com a verificação de I-09 em cada um; I-09 só fecha quando valer em todos).")* *(Texto original do item 6 da lista de pendências, de depois da Etapa 3: "6. I-03, I-04, I-05, I-06, I-09, I-13 e I-15.")* *(Texto original da lista de pendências, de depois da Etapa 2: "4. Linha de base: as três classes de persistência existentes com `--display-skipped` (CR-21). 5. I-03, I-04, I-05, I-06, I-09, I-13 e I-15. 6. Correções 1 a 6 dos testes existentes. 7. Complementares: I-01, I-02, I-14, I-16 e U-06. 8. Execução de fechamento e registro da CL-07, com as ressalvas. 9. T144 e seus testes (U-07 a U-12, M-12, M-13, S-01, Q17), em etapa separada.")*

*(Ordem prevista no congelamento, de 02/11/2026: "1. Linha de base: as três classes de persistência com `--display-skipped`. 2. Fixtures de worker. 3. I-03, I-04, I-05, I-09, I-06, I-13, I-15, I-12. 4. M-06, M-07, M-08, M-11, M-01, M-02, M-05, M-09, M-10; depois M-03. 5. Correções dos testes existentes. 6. Complementares: I-01, I-02, I-14, I-16. 7. Execução de fechamento e registro da CL-07. 8. T144 e seus testes (U-07 a U-12, M-12, M-13, S-01, Q17), em etapa separada.")*

### Estado da validação após a Etapa 2 *(02/11/2026)*

A Etapa 2 implementou a infraestrutura de testes multiprocesso e **10 cenários obrigatórios e 1 complementar** desta matriz, e os executou no PHPUnit, nas tabelas de teste. A Etapa 2 foi **aprovada pelo responsável**. **Esta execução não fecha a CL-07 nem a T020**: ficam pendentes os cenários listados em "Ordem de implementação" (itens 4 a 8) e o registro de fechamento. A tabela "Critérios" **não foi reclassificada**: a reclassificação de cada CR depende do registro de fechamento. CL-07, T020 e T144 continuam abertas. *(Registro histórico: estado na data desta etapa, anterior ao encerramento da CL-07, à conclusão da T020 e à conclusão da T144, todos em 02/11/2026; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)*

**Ambiente:** Moodle 5.2.4+ (Build 20261030), PHP 8.3.36, MariaDB 11.4.13, PHPUnit 11.5.55. O `phpunit.xml` da raiz foi gerado com `admin/tool/phpunit/cli/util.php --buildconfig` em `/var/www/html`, fora do repositório.

**Novas classes de teste** (`vendor/bin/phpunit --colors=never --display-skipped --display-warnings --display-notices --testdox <arquivo>`):

| Classe | Cenários | Testes | Asserções | Resultado |
|---|---|---|---|---|
| `unit_of_work_concurrency_test.php` | I-12 (2 testes), M-06 (2 casos: commit e rollback), M-07, M-08, M-09, M-10, M-11 | 9 | 83 | OK, 0 skips |
| `material_concurrency_test.php` | M-01, M-02, M-03 | 3 | 119 | OK |
| `report_concurrency_test.php` | M-05 | 1 | 15 | OK |
| **Total** | | **13** | **217** | **0 falhas, 0 erros, 0 skips** |

**Regressão dos seis grupos antigos que usam as fixtures** (`redeliver_event_test`, `event_service_concurrency_test`, `event_service_deferral_test`, `redelivery_erasure_race_test`, `student_redelivery_erasure_test`, `redelivery_observers_test`): **58 testes, 513 asserções, 0 falhas, 0 erros, 0 skips.**

**Outras evidências:**

- **M-03** repetido em oito execuções seguidas e em outras três depois, sempre com o mesmo resultado na primeira rodada: **uma inserção aceita e três recusadas por duplicidade**. As rodadas seguintes, sem barreira, variaram (de 30 a 41 aceitas em 64).
- **M-10** foi **executado**: o servidor permite `KILL CONNECTION` a este usuário.
- **C1, C2 e C3** corrigidos e validados. **I-12** validado, inclusive com `perfdebug = 8`.
- **Testes temporários** de verificação dos caminhos de falha da infraestrutura (falha de início, processo vivo sem marcador, processo que ignora `SIGTERM`, estado desconhecido, `PROCESSLIST` ilegível ou com conexão residual, falha parcial de grupo, barreira com worker morto, falha do diagnóstico de timeout) foram executados e **removidos**.
- **Limpeza** verificada ao fim: nenhum worker vivo, nenhum trigger, nenhum arquivo temporário e nenhuma linha residual nas tabelas de teste.

**Cenários ainda sem evidência de execução:** I-03, I-04, I-05, I-06, I-09, I-13, I-15; as seis correções dos testes existentes; os complementares I-01, I-02, I-14, I-16 e U-06; a linha de base com `--display-skipped` (CR-21); e tudo o que é da T144. *(Situação em 02/11/2026, depois da Etapa 3: a linha de base e as seis correções passaram a ter evidência, em "Estado da Etapa 3"; o restante da lista continua sem evidência.)* *(Situação em 02/11/2026, depois da Etapa 4: I-03, I-04 e I-05 passaram a ter evidência, e I-09 a tem nesses três; ver "Estado da Etapa 4".)* *(Situação em 02/11/2026, depois da Etapa 5: I-06, I-13 e I-15 passaram a ter evidência, e I-09 a tem em todos os cenários implementados; ver "Estado da Etapa 5". Continuam sem evidência os complementares I-01, I-02, I-14, I-16 e U-06 e tudo o que é da T144.)* *(Situação em 02/11/2026, depois da Etapa 6: todos os cenários obrigatórios foram reexecutados com zero falhas, erros e skips; ver "Estado da Etapa 6". Continuam sem evidência de execução apenas os complementares I-01, I-02, I-14, I-16 e U-06 e o que é da T144, que não bloqueiam o fechamento da CL-07.)* *(Situação em 02/11/2026, depois do I-02: o I-02 passou a ter evidência; continuam sem evidência de execução os complementares I-01, I-14, I-16 e U-06 e o que é da T144.)*

**Ressalvas técnicas conhecidas:**

- **M-10:** a exceção que o worker devolve vem do `RELEASE_LOCK` no `finally` de `unit_of_work::run`, sobre a conexão encerrada, e **mascara** a falha original. O teste exige só a família `dml_exception`. Não foi corrigido nem se mexeu na produção.
- **Nenhum processo que sobreviva ao `SIGKILL` foi exercitado:** isso não pode ser criado no PHP. O ramo "encerramento não confirmado" de `finish_actor()` foi verificado por simulação (override de `stop_process()` num teste temporário), não por um processo real. O PHP não garante o fim de um processo que sobreviva ao `SIGKILL`; nesse caso o ator fica registrado, `proc_close()` não é chamado e a falha é reportada.
- **Limpeza e diagnóstico:** `end_actors()` não lança; a falha de limpeza é escrita em `stderr` e levantada por um hook `#[After]` de prioridade baixa (para não pular o reset do Moodle); o PHPUnit a reporta **além** da falha do teste, não no lugar dela. A conferência das conexões espera até 10 s e uma lista ilegível conta como não confirmada. As esperas por sinal são *polling* com prazo.
- **M-01, M-02 e M-05** exigem sobreposição temporal dos workers, mas não afirmam que algum worker esperou o lock; a corrida real é demonstrada pelo M-03. **M-02** só exige ponteiro válido (a monotonicidade é da T144).
- A execução combinada dos seis grupos antigos relata **"PHPUnit Deprecations: 4088"**, sem origem identificada; cada classe sozinha, e as classes novas, terminam sem avisos.


### Estado da Etapa 3 *(02/11/2026)*

A Etapa 3 registrou a linha de base das classes de persistência existentes e aplicou as seis "Correções dos testes existentes". **Esta execução não fecha a CL-07 nem a T020**, a tabela "Critérios" **não foi reclassificada**, e a Etapa 3 foi **aprovada pelo responsável em 02/11/2026** *(texto original: "aguarda a revisão do responsável")*. CL-07, T020 e T144 continuam abertas. *(Registro histórico: estado na data desta etapa, anterior ao encerramento da CL-07, à conclusão da T020 e à conclusão da T144, todos em 02/11/2026; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)* Não houve alteração de código de produção, XMLDB, `version.php`, fixtures da Etapa 2, DEC-61 ou DEC-62.

**Ambiente:** branch `feature/sdd-ajustado`, HEAD `caf31ff` (árvore limpa na partida), Moodle 5.2.4+ (Build 20261030), PHP 8.3.36, MariaDB 11.4.13, PHPUnit 11.5.55, `phpunit.xml` em `/var/www/html`. O usuário do banco tem `ALL PRIVILEGES` sobre o esquema (inclui `TRIGGER`). Comando de cada classe: `vendor/bin/phpunit --colors=never --display-skipped --display-warnings --display-notices --testdox <arquivo>`. Os relatórios completos estão no scratchpad da sessão, fora do repositório. *(Correção de 02/11/2026: essa pasta temporária foi apagada e os relatórios brutos das Etapas 3 a 6 **não existem mais**; ver "Registro dos relatórios integrais" em "Revisão documental complementar da Etapa 6".)*

**Antes e depois** (todas sem falhas, erros, avisos, notices ou skips):

| Classe | Antes (HEAD) | Depois | Observação |
|---|---|---|---|
| `unit_of_work_test.php` | 12 testes, 62 asserções, 2,8 s | 12 testes, 62 asserções, 2,7 s | correção 2 (docblock e rótulo) |
| `material_repository_test.php` | 23 testes, 117 asserções, 6,0 s | 23 testes, 117 asserções, 6,6 s | correção 4 (docblock e rótulo) |
| `report_repository_test.php` | 18 testes, 63 asserções, 7,6 s | 18 testes, **69** asserções, 12,2 s (12,6 s depois do ajuste da correção 6) | correção 1 (processo real: +6 asserções); correção 6: skip trocado por falha |
| `material_service_test.php` (extra) | 28 testes, 142 asserções, 20,5 s | 28 testes, 142 asserções, 22,9 s | correção 3 (docblock e rótulo) |
| `install_schema_test.php` (extra) | 5 testes, 222 asserções, 1,1 s | 5 testes, 222 asserções, 1,1 s | correção 5 (docblock e rótulo) |

As três classes da Etapa 2 (`unit_of_work_concurrency_test`, `material_concurrency_test`, `report_concurrency_test`) foram reexecutadas depois das alterações: 9/83, 3/119 e 1/15, sem falhas, erros ou skips (sem regressão).

**Correções:**

- **1 (aplicada).** `test_version_is_not_written_without_the_lock_of_the_report` usa o trait `lock_observer` (`hold_resource`): o lock do relatório é segurado por **outro processo** (conexão própria), o teste confirma o dono pelo servidor (`IS_USED_LOCK`), espera `lock_unavailable_exception` com o recurso do relatório, confirma que nenhuma versão foi gravada e que o ponteiro continua nulo, libera o processo e confere que o lock volta a ficar livre. O `markTestSkipped` foi removido; a falta de pré-requisito falha (`assert_observable_locks`). A limpeza dos processos vem do `tearDown` da classe e do hook da fixture.
- **2, 3, 4 e 5 (aplicadas).** Só docblock e `#[TestDox]`; nenhuma asserção ou comportamento mudou (contagens idênticas antes e depois). Os métodos declaram "lógica no processo" (2), "falha simulada" e "lock no mesmo processo", fora de CR-07 e CR-12 (3), "rejeição lógica antes de qualquer escrita", fora de CR-07 (4), e "XML contra inventário", com o esquema físico em I-16 (5). O docblock de Q2 dizia que o teste conferia tipo, tamanho e demais atributos dos campos; o teste só confere contagens e o `id` de cada tabela, e o docblock passou a dizer isso. *(Docblock original de Q2: "field definitions (type, length, decimals, not null, sequence) match the inventory".)*
- **6 (verificada; ajuste final de código).** Depois da revisão, o `markTestSkipped` do bloco de criação do trigger foi substituído por `fail()`: qualquer `dml_exception` em `CREATE TRIGGER` (privilégio, sintaxe ou outra) reprova o teste, com a classe e a mensagem da exceção original, e o `DROP TRIGGER IF EXISTS` no `finally` e as asserções funcionais não mudaram. *(Texto original deste item: "verificada, sem alteração de código"; o ramo `catch` pulava o teste com "The test user cannot create triggers".)* `test_failure_midway_keeps_the_last_valid_version_and_schedule` foi **executado e aprovado** (5 asserções), consta como aprovado no `--testdox` e não aparece em nenhuma relação de testes pulados. Foi executado também isoladamente com `--filter`. Nenhum trigger ficou no esquema depois.

**Resultados por tipo:**

- **Positivos:** linha de base sem skips nas três classes (CR-21 só quanto a estas classes); as seis correções aplicadas e as classes reexecutadas sem regressão; a exclusão do lock do relatório agora é provada com outro processo.
- **Negativos:** nenhum.
- **Inconclusivos / ressalvas:** (a) os dois testes de `unit_of_work_test.php` ainda têm um `markTestSkipped` no código (linhas 194 e 269), não acionado nesta bateria; o registro "sem skips" vale **para esta execução e este ambiente**; esses dois testes não são prova obrigatória (a exclusão no banco é de M-06 e M-07) e não foram alterados; o teste do trigger deixou de ter `markTestSkipped` (ver correção 6); *(texto original: "os dois testes de `unit_of_work_test.php` e o teste do trigger em `report_repository_test.php` ainda têm um `markTestSkipped` no código […]; em outro ambiente (sem permissão de trigger) o teste do trigger seria pulado; mantido por estar fora do que a correção 6 pede")* (b) a correção 1 depende de a fixture observar `GET_LOCK` no servidor, o que `assert_observable_locks` verifica a cada execução; (c) os cenários I-03, I-04, I-05, I-06, I-09, I-13, I-15 e I-16 continuam sem implementação, e é neles que se prova a falha real de gravação (CR-07) e o esquema físico.

**Limpeza:** nenhum worker vivo, nenhum trigger no esquema, nenhum lock de aplicação em uso e nenhum arquivo de sinal sobrando ao fim da bateria.


### Estado da Etapa 4 *(02/11/2026)*

A Etapa 4 implementou e executou **I-03, I-04 e I-05** (falha real de banco em cada uma das três etapas da DEC-61), com a verificação do invariante de **I-09** incorporada aos três. **Esta execução não fecha a CL-07 nem a T020**, a tabela "Critérios" **não foi reclassificada**, e a Etapa 4 foi **aprovada pelo responsável em 02/11/2026** *(texto original: "aguarda a revisão do responsável")*. CL-07, T020 e T144 continuam abertas. *(Registro histórico: estado na data desta etapa, anterior ao encerramento da CL-07, à conclusão da T020 e à conclusão da T144, todos em 02/11/2026; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)* Não houve alteração de código de produção, XMLDB, `version.php`, fixtures da Etapa 2, DEC-61 ou DEC-62.

**Ambiente:** o mesmo da Etapa 3 (HEAD de partida `d8422fa`, Moodle 5.2.4+ Build 20261030, PHP 8.3.36, MariaDB 11.4.13, PHPUnit 11.5.55). Comando: `vendor/bin/phpunit --colors=never --display-skipped --display-warnings --display-notices --testdox assistente/tests/infrastructure/persistence/material_failure_test.php` (a partir de `/var/www/html`, caminho `public/local/assistente/tests/…`).

**Classe nova** `material_failure_test.php`: **6 testes e 167 asserções, 0 falhas, 0 erros, 0 skips, 0 avisos e 0 notices**. *(Ajustes depois da revisão: a verificação do ponteiro (I-09) passou a afirmar que o material existe antes de ler `current_version`, e os triggers passaram ao prefixo exclusivo `cl07_mf_`, de modo que a limpeza só alcança os da classe; o total subiu de 151 para 167 asserções, sem mudar a contagem de testes. Texto original: "6 testes e 151 asserções […], na primeira execução e em outras três seguidas (4 execuções idênticas)", obtidos antes desses ajustes.)*

| Teste | Cenário | Operação real de banco e ponto de injeção | Resultado |
|---|---|---|---|
| `test_a_failure_in_the_switch_keeps_the_previous_version_current` | I-03 (+ I-09) | `UPDATE` de `material` (`switch_current`); trigger `BEFORE UPDATE` em `material` | OK |
| `test_a_failure_in_the_insert_of_the_version_does_not_consume_the_number` (2 casos: primeira versão e versão seguinte) | I-04 (+ I-09) | `INSERT` em `material_version` (`add_version`); trigger `BEFORE INSERT` em `material_version` | OK |
| `test_a_failure_when_the_version_becomes_completed_keeps_it_pending` | I-05 (+ I-09) | `UPDATE` de `material_version` (`complete_summary`); trigger `BEFORE UPDATE` em `material_version` | OK |
| `test_the_check_of_the_pointer_detects_a_broken_pointer` (2 casos) | controle da verificação de I-09 | escrita direta de um ponteiro inválido (versão `pending` do mesmo material; versão `completed` de outro material) | OK: a verificação reprova nos dois |

**Mecanismo (conforme a decisão da matriz):** `CREATE TRIGGER … BEFORE <evento> ON <tabela> FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '<marcador>'`, instrução única, sem `;`, enviada por `$DB->execute()`; marcador próprio (`cl07 i03 falha injetada na troca`, `cl07 i04 falha injetada na insercao`, `cl07 i05 falha injetada na conclusao`), sem `duplicate`, `unique constraint`, `:`, `?` nem `$`. Incondicional: a janela é o controle (estado preparado antes; a operação testada é a única escrita dentro dela). Trigger criado imediatamente antes da operação e removido em `finally`; `setUp` e `tearDown` removem qualquer trigger `cl07_mf_*` sobrado (e só esses: um trigger de outro prefixo, criado de propósito durante a execução, permaneceu intacto); falha na criação do trigger **falha** o teste (sem `markTestSkipped`); a criação exige que não haja transação aberta (DDL faz commit implícito), e a sessão usa `lock_wait_timeout` de 10 s durante o teste, restaurado ao fim.

**O que cada teste prova:**

- **Pela exceção:** é `dml_write_exception` (nunca `duplicate_record_exception`) e o marcador está em `$e->error`. Isso confirmou na execução que o `MESSAGE_TEXT` do `SIGNAL` chega ao erro do Moodle.
- **Pelo estado persistido:** foto das duas tabelas inteiras, todas as colunas, ordenada por `id`, comparada com a de antes (material e versões separadamente). I-03: ponteiro na versão anterior, alvo `completed`. I-04: nenhuma linha nova, nenhuma linha da tentativa que falhou, ponteiro intacto. I-05: versão `pending`, resumo nulo, ponteiro intacto, e a transição `pending` para `completed` aceita pelo domínio antes da falha (a rejeição não é lógica).
- **Recuperação do próprio critério:** sem o trigger, a mesma troca (I-03) e a mesma conclusão (I-05) funcionam; em I-04, a tentativa seguinte recebe o mesmo `version` que a falha teria recebido, os números ficam contíguos, e não se compara `id` (o `AUTO_INCREMENT` do motor pode ser consumido).
- **Transação e lock:** `is_transaction_started()` falso, e `@@in_transaction` igual a 0 no servidor (a variável está disponível no MariaDB 11.4.13: nenhuma nota de indisponibilidade foi emitida); `IS_USED_LOCK` do `material_<id>` nulo no servidor antes, depois da falha e depois do controle (usa `assert_observable_locks()`, que falha em vez de pular); nenhum trigger sobrado.
- **I-09:** `current_version` nulo ou apontando para versão `completed` do mesmo material, lido do banco, antes da injeção, depois da falha e depois do controle. O primeiro caso de I-04 exercita o ponteiro nulo.

**Regressão:** as 18 classes de `infrastructure/persistence` foram executadas uma a uma (o caminho do diretório, sozinho, não executa nenhum teste neste `phpunit.xml`) mais `material_service_test` (28/142) e `install_schema_test` (5/222): todas OK, sem falhas, erros, skips, avisos ou notices. Entre elas, as da Etapa 2 e da Etapa 3 mantêm seus números (`unit_of_work_concurrency` 9/83, `material_concurrency` 3/119, `report_concurrency` 1/15, `unit_of_work` 12/62, `material_repository` 23/117, `report_repository` 18/69).

**Limpeza:** nenhum trigger no esquema, nenhum worker vivo e nenhum arquivo de sinal ao fim.

**Resultados por tipo:**

- **Positivos:** I-03, I-04, I-05 aprovados com falha real de banco; a verificação de I-09 aprovada nos três e provada capaz de reprovar; sem regressão.
- **Negativos:** nenhum.
- **Inconclusivos / ressalvas:** (a) I-09 ("em todo cenário") só fecha quando a mesma verificação valer em I-06, I-13 e I-15 (Etapa 5); em M-02 já existe uma verificação equivalente, privada da classe `material_concurrency_test`; (b) o lock é verificado livre no servidor depois da operação; que ele estava efetivamente **tomado durante** a operação é demonstrado por M-06 e M-07, não por estes testes; (c) a prova de que as asserções falham quando o estado muda foi feita só para a verificação do ponteiro (controle), não por mutação das demais; (d) CR-07 passa a ter os três cenários executados, mas **não foi reclassificado**: a reclassificação depende do registro de fechamento; (e) vale para MariaDB 11.4.13, com `GET_LOCK`, no esquema de teste.


### Estado da Etapa 5 *(02/11/2026)*

A Etapa 5 implementou e executou os cenários de recuperação **I-06, I-13 e I-15**, com a verificação do invariante de **I-09** em cada um. **Esta execução não fecha a CL-07 nem a T020**, a tabela "Critérios" **não foi reclassificada** (a reclassificação é da Etapa 6), e a Etapa 5 foi **aprovada pelo responsável em 02/11/2026** *(texto original: "aguarda a revisão do responsável")*. CL-07, T020 e T144 continuam abertas. *(Registro histórico: estado na data desta etapa, anterior ao encerramento da CL-07, à conclusão da T020 e à conclusão da T144, todos em 02/11/2026; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)* Não houve alteração de código de produção, XMLDB, `version.php`, fixtures, DEC-61, DEC-62, dos testes da Etapa 2 nem dos da Etapa 4.

**Correspondência entre os testes e os cenários** (definição da matriz, seção A, e CL-52): I-06 é o **cenário C**, I-13 é o **cenário A2** e I-15 é o **cenário B**.

| Teste | Cenário | Estado deixado pela primeira execução | A nova execução do serviço | Evidência exigida e verificada |
|---|---|---|---|---|
| I-06 | C | versão `completed` e **não corrente** (a troca de `current_version` falhou, por trigger em `material`) | só troca o ponteiro (`produce_summary` vê `completed`) | ponteiro na versão; **nenhuma chamada à LLM a mais**; nenhum número novo; nenhuma linha de versão alterada |
| I-13 | A2 | versão `pending` sem resumo (exceção do extrator, ou o que um processo morto depois de `add_version` deixa) | reaproveita a mesma versão (não cria outra) | mesma versão; contagem de versões inalterada; **uma** chamada à LLM; `completed` e corrente |
| I-15 | B | versão `failed` sem resumo (a gravação do resumo falhou, por trigger em `material_version`) | reaproveita a mesma versão e **chama a LLM de novo** | mesma versão; **duas chamadas no total**; `completed` e corrente (limitação aceita, CL-52) |

**Classe nova** `assistente/tests/application/material/material_recovery_test.php` (`vendor/bin/phpunit --colors=never --display-skipped --display-warnings --display-notices --testdox`, a partir de `/var/www/html`, caminho `public/local/assistente/tests/application/material/material_recovery_test.php`): **5 testes e 189 asserções, 0 falhas, 0 erros, 0 skips, 0 avisos e 0 notices**, na primeira execução e em outras três seguidas (4 execuções idênticas, cerca de 5,5 s cada).

| Teste | Cenário | Resultado |
|---|---|---|
| `test_a_switch_that_failed_after_completed_is_finished_without_calling_the_llm` (2 casos: com versão corrente anterior; sem versão corrente antes) | I-06 (+ I-09) | OK |
| `test_a_pending_version_left_behind_is_taken_up_and_completed` (2 casos: exceção do extrator; processo que morreu depois do primeiro passo) | I-13 (+ I-09) | OK |
| `test_a_summary_that_could_not_be_persisted_is_asked_again` | I-15 (+ I-09) | OK |

**Como cada falha é produzida e provada:**

- **O serviço roda de verdade** (`material_service::process`), contra as tabelas de teste, com a LLM simulada (`fake_adapter`) e o repositório real. A primeira execução falha num passo conhecido e a segunda, com o mesmo conteúdo, encontra o estado deixado e termina o trabalho.
- **I-06:** trigger `BEFORE UPDATE` em `material`, `SIGNAL SQLSTATE '45000'` como instrução única, incondicional, com marcador próprio, criado imediatamente antes da execução e removido em `finally`. Na janela, a única escrita em `material` é a troca do ponteiro. A primeira execução devolve falha técnica `could not persist the summary`, a versão fica `completed` com resumo e o ponteiro não se move. A segunda devolve sucesso pelo ramo "só falta a troca" (`... is current`), sem nova chamada à LLM.
- **I-13:** a versão `pending` é deixada por uma exceção do extrator (o extrator é chamado fora do `try` do serviço, então a exceção sai de `process()`) ou pelo que o primeiro passo da DEC-61 deixa (`add_version` do repositório, com o hash do conteúdo). A LLM não é alcançada na primeira execução.
- **I-15:** trigger `BEFORE UPDATE` em `material_version`, instrução única com expressão condicional que põe uma coluna `NOT NULL` em `NULL` quando o novo status é `completed` (precisa de `sql_mode` estrito, que o teste verifica; falha, sem skip, se não houver). A falha atinge só a passagem para `completed`; o serviço ainda consegue marcar a versão `failed`, que é o que o teste exige. Essa falha não tem marcador próprio e é reconhecida pelo estado que deixa, conforme a decisão da matriz para I-15. Prova de que a escolha importa: com um trigger incondicional (mutação temporária de uma cópia do teste, removida) o serviço não consegue marcar `failed`, a versão fica `pending` e o teste reprova.
- **Estado persistido, lido do banco** antes e depois de cada execução: status e resumo da versão, ponteiro do material, contagem e números das versões, linhas das versões comparadas (I-06), chamadas à LLM contadas pelo adaptador (`calls()`).
- **I-09** (`assert_pointer_invariant`, com a verificação de que o material existe): `current_version` nulo, ou versão existente, do mesmo material e `completed`, depois da falha e depois da recuperação, em todos os casos. O caso "sem versão corrente antes" de I-06 exercita o ponteiro nulo.
- **Transação e lock:** `is_transaction_started()` falso e `@@in_transaction` igual a 0 no servidor; `IS_USED_LOCK` do `material_<id>` nulo no servidor (usa `assert_observable_locks()`, que falha em vez de pular); nenhum trigger sobrado; nas duas fases de cada cenário.
- **Limpeza:** trigger criado imediatamente antes e removido em `finally`; `setUp` e `tearDown` removem qualquer trigger de prefixo `cl07_mr_` (exclusivo desta classe); nenhum trigger, worker ou arquivo de sinal ao fim.

**Regressão (cada classe em execução própria, com `--display-skipped --display-warnings --display-notices`):** as 18 classes de `infrastructure/persistence` (incluindo `material_failure_test` 6/167, `material_concurrency_test` 3/119, `unit_of_work_concurrency_test` 9/83, `report_concurrency_test` 1/15, `material_repository_test` 23/117, `report_repository_test` 18/69, `unit_of_work_test` 12/62); `content_extractor_test` 8/40, `material_service_test` 28/142, `handler_registry_test` 5/36, `queue_processor_test` 20/101; `install_schema_test` 5/222, `upgrade_test` 2/4; e os seis grupos que usam as fixtures (`redeliver_event_test` 13/55, `event_service_deferral_test` 11/64, `event_service_concurrency_test` 11/247, `redelivery_observers_test` 3/19, `redelivery_erasure_race_test` 4/84, `student_redelivery_erasure_test` 16/44; 58 testes e 513 asserções, como na Etapa 2). Todas OK, sem falhas, erros, skips, avisos ou notices.

**Resultados por tipo:**

- **Positivos:** I-06, I-13 e I-15 aprovados contra o banco real; I-09 verificada nos três, em I-03/I-04/I-05 (Etapa 4) e em M-02 (Etapa 2); sem regressão; sem resíduos.
- **Negativos:** nenhum.
- **Inconclusivos / ressalvas:**
  - (a) **Processo morto.** Em I-13, o caso "processo que morreu" reproduz o **estado** que um processo morto deixa depois de `add_version`, não mata um processo que esteja rodando o serviço: as fixtures dos workers não executam o serviço, e não foram alteradas. A morte de processo com transação aberta e o encerramento da conexão estão em M-09 e M-10.
  - (b) **Falha do passo identificada pelo estado.** O serviço devolve só `could not persist the summary` (sem a causa do banco); por isso a falha de I-06 e a de I-15 são reconhecidas pelo estado que deixam e pela mensagem, e o controle (a mesma execução funcionando sem o trigger) mostra que a causa era o trigger.
  - (c) **CL-52 preservada.** I-15 registra que a LLM é chamada de novo e que o segundo resumo é um resumo novo; **não** transforma isso em garantia de idempotência nem em requisito, e não altera a decisão do responsável de 02/11/2026. O teste não compara o texto do primeiro e do segundo resumo (o primeiro nunca foi persistido).
  - (d) **CL-50 e CL-51 não foram avaliadas** por estes testes: a versão `pending` superada por conteúdo novo e a ausência de reconciliação depois de esgotadas as tentativas continuam riscos independentes. Os testes recuperam com uma nova execução do serviço, como a fila faria, sem simular o esgotamento de tentativas.
  - (e) **Helpers.** Os auxiliares de trigger, de limpeza e da verificação de I-09 são privados da classe, como na Etapa 4 (a extração para um trait compartilhado foi avaliada e adiada: exigiria alterar `material_failure_test.php`, já aprovado; ficam dois blocos pequenos duplicados; reavaliar se uma etapa futura precisar de um terceiro uso).
  - (f) CR-19 passa a ter I-03, I-06, I-13 e I-15 executados, mas **não foi reclassificado**: a reclassificação é da Etapa 6.
  - (g) Vale para MariaDB 11.4.13, com `GET_LOCK` e `sql_mode` estrito, no esquema de teste.

**Critérios atendidos nesta etapa:** I-06, I-13 e I-15 implementados conforme a matriz; A2, B e C cobertos; I-09 integrada; CL-52 preservada; zero falhas, erros, skips, avisos e notices; regressão executada; sem resíduos. **Pendentes para a CL-07** (Etapa 6 e seguintes): complementares I-01, I-02, I-14, I-16 e U-06; execução de fechamento com a reclassificação dos CR; registro de fechamento com as ressalvas de ambiente; autorização expressa para marcar a T020. A T144 permanece separada.


### Estado da Etapa 6 *(02/11/2026)*

A Etapa 6 reexecutou **todos os cenários obrigatórios** da seção A, as seis correções dos testes existentes e a regressão pertinente, reclassificou os CR com base nas evidências, corrigiu as referências desatualizadas das Etapas 3 e 5 e preparou a proposta de encerramento. **Esta etapa não fecha a CL-07 nem a T020** e **aguarda a revisão do responsável**. CL-07, T020 e T144 continuam abertas. *(Registro histórico: estado na data desta etapa, anterior ao encerramento da CL-07, à conclusão da T020 e à conclusão da T144, todos em 02/11/2026; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)* Não houve alteração de código de produção, de testes, de fixtures, de XMLDB, de DEC-61, DEC-62 ou CL-52; nenhum cenário novo foi criado.

**1. Data.** 02/11/2026 (execução final entre 15:11 e 15:18 UTC). A bateria foi executada duas vezes, com resultados idênticos (351 testes e 2400 asserções, tudo zerado); a segunda é a registrada abaixo.

**2. Ambiente.** Moodle 5.2.4+ (Build 20261030), PHP 8.3.36, MariaDB 11.4.13 (`11.4.13-MariaDB-ubu2404`), PHPUnit 11.5.55, Linux 6.8.0-1064-azure x86_64; `phpunit.xml` em `/var/www/html` (fora do repositório); prefixo de teste `test_`; `sql_mode` `STRICT_ALL_TABLES`; isolamento `REPEATABLE-READ`; Lock Factory efetiva `mysql_lock_factory` (`GET_LOCK`), confirmada por I-12.

**3. Branch e commit de referência.** `feature/sdd-ajustado`, HEAD `39e88a90c5b7598decb0a4ff397360f8b01b9411`, árvore limpa na partida. Há três commits locais ainda não enviados: `d8422fa` (Etapa 3), `cf5bb16` (Etapa 4) e `39e88a9` (Etapa 5).

**4. Matriz de execução dos obrigatórios** (comando de cada linha: `cd /var/www/html && vendor/bin/phpunit --colors=never --display-skipped --display-warnings --display-notices --testdox public/local/assistente/tests/<caminho>`):

| ID | CR | Caminho e método de teste (a partir de `assistente/tests/`) | Etapa | Última evidência antes da Etapa 6 | Resultado final |
|---|---|---|---|---|---|
| I-03 | 07, 19 | `infrastructure/persistence/material_failure_test.php::test_a_failure_in_the_switch_keeps_the_previous_version_current` | 4 | 6 testes, 167 asserções (Etapa 4) | OK |
| I-04 | 07 | `…/material_failure_test.php::test_a_failure_in_the_insert_of_the_version_does_not_consume_the_number` (2 casos) | 4 | idem | OK |
| I-05 | 07 | `…/material_failure_test.php::test_a_failure_when_the_version_becomes_completed_keeps_it_pending` | 4 | idem | OK |
| I-06 | 19 | `application/material/material_recovery_test.php::test_a_switch_that_failed_after_completed_is_finished_without_calling_the_llm` (2 casos) | 5 | 5 testes, 189 asserções (Etapa 5) | OK |
| I-13 | 19 | `…/material_recovery_test.php::test_a_pending_version_left_behind_is_taken_up_and_completed` (2 casos) | 5 | idem | OK |
| I-15 | 19 | `…/material_recovery_test.php::test_a_summary_that_could_not_be_persisted_is_asked_again` | 5 | idem | OK |
| I-09 | 03 | em `material_failure_test` (3 cenários e 2 casos de controle), `material_recovery_test` (5 testes) e `material_concurrency_test::test_four_processes_running_the_full_cycle_leave_a_valid_pointer` | 2, 4, 5 | Etapas 2, 4 e 5 | OK |
| I-12 | 09 | `infrastructure/persistence/unit_of_work_concurrency_test.php::test_the_effective_lock_factory_is_the_database_one` e `::test_the_effective_lock_factory_is_recognised_behind_the_timing_wrapper` | 2 | 9 testes, 83 asserções (Etapa 2) | OK |
| M-01 | 14 | `…/material_concurrency_test.php::test_four_processes_adding_versions_get_consecutive_numbers` | 2 | 3 testes, 119 asserções | OK |
| M-02 | 14 | `…/material_concurrency_test.php::test_four_processes_running_the_full_cycle_leave_a_valid_pointer` | 2 | idem | OK |
| M-05 | 15 | `…/report_concurrency_test.php::test_four_processes_adding_report_versions_get_consecutive_numbers` | 2 | 1 teste, 15 asserções | OK |
| M-06 | 11 | `…/unit_of_work_concurrency_test.php::test_the_lock_is_exclusive_while_held_and_free_after_commit_or_rollback` (commit e rollback) | 2 | 9 testes, 83 asserções | OK |
| M-07 | 12 | `…/unit_of_work_concurrency_test.php::test_the_lock_timeout_expires_while_another_process_holds_the_lock` | 2 | idem | OK |
| M-08 | 13 | `…/unit_of_work_concurrency_test.php::test_the_lock_is_released_when_the_holder_is_killed` | 2 | idem | OK |
| M-09 | 18 | `…/unit_of_work_concurrency_test.php::test_an_unconfirmed_write_is_rolled_back_when_the_process_is_killed` | 2 | idem | OK |
| M-10 | 20 | `…/unit_of_work_concurrency_test.php::test_a_connection_killed_by_the_server_fails_cleanly_and_the_work_can_be_repeated` | 2 | executado na Etapa 2 | OK, **executado, não pulado** |
| M-11 | 16 | `…/unit_of_work_concurrency_test.php::test_independent_resources_progress_while_the_same_resource_waits` | 2 | 9 testes, 83 asserções | OK |
| M-03 (complementar) | 05 | `…/material_concurrency_test.php::test_without_the_lock_the_unique_index_refuses_duplicate_versions` | 2 | 3 testes, 119 asserções | OK |

**5 e 6. Comandos e resultados por classe.** Cada classe foi executada sozinha, com o comando acima (o caminho do diretório, sozinho, não executa nenhum teste neste `phpunit.xml`, por isso o arquivo é passado classe a classe). Colunas: testes, asserções, falhas, erros, skips, warnings, notices, tempo.

| Classe | Testes | Asserções | Falhas | Erros | Skips | Warnings | Notices | Tempo |
|---|---|---|---|---|---|---|---|---|
| `material_failure_test` | 6 | 167 | 0 | 0 | 0 | 0 | 0 | 00:04.050 |
| `material_recovery_test` | 5 | 189 | 0 | 0 | 0 | 0 | 0 | 00:05.019 |
| `material_concurrency_test` | 3 | 119 | 0 | 0 | 0 | 0 | 0 | 00:13.644 |
| `report_concurrency_test` | 1 | 15 | 0 | 0 | 0 | 0 | 0 | 00:05.913 |
| `unit_of_work_concurrency_test` | 9 | 83 | 0 | 0 | 0 | 0 | 0 | 00:17.427 |
| `unit_of_work_test` | 12 | 62 | 0 | 0 | 0 | 0 | 0 | 00:02.683 |
| `material_repository_test` | 23 | 117 | 0 | 0 | 0 | 0 | 0 | 00:06.083 |
| `report_repository_test` | 18 | 69 | 0 | 0 | 0 | 0 | 0 | 00:11.046 |
| `material_service_test` | 28 | 142 | 0 | 0 | 0 | 0 | 0 | 00:18.517 |
| `install_schema_test` | 5 | 222 | 0 | 0 | 0 | 0 | 0 | 00:01.108 |
| `config_repository_test` | 15 | 32 | 0 | 0 | 0 | 0 | 0 | 00:05.620 |
| `criteria_result_repository_test` | 11 | 48 | 0 | 0 | 0 | 0 | 0 | 00:06.036 |
| `event_repository_test` | 15 | 91 | 0 | 0 | 0 | 0 | 0 | 00:06.327 |
| `forum_analysis_repository_test` | 12 | 34 | 0 | 0 | 0 | 0 | 0 | 00:10.941 |
| `frequency_repository_test` | 17 | 58 | 0 | 0 | 0 | 0 | 0 | 00:16.266 |
| `performance_repository_test` | 15 | 38 | 0 | 0 | 0 | 0 | 0 | 00:11.195 |
| `quiz_initial_repository_test` | 15 | 54 | 0 | 0 | 0 | 0 | 0 | 00:06.683 |
| `quiz_results_repository_test` | 16 | 58 | 0 | 0 | 0 | 0 | 0 | 00:08.539 |
| `redelivery_task_repository_test` | 4 | 26 | 0 | 0 | 0 | 0 | 0 | 00:01.247 |
| `repository_test` | 10 | 25 | 0 | 0 | 0 | 0 | 0 | 00:02.456 |
| `synthesis_repository_test` | 18 | 57 | 0 | 0 | 0 | 0 | 0 | 00:07.793 |
| `content_extractor_test` | 8 | 40 | 0 | 0 | 0 | 0 | 0 | 00:04.344 |
| `handler_registry_test` | 5 | 36 | 0 | 0 | 0 | 0 | 0 | 00:00.305 |
| `queue_processor_test` | 20 | 101 | 0 | 0 | 0 | 0 | 0 | 00:08.674 |
| `upgrade_test` | 2 | 4 | 0 | 0 | 0 | 0 | 0 | 00:00.422 |
| `redeliver_event_test` | 13 | 55 | 0 | 0 | 0 | 0 | 0 | 00:13.563 |
| `event_service_deferral_test` | 11 | 64 | 0 | 0 | 0 | 0 | 0 | 00:19.925 |
| `event_service_concurrency_test` | 11 | 247 | 0 | 0 | 0 | 0 | 0 | 00:57.086 |
| `redelivery_observers_test` | 3 | 19 | 0 | 0 | 0 | 0 | 0 | 00:11.074 |
| `redelivery_erasure_race_test` | 4 | 84 | 0 | 0 | 0 | 0 | 0 | 00:28.952 |
| `student_redelivery_erasure_test` | 16 | 44 | 0 | 0 | 0 | 0 | 0 | 00:12.329 |

**7. Resultado agregado.** 31 classes, **351 testes, 2400 asserções, 0 falhas, 0 erros, 0 skips, 0 warnings e 0 notices**; código de saída 0 em todas. Por grupo, **sem sobreposição** (cada uma das 31 classes está em exatamente um grupo): **A**, as 5 classes criadas nas Etapas 2 a 5 (`material_failure_test`, `material_recovery_test`, `material_concurrency_test`, `report_concurrency_test`, `unit_of_work_concurrency_test`): 24 testes e 573 asserções; **B**, as 5 classes da linha de base e das correções (`unit_of_work_test`, `material_repository_test`, `report_repository_test`, `material_service_test`, `install_schema_test`): 86 testes e 612 asserções; **C**, as 21 classes restantes, que são a regressão: 241 testes e 1215 asserções (24 + 86 + 241 = 351; 573 + 612 + 1215 = 2400). *(Texto original: "Por grupo: as 5 classes criadas nas Etapas 2 a 5 (…): 24 testes e 573 asserções; as 5 classes da linha de base e das correções (…): 86 testes e 612 asserções; as 21 restantes (regressão): 241 testes e 1215 asserções." Os números não mudaram; só foi explicitado que os grupos não se sobrepõem.)*

**8. Regressão.** Pela divisão da linha 7, o grupo **C** (21 classes, 241 testes e 1215 asserções) é a regressão em sentido estrito: as 11 classes de `infrastructure/persistence` que não são novas nem de linha de base (`config_repository_test`, `criteria_result_repository_test`, `event_repository_test`, `forum_analysis_repository_test`, `frequency_repository_test`, `performance_repository_test`, `quiz_initial_repository_test`, `quiz_results_repository_test`, `redelivery_task_repository_test`, `repository_test`, `synthesis_repository_test`), mais `content_extractor_test`, `handler_registry_test`, `queue_processor_test`, `upgrade_test` e os seis grupos que usam as fixtures multiprocesso. A pasta `infrastructure/persistence` tem 18 classes no total, e elas se distribuem assim: 4 do grupo A (`material_failure_test`, `material_concurrency_test`, `report_concurrency_test`, `unit_of_work_concurrency_test`), 3 do grupo B (`unit_of_work_test`, `material_repository_test`, `report_repository_test`) e as 11 do grupo C; a quinta classe do grupo A, `material_recovery_test`, e `material_service_test`, do grupo B, ficam em `application/material`, e `install_schema_test`, do grupo B, em `db`. Os seis grupos que usam as fixtures multiprocesso (`redeliver_event_test` 13/55, `event_service_deferral_test` 11/64, `event_service_concurrency_test` 11/247, `redelivery_observers_test` 3/19, `redelivery_erasure_race_test` 4/84, `student_redelivery_erasure_test` 16/44; 58 testes e 513 asserções, os mesmos números da Etapa 2). Todas OK. *(Texto original do início deste item: "As 18 classes de `infrastructure/persistence` (as 5 acima que são dessa pasta mais as 13 restantes), `material_service_test`, `content_extractor_test`, `material_recovery_test`, `handler_registry_test`, `queue_processor_test`, `install_schema_test`, `upgrade_test` e os seis grupos…". Correção factual: só 4 das 5 classes novas ficam nessa pasta (a quinta, `material_recovery_test`, fica em `application/material`), e a pasta se divide em 4 + 3 + 11, não em 5 + 13; a enumeração antiga repetia classes dos grupos A e B.)*

**9. As seis correções dos testes existentes** (conferidas no código e no resultado, não só pela existência do método):

| Corr. | Verificação no código | Resultado da execução |
|---|---|---|
| 1 | `report_repository_test::test_version_is_not_written_without_the_lock_of_the_report` usa `hold_resource` (processo independente), espera `lock_unavailable_exception`, não contém `markTestSkipped` | aprovado (`report_repository_test` 18/69, 0 skips) |
| 2 | os dois métodos de `unit_of_work_test` têm docblock "lógica de um só processo" e `#[TestDox]` que remete a exclusão no banco a M-06 e M-07 | aprovados (12/62) |
| 3 | os dois métodos de `material_service_test` declaram "falha simulada" e "lock no mesmo processo" e "not evidence of CR-07 or CR-12" | aprovados (28/142) |
| 4 | os dois métodos de `material_repository_test` declaram "rejeição lógica antes de qualquer escrita" | aprovados (23/117) |
| 5 | os três métodos de `install_schema_test` declaram "XML against the inventory" e remetem o esquema físico a I-16 | aprovados (5/222) |
| 6 | `report_repository_test::test_failure_midway_keeps_the_last_valid_version_and_schedule`: a falha de criação do trigger faz o teste falhar (`fail()`), não pular | aprovado, fora da relação de pulados |

**10. M-10 e o privilégio `KILL`.** M-10 foi **executado e aprovado**, não pulado. A conta do banco tem `GRANT USAGE ON *.*` e `GRANT ALL PRIVILEGES ON moodle.*`, sem privilégio global de `KILL` declarado; o servidor, mesmo assim, aceitou `KILL CONNECTION` sobre a conexão de outro processo da mesma conta, que é o que o teste exige (ele só pula se o servidor recusar). Se o servidor recusasse, o teste seria pulado, CR-20 ficaria **não comprovado** e o fechamento dependeria de autorização expressa; a ausência de privilégio nunca é tratada como aprovação.

**11. Limitações e ressalvas.** Ver "Proposta de encerramento da CL-07", item 7 (ressalvas de ambiente, cada uma separada) e a coluna de ressalvas da matriz de rastreabilidade abaixo. Observações desta execução: (a) existem 32 ramos `markTestSkipped` na árvore de testes (fora a fixture `lock_observer`); nas 31 classes executadas, 17 deles estão em 11 classes, e **nenhum foi acionado** (0 skips); (b) a suíte completa `local_assistente_testsuite` (1412 testes) foi iniciada como verificação adicional e **interrompida a pedido do responsável** antes de terminar; **não é usada como evidência**, e os "5 skipped" da execução de 29/10/2026 não foram reproduzidos nem explicados por esta bateria; (c) o aviso "PHPUnit Deprecations: 4088" da execução combinada dos seis grupos antigos (Etapa 2) não aparece em nenhuma execução por classe e continua sem origem identificada.

**12. Resíduos.** Nenhum: sem trigger `cl07*` no esquema, sem worker vivo, sem lock de aplicação em uso, sem arquivo de sinal em `/tmp` e sem alteração no repositório fora da documentação.

**13. Critérios atendidos.** Todos os obrigatórios executados com zero falhas, erros e skips (e zero warnings e notices); saída com `--display-skipped` obtida em todas as execuções; CR-09 comprovado por I-12 na forma forte; invariante I-09 vale em todos os cenários implementados; M-10 executado; seis correções verificadas; regressão executada; ambiente, comandos, resultados e divergências registrados; ressalvas documentadas.

**14. Critérios parcialmente comprovados e não comprovados.** **CR-02 (pares circulares aceitos na instalação): parcialmente comprovado**, por falta de cenário próprio (I-02, complementar, não executado); ver a justificativa em "Evidências históricas de CR-01 e CR-02". *(Superado em 02/11/2026: o I-02 foi executado e o CR-02 passou a Comprovado, com ressalva; ver "Estado do I-02 e avaliação de encerramento".)* CR-22 e CR-23 (monotonicidade, DEC-62), da T144, fora do escopo da CL-07. *(Registro histórico: estado na data desta passagem; a T144 foi concluída depois, em 02/11/2026, e CR-22 e CR-23 foram reclassificados; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)* *(Texto original do início deste item: "Critérios não comprovados. CR-22 e CR-23 (monotonicidade, DEC-62), da T144, fora do escopo da CL-07.")* Os complementares I-01, I-02, I-14, I-16 e U-06 não foram executados (não bloqueiam automaticamente) *(atualização: o I-02 foi executado depois, em 02/11/2026)*. As ressalvas de ambiente não têm evidência de execução, por definição.

**15. Situação da proposta de fechamento.** Preparada, abaixo. **Submetida à decisão do responsável.** A CL-07 não foi fechada e a T020 não foi marcada.

#### Classificação dos CR e matriz de rastreabilidade (CR-01 a CR-21)

`CR → requisito/decisão → antes → depois → cenário e classe::método → evidência concreta → ressalva`. O sinal "=" indica classificação mantida.

| CR | Requisito / decisão | Antes → depois | Cenário e classe::método | Evidência concreta (execução de 02/11/2026) | Ressalva |
|---|---|---|---|---|---|
| 01 | Instalação do XMLDB (spec-002; DEC-29) | Comprovado = | I-16 (complementar, não executado); `install_schema_test` | registro histórico de 29/10/2026 (T013: suíte do plugin executada, `local_assistente` instalado, Behat `plugin_installed.feature`); `install_schema_test` 5/222 nesta etapa | a saída bruta de 29/10 não está no repositório, só o resumo registrado; compara o XML com o inventário, não o esquema físico; I-16 não executado |
| 02 | Pares circulares (DEC-29) | Comprovado → Parcialmente comprovado (rebaixado em 02/11/2026) → **Comprovado, com ressalva** (restabelecido em 02/11/2026 pelo I-02) | I-02 (complementar, **executado**: `install_schema_test::test_circular_pairs_can_be_written_in_order`) | **existente:** `install.xml` declara as duas chaves circulares (linhas 114 e 344) e há registro histórico de instalação bem-sucedida do plugin (T013, 29/10/2026); **ausente:** teste específico de aceitação dos pares circulares | **sem cenário próprio**: a T099 declarou que a instalação das chaves circulares "não foi tocada"; a instalação geral não comprova isoladamente esse comportamento; a evidência direta seria I-02 (não executado) |
| 03 | Integridade do ponteiro (DEC-29, DEC-50, DEC-61) | Parcial → **Comprovado** (aplicação) | I-09 em `material_failure_test`, `material_recovery_test`, `material_concurrency_test::…full_cycle…` | ponteiro nulo ou versão existente, do mesmo material e `completed`, lido do banco antes e depois de cada falha e de cada recuperação e ao fim de 4 processos concorrentes; a verificação reprova nos 2 casos de ponteiro quebrado | nível da aplicação (o banco não impõe a chave: CL-37); `update_details` é a CL-49; monotonicidade é da T144 |
| 04 | Compatibilidade com o Moodle 5.2 | Comprovado = | — | as 31 classes executam no Moodle 5.2.4+ | — |
| 05 | Unicidade de versão no banco (spec-002) | Comprovado = | M-03 (complementar) `material_concurrency_test::…unique_index_refuses…` | sem lock, na primeira rodada os 4 processos calculam a mesma versão: 1 aceita e 3 recusadas por duplicidade | as rodadas seguintes não têm resultado fixo |
| 06 | Relatório atômico (DEC-23, DEC-29, DEC-44) | Comprovado = (agora sem skip) | correção 6; M-05 | `…midway…` aprovado e fora dos pulados; `report_repository_test` 18/69 | exige permissão de `TRIGGER`: a falta reprova |
| 07 | Falha de banco em cada etapa (DEC-61, DEC-50) | Parcial → **Comprovado** | I-03, I-04, I-05 (`material_failure_test`) | trigger `SIGNAL` real em cada `UPDATE`/`INSERT`; fotos das duas tabelas idênticas; transação encerrada; lock livre no servidor; a mesma operação funciona sem a falha; em I-04 o `version` não é consumido | simula a falha de escrita por trigger (não falhas de disco ou rede); MariaDB |
| 08 | Síntese sob lock e transação (DEC-41) | Comprovado = | — (T099, 27/10/2026) | `synthesis_repository_test` 18/57 | evidência anterior mantida |
| 09 | Lock Factory efetiva (DEC-22) | Parcial → **Comprovado** (forma forte) | I-12 (2 testes) | `mysql_lock_factory` diretamente e atrás do `timing_wrapper_lock_factory` com `perfdebug = 8`; `IS_USED_LOCK` da chave devolve a conexão do teste; outra chave livre | outras fábricas não generalizadas |
| 10 | Nomes dos recursos de lock (DEC-15, DEC-20, DEC-23, DEC-41) | Comprovado = | `unit_of_work_test` (nomes); chaves reais em I-12, M-06, M-07 | 12/62 sem falhas | — |
| 11 | Aquisição e liberação (DEC-22, DEC-61) | Parcial → **Comprovado** | M-06 (commit e rollback) | com a unidade aberta o lock é da conexão do worker e a escrita é invisível; após commit e após rollback, com o worker vivo, o lock está livre | — |
| 12 | Tempo limite do lock (DEC-22) | Parcial → **Comprovado** (ressalva) | M-07; correção 1 | `lock_unavailable_exception` com o nome do recurso após o tempo limite de 1 s (asserção: entre 0,9 s e 15 s); nada escrito; funciona depois da liberação | contenção prolongada e com mais de 4 processos não testada |
| 13 | Lock após a morte do processo (plano 003) | Não comprovado → **Comprovado** | M-08 | `SIGKILL` do holder (sinal 9); conexão some; lock livre; outro processo o adquire | — |
| 14 | Concorrência real no mesmo material (DEC-15, DEC-20) | Não comprovado → **Comprovado** (ressalva) | M-01, M-02 (+ controle M-03, M-11) | 4 × 8 `add_version`: 32 versões novas, contíguas, sem duplicata; ciclo completo concorrente com ponteiro válido; os workers se sobrepõem no tempo | M-01 e M-02 não afirmam que algum worker esperou o lock (a corrida real é demonstrada pelo M-03); M-02 não afirma qual versão fica corrente (T144) |
| 15 | Concorrência real no relatório (DEC-23, DEC-44) | Parcial → **Comprovado** (ressalva) | M-05; correção 1 | 4 × 8: 32 versões contíguas, ponteiro na maior; lock do relatório segurado por processo independente | não afirma que um worker esperou o lock |
| 16 | Recursos diferentes sem bloqueio indevido | Não comprovado → **Comprovado** | M-11 | com `material_a` segurado, operações em `material_b` e `report_x` concluem; no mesmo recurso o worker aparece esperando no servidor | — |
| 17 | Versões da síntese (DEC-41) | Comprovado = | — (T099) | evidência anterior mantida | — |
| 18 | Morte com transação aberta (plano 003) | Não comprovado → **Comprovado** | M-09 | `SIGKILL` com escrita não confirmada: nenhuma linha parcial; lock livre; a próxima gravação funciona | — |
| 19 | Recuperação e repetição (DEC-61; CL-52) | Parcial → **Comprovado** (ressalva) | I-06 (C), I-13 (A2), I-15 (B); I-03 (controle) | C: só a troca, sem nova chamada à LLM, sem número novo; A2: mesma versão, contagem inalterada, uma chamada; B: mesma versão, duas chamadas no total | o caso "processo morto" de I-13 reproduz o estado, não mata um processo que rode o serviço; I-15 registra a limitação CL-52; CL-50 e CL-51 não avaliadas |
| 20 | Perda de conexão pelo servidor (plano 003) | Não comprovado → **Comprovado** (ressalva) | M-10 | `KILL CONNECTION` do worker com unidade em curso: falha de conexão no worker, sem estado parcial, retentativa funciona | a exceção devolvida vem do `RELEASE_LOCK` e mascara a falha original (teste exige a família `dml_exception`); depende de o servidor permitir o `KILL` |
| 21 | Execução efetiva, sem skip | Inconclusivo → **Comprovado** (neste ambiente) | execução das 31 classes com `--display-skipped` | 351 testes, 2400 asserções, 0 skips | 32 ramos de skip existem na árvore e nenhum foi acionado; suíte completa não concluída |

CR-22 e CR-23 **não foram reclassificados** (pendentes da T144) *(afirmação da Etapa 6, anterior à T144)*. *(Em 02/11/2026, depois da T144, foram reclassificados: ver "Evidências da T144 sobre CR-22 e CR-23".)*

#### Evidências históricas de CR-01 e CR-02 *(revisão documental de 02/11/2026; só leitura dos registros existentes)*

**Onde estão registradas** (todas no repositório):

- `spec-kit/specs/001-infraestrutura-github-codespaces-e-container-moodle/tasks.md`, **T013** (linha 51): em 29/10/2026 a suíte `local_assistente_testsuite` foi executada no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13 (OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped por travas reentrantes no mesmo processo, 4 notices); o Behat `assistente/tests/behat/plugin_installed.feature` (1 cenário, 3 passos) foi aprovado; `/var/www/html/public/local/assistente` é link simbólico para `assistente/` e `local_assistente` aparece instalado. O mesmo registro está repetido em **T012** e **T019** de `spec-kit/specs/002-esquema-xmldb/tasks.md`.
- `spec-kit/closure-register.md`: **CL-01** (o reconhecimento do plugin, Q2/T013, "já registrado em 29/10/2026") e **CL-22** (runners); **CL-07**, nota "MATRIZ T099" (27/10/2026), que diz que "a instalação das chaves circulares é da T020 e não foi tocada"; **CL-37** (fechada em 28/10/2026), que confirmou neste banco que as chaves `foreign` do XMLDB não são impostas pelo banco.
- Commit `9952371` ("add db/upgrade.php (T019) and the minimal Behat feature (T013)") e `assistente/db/install.xml`, linhas 114 (`material.current_version` → `material_version.id`) e 344 (`report.current_version` → `report_version.id`), que são as duas chaves circulares.

**CR-01 (instalação do XMLDB).** A evidência é o registro de 29/10/2026 acima. Esta revisão não a reproduziu: a saída bruta daquela execução não está no repositório, apenas o resumo registrado em T013, T012, T019 e CL-01. Como o Moodle cria as tabelas do banco de teste a partir do `install.xml`, todas as classes executadas na Etapa 6 operam sobre as 21 tabelas criadas por ele, o que é compatível com o registro, mas **não é um cenário próprio**. A comparação do esquema físico com o `install.xml` é I-16 (complementar, não executado).

**CR-02 (pares circulares aceitos na instalação): reclassificado de Comprovado para Parcialmente comprovado (02/11/2026, por autorização do responsável, exclusivamente documental).** **Não existe cenário próprio nem registro dedicado**; o motivo da reclassificação é a insuficiência de evidência direta. A justificativa distingue três coisas:

- **Evidência existente.** (a) O `assistente/db/install.xml` declara as duas chaves circulares, nas linhas 114 (`material.current_version` → `material_version.id`) e 344 (`report.current_version` → `report_version.id`). (b) Há registro histórico de instalação bem-sucedida do plugin e de execução da suíte sobre as tabelas criadas a partir do `install.xml` (T013, 29/10/2026).
- **Evidência ausente.** Nenhum teste específico demonstrou a aceitação dos pares circulares na instalação (I-02, complementar, **não executado**). A instalação geral do plugin não comprova isoladamente esse comportamento. A T099 declarou expressamente que a instalação das chaves circulares "não foi tocada" e que ela é da T020.
- **Inferência (não é comprovação experimental).** Como as chaves `foreign` do XMLDB criam apenas índices e não restrições no banco (CL-37), esperar-se-ia que a circularidade não imponha ordem de criação. Essa expectativa decorre da leitura do XML e do comportamento esperado das chaves e **não substitui** um teste; ela não é apresentada como teste específico.

Histórico: a classificação anterior era **Comprovado** (mantida da auditoria, sem cenário próprio reexecutado na Etapa 6; marcada como "evidência indireta" na revisão documental complementar de 02/11/2026); passou a **Parcialmente comprovado** por insuficiência de evidência direta. Não houve execução de I-02 nem de qualquer outro teste nesta reclassificação. Os demais CR não foram afetados. *(Texto original deste parágrafo, anterior à reclassificação: "Não existe cenário próprio nem registro dedicado. O que sustenta a classificação é indireto: […]. Classificação mantida ("Comprovado"), agora marcada como evidência indireta. Se o responsável preferir tratar CR-02 como "Parcialmente comprovado" até I-02 ser executado, a mudança é só de classificação, sem efeito sobre os demais CR; a decisão é dele.")* **Atualização de 02/11/2026: o I-02 foi executado, a lacuna de evidência direta apontada acima foi preenchida e o CR-02 foi restabelecido como Comprovado, com ressalva; ver "Estado do I-02 e avaliação de encerramento".**

#### Cenários que sustentam mais de um CR

- **I-03**: CR-07 (falha na troca) e CR-19 (a mesma troca funciona sem a falha).
- **I-09**: CR-03; verificada em M-02 (que fecha CR-14), em I-03/I-04/I-05 (CR-07) e em I-06/I-13/I-15 (CR-19).
- **M-02**: CR-14 e, pela verificação de I-09, CR-03.
- **Correção 1**: CR-12 e CR-15. **Correção 6**: CR-06 e CR-07.
- **Correções 1 a 6 em conjunto** (matriz): CR-07, 11, 12, 15 e 19.
- **I-12**: CR-09, e dá a chave de lock real usada na verificação de CR-10, 11 e 12.

#### Evidências complementares existentes, sem reexecução própria (não implementadas nesta etapa)

I-01, I-14, I-16 e U-06 (complementares, não executados); I-02 (complementar, **executado em 02/11/2026**); T144 (U-07 a U-12, M-12, M-13, S-01, Q17) *(não executada na data da proposta; a T144 foi concluída depois, em 02/11/2026: ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento)*; CL-37; CL-49; CL-50; CL-51; CL-52.


### Proposta de encerramento da CL-07 — submetida à decisão do responsável

*(Decisão tomada em 02/11/2026: o responsável autorizou expressamente o encerramento da CL-07 e a conclusão da T020; ver "Encerramento formal da CL-07 e conclusão da T020". O texto da proposta é preservado abaixo como foi apresentado; onde diz "submetida", "aguarda" ou "depende de autorização", vale para a data dele.)*

**1. Escopo efetivamente validado.** Persistência, transações, integridade do ponteiro, locks, concorrência real, recuperação, repetição segura, falhas de gravação e evidência de execução (CR-01 a CR-21 da T020), no PHPUnit, nas tabelas de teste, com MariaDB 11.4.13, `GET_LOCK` e processos da mesma máquina.

**2. CR comprovados.** *(Atualizado em 02/11/2026 depois do I-02: CR-01 a CR-21, **21 de 21**, com o CR-02 comprovado com ressalva; texto vigente até então, abaixo.)* CR-01 e CR-03 a CR-21: **20 dos 21**. Treze foram reclassificados nesta etapa (03, 07, 09, 11, 12, 13, 14, 15, 16, 18, 19, 20 e 21); sete mantêm a classificação anterior (01, 04, 05, 06, 08, 10 e 17), sem cenário próprio reexecutado para 01, 08 e 17. Seis têm ressalva específica (12, 14, 15, 19, 20 e 21). *(Texto original deste item: "CR-01 a CR-21: 21 de 21. […] oito mantêm a classificação anterior (01, 02, 04, 05, 06, 08, 10 e 17), sem cenário próprio reexecutado para 01, 02, 08 e 17." Corrigido em 02/11/2026: o CR-02 foi rebaixado a Parcialmente comprovado.)*

**3. CR parciais ou não comprovados.** *(Atualizado em 02/11/2026: depois do I-02 não há CR parcial entre CR-01 e CR-21; o texto vigente até então segue.)* **CR-02 (pares circulares aceitos na instalação): parcialmente comprovado**, sem cenário próprio (I-02, complementar, não executado); é o único entre CR-01 e CR-21. CR-22 e CR-23 (DEC-62) continuam **não comprovados**, são da T144 e **não bloqueiam** a CL-07. *(Registro histórico: estado na data desta passagem; a T144 foi concluída depois, em 02/11/2026, e CR-22 e CR-23 foram reclassificados; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)*

**4. Cenários obrigatórios aprovados.** I-03, I-04, I-05, I-06, I-09, I-12, I-13, I-15, M-01, M-02, M-05, M-06, M-07, M-08, M-09, M-10 e M-11, mais as seis correções dos testes existentes. Zero falhas, erros e skips.

**5. Resultado agregado.** 31 classes, 351 testes, 2400 asserções, zero falhas, erros, skips, warnings e notices (Estado da Etapa 6, item 7). *(Depois da Etapa 6, `install_schema_test` passou de 5 testes e 222 asserções para 6 e 232, com o I-02. Somado, o conjunto equivale a 352 testes e 2410 asserções; **só essa classe e seis vizinhas foram reexecutadas** depois do I-02, e as outras 24 classes mantêm os números da Etapa 6, que não foram obtidos numa nova execução completa.)*

**6. Evidências das Etapas 2 a 5.** Etapa 2: infraestrutura multiprocesso e I-12, M-01, M-02, M-05 a M-11 (13 testes, 217 asserções). Etapa 3: linha de base e seis correções. Etapa 4: I-03, I-04, I-05 e I-09 (6 testes, 167 asserções). Etapa 5: I-06, I-13 e I-15 (5 testes, 189 asserções). Cada uma está registrada na sua seção de estado, aprovada pelo responsável em 02/11/2026.

**7. Ressalvas de ambiente, cada uma separada** (nenhuma tem evidência de execução; nenhuma é dada como comprovada): (a) recuperação de queda do InnoDB (crash recovery); (b) reinicialização do MariaDB; (c) isolamento transacional diferente de `REPEATABLE-READ`; (d) vários servidores web contra o mesmo banco; (e) outras fábricas de lock; (f) outros bancos (PostgreSQL, SQL Server, Oracle). A tabela "Ressalvas de ambiente" desta seção detalha o limite de cada uma. Ressalvas desta execução: o caso "processo morto" de I-13 é por estado equivalente; a exceção de M-10 mascara a falha original; M-01, M-02 e M-05 não afirmam espera pelo lock; a suíte completa não foi concluída.

**8. CL-50, CL-51 e CL-52.** Riscos independentes, **não alterados** e fora dos critérios de fechamento. CL-52 é **limitação aceita** pelo responsável (I-15 a registra e não a transforma em garantia). CL-50 (versão `pending` obsoleta) e CL-51 (sem reconciliação depois de esgotar as tentativas) continuam **abertas e não avaliadas** por estes testes.

**9. CL-37, CL-49 e T144.** Fora do escopo e **não resolvidas**. CL-37 (chaves não impostas pelo banco) e CL-49 (`update_details` sem o lock do material) continuam independentes. T144 (monotonicidade de `current_version`, DEC-62; U-07 a U-12, M-12, M-13, S-01, Q17) continua `[ ]`, aberta e independente. *(Registro histórico: estado na data desta passagem; a T144 foi concluída depois, em 02/11/2026, e CR-22 e CR-23 foram reclassificados; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)*

**10. Pendências que condicionam o encerramento.** (a) A decisão do responsável sobre fechar a CL-07 e marcar a T020. (b) Os complementares I-01, I-14, I-16 e U-06 não foram executados (o I-02 foi executado em 02/11/2026; texto anterior: "I-01, I-02, I-14, I-16 e U-06 não foram executados"): não bloqueiam automaticamente, mas I-16 (esquema físico contra o `install.xml`) é a única evidência que cobre a diferença entre o XML e o banco, e I-02 (aceitação dos pares circulares) era a evidência direta que faltava ao CR-02 *(executado em 02/11/2026; deixou de ser pendência)*. (c) As ressalvas de ambiente precisam ser aceitas como ressalvas. (d) Os três commits locais ainda não foram enviados (assunto à parte do fechamento).

**11. Justificativa.** **Propõe-se o fechamento.** Todos os "Critérios de fechamento" da matriz estão atendidos: todos os obrigatórios executados com zero falhas, erros e skips; saída com `--display-skipped` obtida; CR-09 por I-12 na forma forte; invariante I-09 válida; ambiente, comandos, resultados e divergências registrados; ressalvas documentadas; M-10 executado (não é preciso invocar a regra do CR-20 não comprovado). Os pontos de julgamento do responsável são: aceitar CR-19 como comprovado com a ressalva do "processo morto" por estado equivalente; aceitar o CR-02 como comprovado com a ressalva descrita em "Estado do I-02 e avaliação de encerramento" *(texto anterior: "aceitar o CR-02 como parcialmente comprovado, até que haja evidência direta (por exemplo, I-02)")*; aceitar que os complementares não executados não bloqueiam; e aceitar as ressalvas de ambiente.

**12. Decisão que depende de autorização expressa do responsável.** Fechar a CL-07 (hoje "Aguardando revisão", validação técnica pendente); marcar a T020 como concluída; aceitar as ressalvas e os itens fora do escopo acima. Nada disso foi feito. Em particular, **se M-10 não puder ser executado em outro ambiente, CR-20 permanece não comprovado e o fechamento naquele ambiente depende de autorização expressa**.

**Distinção.** *Critérios comprovados:* CR-01 a CR-21 (com as ressalvas citadas; o CR-02 desde 02/11/2026, pelo I-02). *Parcialmente comprovado:* nenhum entre CR-01 e CR-21 *(texto anterior: "CR-01 e CR-03 a CR-21 […]. Parcialmente comprovado: CR-02")*. *Não comprovados:* CR-22 e CR-23 (T144). *(Registro histórico: estado na data desta passagem; a T144 foi concluída depois, em 02/11/2026, e CR-22 e CR-23 foram reclassificados; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)* *Limitações aceitas:* CL-52. *Riscos independentes:* CL-50, CL-51, CL-37, CL-49. *Fora do escopo:* T144 e Q17. *Exigem autorização expressa:* o fechamento da CL-07, a marcação da T020 e a aceitação das ressalvas.


### Revisão documental complementar da Etapa 6 *(02/11/2026)*

Revisão **exclusivamente documental e de leitura**, pedida pelo responsável depois da Etapa 6. Não houve execução de PHPUnit, alteração de código de produção, de testes ou de fixtures, nem tentativa de reconstruir registros perdidos. **A CL-07 e a T020 continuam abertas**; a T144 não foi tocada *(na data desta revisão; concluída depois, em 02/11/2026: ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento)*; nenhuma decisão de fechamento foi tomada. Alterações e justificativas:

1. **Regressão sem ambiguidade** (item 7 e 8 de "Estado da Etapa 6"). A frase antiga dizia que as 18 classes de `infrastructure/persistence` eram "as 5 acima que são dessa pasta mais as 13 restantes". Isso estava errado: só 4 das 5 classes novas ficam nessa pasta (`material_recovery_test` fica em `application/material`), e as 18 se dividem em 4 (grupo A) + 3 (grupo B) + 11 (grupo C). A enumeração antiga também repetia classes dos grupos A e B. Passou a valer a divisão A (5 classes, 24/573) + B (5 classes, 86/612) + C (21 classes, 241/1215) = 31 classes, 351 testes, 2400 asserções, sem sobreposição. Os números não mudaram.
2. **Evidências históricas de CR-01 e CR-02** (subseção própria, acima). Para CR-01, as fontes do registro de 29/10/2026 foram localizadas. Para CR-02, **não há cenário próprio**; a classificação tinha sido mantida e marcada como evidência indireta, e foi **depois rebaixada para Parcialmente comprovado** (reclassificação documental de 02/11/2026, autorizada pelo responsável). *(Texto original deste item: "a classificação foi mantida e marcada como evidência indireta, e a decisão de rebaixá-la até I-02 ser executado fica com o responsável.")*
3. **Leitura do método M-10 e dos helpers** (abaixo). Conclusão: a classificação de CR-20 como "Comprovado, com ressalva" é adequada ao que o teste verifica; a ressalva é mais específica do que estava escrita.
4. **Registro dos relatórios integrais** (abaixo).

#### Leitura do M-10 (CR-20), sem alteração de código

Lidos: `unit_of_work_concurrency_test::test_a_connection_killed_by_the_server_fails_cleanly_and_the_work_can_be_repeated`, o modo `write_and_hold` de `fixtures/queue_worker.php` e, em `fixtures/lock_observer.php`, `spawn_actor`, `finish_actor`, `lock_owner`, `lock_key`, `await`, `kill_connection`, `kill_was_denied`, `await_connection_gone`, `await_lock_free` e `signal_file`.

**O que o teste faz.** (1) Inicia um worker que abre uma unidade de trabalho (lock `material_<id>` e transação), escreve uma versão, avisa por arquivo de sinal e espera dentro da unidade. (2) Confirma pelo servidor (`IS_USED_LOCK`) que a conexão do worker é a dona do lock. (3) A conexão do teste executa `KILL CONNECTION <id>`, e o `<id>` vem do marcador do próprio worker (`CONNECTION_ID()`), então o alvo é inequívoco e o `KILL` ocorre com o lock tomado e a escrita não confirmada. (4) Espera a conexão sumir do `PROCESSLIST` e o lock ficar livre. (5) Libera o worker, que faz um `SELECT 1` e falha; o teste exige que o erro devolvido seja de uma classe que seja ou herde `dml_exception`. (6) Afirma que nenhuma linha do worker restou e que `add_version` no teste funciona e devolve `version = 1`, o mesmo número da tentativa interrompida.

**O que isso prova para o CR-20.** O servidor encerra a conexão de um processo com unidade aberta; o lock é liberado com a conexão; a escrita não confirmada desaparece; o processo falha por uma exceção da camada de banco, não por travamento; a mesma operação repetida funciona, com o mesmo número. O teste **pula** só se o servidor recusar o `KILL` com uma das três mensagens reconhecidas ("not owner of thread", "access denied", "you need"); qualquer outro erro do `KILL` **falha** o teste. Na Etapa 6 ele foi executado, não pulado.

**O que o teste não prova (ressalvas específicas de CR-20).**

- **Qual comando falhou no worker.** A exceção devolvida pode vir do `SELECT 1`, mas também do `ROLLBACK` (na captura) ou do `RELEASE_LOCK` (no `finally`) que `unit_of_work::run` tenta sobre a conexão encerrada; o que sai é a exceção que escapa de `run()`, e ela pode mascarar a falha original. O teste não distingue qual foi.
- **A natureza exata do erro.** Só exige a família `dml_exception`; não confere o texto nem o código do servidor (por exemplo "server has gone away"). A asserção é **fraca quanto ao tipo do erro e forte quanto ao estado resultante** (nada parcial, lock livre, retentativa funcionando).
- **Alcance do `KILL`.** É o encerramento de **uma** conexão pelo servidor **em funcionamento**; não cobre queda ou reinício do MariaDB (ressalvas de ambiente).
- **Privilégio.** A conta não tem `KILL` global, mas o MariaDB permite encerrar threads da mesma conta, que é o caso testado. O resultado não se generaliza a outra configuração de contas; nesse caso o teste seria pulado e CR-20 ficaria não comprovado.
- **Conteúdo do registro bruto.** A nota `[sinal] M-10: the worker failed with …`, que o teste imprime com a falha do worker, estava na saída bruta da execução, que se perdeu (ver abaixo); por isso a mensagem exata não está registrada neste documento.

**Conclusão sobre CR-20.** A classificação "Comprovado, com ressalva" é sustentada pelo que o teste verifica, desde que a ressalva leia como acima. Endurecer o teste (conferir o código de erro do servidor, ou a origem da exceção) seria alteração de teste e está fora do escopo desta revisão.

#### Registro dos relatórios integrais

- **O que existe.** Os números de cada execução estão transcritos nas seções de estado deste documento, classe por classe (testes, asserções, falhas, erros, skips, warnings, notices e tempo, na Etapa 6; testes e asserções nas Etapas 3 a 5), com o comando, o ambiente e o resultado. É o registro auditável que o repositório tem.
- **O que se perdeu.** As saídas brutas do PHPUnit das Etapas 3 a 6 (inclusive as duas execuções finais de 31 classes) foram gravadas numa pasta temporária da sessão, fora do repositório, que foi apagada. **Não foram reconstruídas nem reescritas**: reexecutar a bateria para recriá-las não foi autorizado por esta revisão, e uma reexecução produziria registros novos, não os originais. O patch completo da Etapa 6, também guardado ali, igualmente se perdeu; o conteúdo dele está nestes três documentos, e o histórico git contém os commits das Etapas 3 a 5.
- **Frases corrigidas.** A frase da Etapa 3 que dizia que os relatórios completos estavam no scratchpad foi anotada com esta correção.
- **Sugestão, sem efeito automático.** Se o responsável quiser relatórios brutos duráveis, uma reexecução autorizada poderia gravá-los num diretório do repositório, na convenção de `spec-kit/validation/` (por exemplo `spec-kit/validation/cl07/`); essa pasta não foi criada.


### Estado do I-02 e avaliação de encerramento *(02/11/2026)*

Esta seção registra a execução do **I-02** (comprovação pendente do CR-02) e a avaliação das condições de encerramento da CL-07. **A CL-07 e a T020 continuam abertas**: o fechamento e a marcação da T020 dependem de autorização expressa do responsável, que não foi dada. *(Superado no mesmo dia: a autorização expressa foi dada e a CL-07 foi encerrada; ver "Encerramento formal da CL-07 e conclusão da T020".)* Não houve alteração de código de produção, de XMLDB, de fixtures, de DEC-61, DEC-62, T144, CL-37, CL-49, CL-50, CL-51 ou CL-52.

#### 1. Definição original do I-02

- **Auditoria da CL-07 (primeira versão):** `test_circular_pairs_can_be_written_in_order_and_the_database_does_not_enforce_them`, em `install_schema_test`: inserir material com ponteiro `NULL`, a versão e o ponteiro, e remover a versão com ponteiro ativo; esperado: a gravação em ordem funciona e o banco **não** impede a remoção (caracteriza a premissa da CL-37); CR-02 e CR-03.
- **Versão congelada na matriz (a vigente, seção B):** "só a ordem de gravação do par circular, **sem** fixar a semântica de exclusão de versões, que é da CL-37". A verificação de remoção foi retirada porque fixaria a semântica de exclusão. A matriz liga o I-02 ao CR-02; o CR-03 ficou com o I-09.
- **Critério do CR-02:** "Pares circulares aceitos na instalação".

O I-02 foi implementado **conforme a versão vigente**, no arquivo e na classe que a definição original indicava, e **não** verifica a exclusão de versões.

#### 2. Inventário das evidências, por tipo

| Tipo | Evidência | O que comprova | Limite |
|---|---|---|---|
| **Teste unitário (só lê o XML, não toca o banco)** | `install_schema_test` Q2, Q3 e Q5 (contagens, índices únicos, ausência de campos fora do inventário) | o `install.xml` declara as chaves circulares e bate com o inventário | **não** prova nada sobre o banco físico |
| **Teste de integração, banco real (Moodle DML)** | `install_schema_test` Q1 `test_exactly_21_tables_are_created` (`$DB->get_tables`); Q4 (índice único recusa duplicata) | as 21 tabelas existem fisicamente; o banco aplica os índices únicos | não olha as colunas do ponteiro |
| **Teste de integração, banco real (novo, I-02)** | `install_schema_test::test_circular_pairs_can_be_written_in_order` | as colunas `current_version` existem fisicamente e aceitam `NULL` (`$DB->get_columns`); nos dois pares, grava-se a linha sem ponteiro, depois a versão, depois o ponteiro, e as três leituras conferem | só a ordem de gravação; não toca a exclusão (CL-37) nem compara o esquema físico inteiro com o XML (I-16) |
| **Consulta direta ao banco (`information_schema`, 02/11/2026, só leitura, não persistida como teste)** | tabelas, colunas, índices e restrições dos dois pares, no prefixo de teste `test_` e no do site `mdl_` | ver o resultado abaixo | é uma fotografia do estado de hoje; não prova como o estado foi criado |
| **Registro histórico** | T013 (29/10/2026): plugin instalado, suíte do plugin executada, Behat `plugin_installed.feature`; CL-37: premissa de que o banco não impõe as chaves foi confirmada ("0 chaves estrangeiras impostas") | instalação anterior bem-sucedida | a saída bruta não está no repositório; só o resumo |
| **Inferência** | as chaves `foreign` do XMLDB criam apenas índices | explica por que a circularidade não impõe ordem de criação | **não** substitui a consulta nem o teste; é confirmada por eles, não presumida |

#### 3. Execução

**Teste novo.** `vendor/bin/phpunit --colors=never --display-skipped --display-warnings --display-notices --testdox public/local/assistente/tests/db/install_schema_test.php`, a partir de `/var/www/html`. Resultado: **6 testes, 232 asserções, 0 falhas, 0 erros, 0 skips, 0 warnings e 0 notices** (antes: 5 testes e 222 asserções; o I-02 acrescentou 1 teste e 10 asserções). Repetido mais duas vezes, com o mesmo resultado. Nenhuma linha do I-02 ficou nas tabelas de teste (lido depois), e o teste se desfaz pela reversão do próprio PHPUnit.

**Vizinhas reexecutadas** (usam as mesmas tabelas): `material_repository_test` 23/117, `report_repository_test` 18/69, `material_failure_test` 6/167, `material_concurrency_test` 3/119, `report_concurrency_test` 1/15, `material_recovery_test` 5/189; todas sem falhas, erros, skips, warnings ou notices.

**Consultas diretas** (MariaDB 11.4.13, banco `moodle`; `information_schema`, somente `SELECT`), nos dois prefixos, com o mesmo resultado:

- as quatro tabelas dos dois pares existem (InnoDB), e há **21** tabelas `local_assistente_*` em cada prefixo;
- `material.current_version` e `report.current_version`: `bigint(10)`, **anuláveis**, default `NULL`; `material_version.material_id` e `report_version.report_id`: `bigint(10)`, não anuláveis;
- índices: um índice não único em cada `current_version`; um único e um não único em `material_version(material_id)` e em `report_version(report_id)`;
- restrições físicas dessas quatro tabelas: apenas `PRIMARY KEY` e `UNIQUE`; **0 restrições `FOREIGN KEY`** em qualquer tabela `local_assistente_*` e **0** `REFERENTIAL_CONSTRAINTS`.

#### 4. O que o I-02 comprovou, o que não comprovou, e as limitações

- **Comprovou:** o banco criado pela instalação tem as quatro tabelas dos dois pares circulares, a coluna do ponteiro é física e anulável, existem os índices correspondentes, o banco não impõe nenhuma restrição de chave estrangeira entre elas, e a gravação na ordem "linha sem ponteiro, versão, ponteiro" funciona nos dois pares.
- **Não comprovou:** a semântica de exclusão (CL-37, fora do I-02); a comparação campo a campo do esquema físico com o `install.xml` (I-16, não executado); o comportamento em outros bancos; a ordem inversa de gravação (fora da definição).
- **Limitações.** (a) A *instalação* é comprovada pelo **estado físico resultante**, que existe tanto no banco de teste (criado pela inicialização do PHPUnit, que usa o instalador XMLDB) quanto no do site; **não** foi feita uma nova instalação limpa nesta etapa, e não era necessária nem foi tocado nenhum banco compartilhado. (b) As consultas são uma fotografia de hoje, não persistida como teste. (c) MariaDB 11.4.13 apenas. (d) A saída bruta do histórico de 29/10/2026 continua fora do repositório.

#### 5. Situação final do CR-02

**Comprovado, com ressalva.** Critério: "pares circulares aceitos na instalação". A evidência atende ao critério porque combina três coisas independentes da inferência: o **estado físico** do banco criado pela instalação (consulta direta, nos dois prefixos), o **teste de integração** que lê as colunas reais e grava na ordem nos dois pares, e o **registro histórico** da instalação. Não se está tratando "ausência de erro" como prova: a conclusão se apoia em tabelas, colunas, índices e restrições efetivamente lidos do banco. **Ressalva:** a aceitação *na instalação* é demonstrada pelo resultado dela, não pela observação de uma nova instalação limpa; isso não muda a classificação, mas fica registrado. Histórico: Comprovado (auditoria) → Parcialmente comprovado (revisão documental de 02/11/2026) → Comprovado, com ressalva (I-02, 02/11/2026).

#### 6. Situação dos CR-01 a CR-21 depois do I-02

**21 de 21 comprovados**, com as ressalvas já registradas por CR (12, 14, 15, 19, 20 e 21 e agora também o 02). CR-01 recebe, além do histórico de 29/10/2026, a evidência direta de hoje: as 21 tabelas existem no banco do site e no de teste. **Nenhum outro CR foi reclassificado.** CR-22 e CR-23 não fazem parte da CL-07 e continuam da T144. *(Registro histórico: estado na data desta passagem; a T144 foi concluída depois, em 02/11/2026, e CR-22 e CR-23 foram reclassificados; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)*

#### 7. Avaliação das condições de encerramento (seção "Critérios de fechamento")

| Condição | Situação |
|---|---|
| Todos os obrigatórios executados, com zero falhas, erros e skips | **Atendida.** Etapa 6: 31 classes, 351 testes, 2400 asserções; depois do I-02, as classes afetadas e as vizinhas foram reexecutadas |
| Saída com `--display-skipped` registrada | **Atendida na forma registrada** (comando, ambiente e números por classe nas seções de estado) |
| CR-09 comprovado por I-12 na forma forte | **Atendida** |
| Invariante I-09 vale | **Atendida** (I-03, I-04, I-05, I-06, I-13, I-15 e M-02) |
| Ambiente, comandos, resultados e divergências registrados | **Atendida** |
| Ressalvas documentadas (CR-22 e CR-23, CL-49, CL-37, isolamento, queda do InnoDB, reinício do MariaDB, vários servidores, outras fábricas de lock, outros bancos) | **Atendida** *(registro correspondente à data do fechamento da CL-07, 02/11/2026; CR-22 e CR-23 foram reclassificados depois, pela T144)* |
| M-10 executado (senão CR-20 não comprovado e autorização expressa) | **Atendida**: executado e aprovado |
| T020: instalação com as chaves circulares; relatório atômico; três etapas do material com falha provocada; recuperação; concorrência real; locks; encerramento abrupto e perda de conexão | **Atendida**: CR-02 (I-02), correção 6 e M-05, I-03/I-04/I-05, I-06/I-13/I-15, M-01/M-02, M-06 a M-11 |
| Separação CL-07 e T144 (CR-22, CR-23, DEC-62 e Q17 fora) | **Mantida** *(registro correspondente à data do fechamento da CL-07, 02/11/2026; a separação continua: a T144 foi concluída fora da CL-07)* |

**Pontos que o responsável deve pesar** (não são condições descumpridas, mas julgamento): (i) a saída bruta das execuções de 29/10/2026 e das Etapas 3 a 6 **não está preservada**; o registro é o que está transcrito nestes documentos (ver "Registro dos relatórios integrais"); se o responsável exigir os relatórios brutos como parte da condição "saída registrada", seria preciso uma nova execução com gravação durável, não autorizada; (ii) CR-19 comprovado com a ressalva do "processo morto" por estado equivalente; (iii) CR-20 com a ressalva de que o teste não distingue qual comando falhou no worker; (iv) a classe `install_schema_test` e as 24 classes não reexecutadas depois do I-02 mantêm os números da Etapa 6; (v) os complementares I-01, I-14, I-16 e U-06 não foram executados e não bloqueiam; (vi) as ressalvas de ambiente não têm evidência de execução; (vii) CL-50, CL-51 e CL-52 permanecem como limitação aceita e riscos independentes, fora dos critérios.

#### 8. Parecer técnico

As condições de encerramento originalmente estabelecidas estão **satisfeitas** no que a matriz define, com o CR-02 agora comprovado por evidência direta. **Propõe-se o encerramento da CL-07 e a marcação da T020**, sujeitos aos julgamentos listados no item 7 e à **autorização expressa** exigida pela própria matriz. O parecer é consequência das evidências: se qualquer um dos pontos do item 7 for considerado condição, e não ressalva, o encerramento fica pendente exatamente nele.

#### 9. Situação da T020 e da CL-07

**Abertas.** A T020 continua `[ ]` e a CL-07 continua "Aguardando revisão" no registro de encerramento. Nada foi marcado nem fechado. *(Superado no mesmo dia: depois da autorização expressa do responsável, a T020 foi marcada `[x]` e a CL-07 foi encerrada; ver "Encerramento formal da CL-07 e conclusão da T020".)* A T144 continua `[ ]`, aberta e independente. *(Registro histórico: estado na data desta passagem; a T144 foi concluída depois, em 02/11/2026, e CR-22 e CR-23 foram reclassificados; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)*


### Encerramento formal da CL-07 e conclusão da T020 *(02/11/2026)*

**Decisão.** Por **autorização expressa do responsável, datada de 02/11/2026**, a **CL-07 foi encerrada** (Fechado, validação técnica, **com ressalvas**) e a **T020 foi marcada como concluída**. É um fechamento documental: não houve execução de testes, alteração de código de produção, de testes, de XMLDB ou de fixtures, nem commit ou push. Esta seção é o registro de fechamento previsto na matriz; as seções anteriores (Estados das Etapas 2 a 6, do I-02 e a Proposta de encerramento) continuam como foram escritas, e as frases delas que dizem que a CL-07 e a T020 estão abertas valem para a data de cada uma.

#### 1. Fundamento

As condições da seção "Critérios de fechamento" foram satisfeitas: todos os obrigatórios executados com zero falhas, erros e skips; a saída com `--display-skipped` registrada (comando, ambiente e números por classe, nas seções de estado); CR-09 comprovado por I-12 na forma forte; invariante I-09 válida; ambiente, comandos, resultados e divergências registrados; ressalvas documentadas; M-10 executado, de modo que não se aplicou a regra do CR-20 não comprovado. O I-02 foi implementado e aprovado, e o CR-02 passou a Comprovado, com ressalva. O parecer técnico está em "Estado do I-02 e avaliação de encerramento", item 8.

#### 2. Estado final dos critérios

**CR-01 a CR-21: 21 de 21 comprovados**, cada um com a ressalva individual registrada na tabela "Critérios" e na matriz de rastreabilidade (nada foi removido): CR-02 (sem nova instalação limpa), CR-03 (nível da aplicação), CR-09 (forma forte, neste ambiente), CR-12, CR-14, CR-15, CR-19, CR-20 e CR-21. **Histórico preservado do CR-02:** Comprovado (auditoria) → Parcialmente comprovado (revisão documental de 02/11/2026, por falta de evidência direta) → Comprovado, com ressalva (I-02, 02/11/2026). CR-22 e CR-23 não fazem parte da CL-07.

#### 3. Resultados e evidências preservados

| Etapa | O que ficou registrado |
|---|---|
| 2 | infraestrutura multiprocesso; I-12, M-01, M-02, M-05 a M-11 (13 testes, 217 asserções); regressão de 58 testes e 513 asserções |
| 3 | linha de base e as seis correções dos testes existentes |
| 4 | I-03, I-04, I-05 e I-09 (6 testes, 167 asserções) |
| 5 | I-06, I-13 e I-15 (5 testes, 189 asserções) |
| 6 | reexecução final: 31 classes, 351 testes, 2.400 asserções, 0 falhas, erros, skips, warnings e notices; M-10 executado |
| I-02 | `install_schema_test`: 6 testes, 232 asserções, sem falhas, erros, skips, warnings ou notices; consultas diretas ao `information_schema` |

**Distinção entre o acumulado e o que foi reexecutado por último.** O total acumulado de **352 testes e 2.410 asserções** é a soma dos 351 testes e 2.400 asserções da reexecução da Etapa 6 com o teste e as 10 asserções que o I-02 acrescentou. **As 31 classes não foram reexecutadas juntas depois do I-02**: depois dele foram reexecutadas só `install_schema_test` e seis classes vizinhas (`material_repository_test`, `report_repository_test`, `material_failure_test`, `material_concurrency_test`, `report_concurrency_test` e `material_recovery_test`). As outras 24 classes mantêm os números da Etapa 6.

#### 4. Ressalvas e limitações preservadas (nenhuma foi removida ou atenuada)

1. **CR-02.** A comprovação se baseia no **estado físico observado** no banco (consulta direta nos dois prefixos) e no **teste de integração** do I-02, **sem uma nova instalação limpa**.
2. **CR-19.** O "processo morto" de I-13 é representado por **estado equivalente**; não se matou um processo que rodasse o serviço (as fixtures não rodam o serviço).
3. **CR-20.** O teste de M-10 **não distingue exatamente qual comando falhou no worker**; exige a família `dml_exception` e verifica o estado resultante.
4. **Perda das saídas brutas históricas.** As saídas brutas de 29/10/2026 e das Etapas 3 a 6 **não foram integralmente preservadas**; o registro auditável é o que está transcrito nos documentos. Não foram reconstruídas.
5. **Total acumulado** de 352 testes e 2.410 asserções, **sem** afirmar que as 31 classes foram reexecutadas juntas depois do I-02 (item 3 acima).
6. **Complementares não executados:** I-01, I-14, I-16 e U-06. Não bloqueiam, conforme a matriz. O I-16 é a única evidência que compararia o esquema físico inteiro com o `install.xml`.
7. **CL-50, CL-51 e CL-52** permanecem como **riscos independentes e limitação aceita** (CL-52). O encerramento não transforma nenhuma delas em comprovação.
8. **Ressalvas de ambiente**, cada uma sem evidência de execução: recuperação de queda do InnoDB; reinicialização do MariaDB; isolamento transacional diferente de `REPEATABLE-READ`; vários servidores web; outras fábricas de lock; outros bancos.
9. **Alcance:** MariaDB 11.4.13, `GET_LOCK`, processos da mesma máquina, nas tabelas de teste. O encerramento não generaliza o resultado.

#### 5. Separação de escopo

- A **T144 permanece aberta** (monotonicidade de `current_version`, DEC-62; U-07 a U-12, M-12, M-13, S-01 e Q17). CR-22 e CR-23 continuam fora da CL-07. *(Registro histórico: estado na data desta passagem; a T144 foi concluída depois, em 02/11/2026, e CR-22 e CR-23 foram reclassificados; ver "Evidências da T144 sobre CR-22 e CR-23" ao fim deste documento.)*
- **DEC-61 e DEC-62** não foram alteradas.
- **CL-37, CL-49, CL-50, CL-51 e CL-52 não foram encerradas.**
- As linhas de decisão do registro que diziam "Aguardando revisão (VT: CL-07)" (DEC-20, DEC-23, DEC-29, DEC-41, DEC-51 e DEC-61) tiveram, na consolidação final de 02/11/2026, só o qualificador da VT da CL-07 anotado, com o texto anterior preservado; a revisão do responsável sobre cada decisão não foi alterada. *(Texto original: "são anteriores e não foram alteradas por esta decisão".)*
- Nenhuma outra tarefa ou critério foi alterado.

### Evidências da T144 sobre CR-22 e CR-23 *(02/11/2026)*

Esta seção é posterior ao encerramento formal da CL-07 e à conclusão da T020 e **não os altera**: CR-22 e CR-23 nunca fizeram parte da CL-07 (opção A, 02/11/2026). **CR-01 a CR-21 permanecem 21 de 21 comprovados, com as ressalvas registradas.** A T144 foi implementada e testada em separado (`tasks.md` da 008; quickstart 008, "Evidências de Q16, Q17 e Q18"). Ambiente: Moodle 5.2.4+ (Build 20261030), PHP 8.3.36, MariaDB 11.4.13, PHPUnit 11.5.55; execuções com `--display-skipped`, 0 falhas, 0 erros, 0 skips, 0 warnings e 0 notices.

| CR | Situação | Evidência executada | Não comprovado / ressalvas |
|---|---|---|---|
| 22 — Monotonicidade no repositório (DEC-62) | **Comprovado, com ressalva** (antes: Não comprovado, pendente da T144) | `material_repository_test` (26 testes, 160 asserções): Q16 em sequência (ponteiro nulo, número maior, igual/própria corrente e menor recusados sem alterar o estado lido do banco, outro material e versão não `completed` recusados como antes, histórico consultável), comparação por número e não por `id`, tipo próprio da recusa. Evidências **diretas** do repositório: Q16 e o teste de comparação por número. Q18 em `material_service_test` (30 testes, 158 asserções na execução original; 31 testes, 171 asserções depois do teste complementar de 02/11/2026, commit `bb8dc37`) é evidência **complementar** do comportamento da camada de serviço diante da recusa; não é teste direto da monotonicidade do repositório. Controle de mutação: sem a regra, os 3 testes novos do repositório e os 2 Q18 falham | **U-07 a U-12 e S-01 não foram implementados com esses identificadores**; o conteúdo equivalente está distribuído entre o Q16, o teste por número e os Q18, **sem mapeamento um a um**, e nada se atribui aos identificadores. Q18, ramo "`completed` e não corrente": só a própria corrente foi exercida (a recusa por versão mais nova exigiria uma corrida entre a detecção e a troca) *(limite da execução original; superado em 02/11/2026 pelo teste complementar do Q18, com a intercalação simulada num único processo, o que mantém a evidência do Q18 como complementar, da camada de serviço; a evidência de concorrência entre processos independentes continua sendo a do CR-23: M-12, M-13 e Q17; ver "Complemento do Q18" em `specs/008-materiais-vers-es-e-contexto/quickstart.md`)*. Falha técnica real na troca: coberta pelos testes já existentes (I-03, I-06, lock), sem teste novo |
| 23 — Monotonicidade sob concorrência (DEC-62) | **Comprovado, com ressalva** (antes: Não comprovado, pendente da T144) | `material_monotonic_concurrency_test` (6 testes, 379 asserções, 3 execuções seguidas OK): M-12 (v3 antes de v2, v2 antes de v3 e contenção v2/v3 pelo lock, 6 rodadas, as duas ordens ocorreram), M-13 (mesma versão em dois processos, 6 rodadas; seleção da corrente sem gravar; ponteiro nulo disputado, 6 rodadas, as duas ordens ocorreram), Q17 da 008. `material_concurrency_test` (M-02) reexecutado e ajustado: ponteiro termina na maior versão (3 execuções OK) | MariaDB, `GET_LOCK`, processos da mesma máquina; a ordem de obtenção do lock sob contenção só é observada, não controlada; o critério é por invariantes, sem exigir tipo de exceção (a implementação usa tipo próprio). Sem evidência para outro SGBD, outra Lock Factory ou várias máquinas |

*Regressão* (cada classe sozinha, todas OK): demais classes de `infrastructure/persistence`, `material_recovery_test` (5/189), `material_failure_test` (6/167), `content_extractor_test`, `install_schema_test` (6/232). *Não alterados por esta nota:* o encerramento da CL-07, a T020, DEC-61, DEC-62, CL-37, CL-49, CL-50, CL-51 e CL-52, o código, os testes e os arquivos XMLDB.
