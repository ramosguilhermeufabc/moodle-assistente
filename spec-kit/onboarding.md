# Onboarding — assumir a manutenção do `local_assistente`

> **`VIGENTE`** · O que você precisa saber para mexer neste plugin: arquitetura, ciclo de
> processamento, pontos de entrada, convenções de teste e o vocabulário de identificadores que o
> código cita. Índice dos documentos: [`README.md`](README.md). Para rodar qualquer coisa:
> [`operacao.md`](operacao.md). Para achar código por área, o
> [`README.md`](../README.md) da raiz do repositório, que é o mapa do código e não é repetido aqui.

## 1. O que o plugin faz

`local_assistente`, componente `local/assistente`, para Moodle 5.2 (`MOODLE_502_STABLE`).
Acompanha questionários, notas, fóruns, participação e materiais de um curso; processa os eventos
do Moodle de forma assíncrona; aplica os critérios que o professor configurou; e apresenta
consultas, síntese pedagógica, histórico e relatórios versionados.

`assistente/version.php`: versão `2026110202`, release `0.1.0-dev`, maturidade
`MATURITY_ALPHA`. Única biblioteca de terceiros: `smalot/pdfparser` 2.13.1, em
`assistente/lib/pdfparser` (DEC-67), declarada em `assistente/thirdpartylibs.xml`.

Operações que exigem interpretação linguística usam uma API externa de LLM; **cálculo
determinístico não usa LLM** (constituição §13). Materiais no escopo: PDF, TEXT e YouTube — Book e
Folder estão fora (constituição §6).

## 2. Camadas, e por que elas se sustentam

`assistente/classes/` tem as pastas `domain`, `application`, `infrastructure`, `observer`, `task`,
`privacy`, `form`, `output` e `event`. O layout não é convenção escrita: é **imposto por teste**.
`assistente/tests/architecture_test.php` reprova a suíte em doze regras, verificadas sobre o
código com o tokenizador do PHP:

| Regra | O que reprova |
|---|---|
| `test_layout_and_namespaces` | pasta ou namespace fora do layout aprovado |
| `test_no_dml_or_sql_outside_the_infrastructure` | `$DB` ou SQL em `domain/`, `application/`, `observer/`, `task/`, `form/`, `output/` ou `privacy/` |
| `test_domain_does_not_use_the_moodle_api` | API do Moodle, banco ou estado global dentro de `domain/` |
| `test_no_physical_table_prefix` | prefixo físico de tabela em chamada DML |
| `test_observers_only_delegate` | observer que calcula resultado em vez de delegar |
| `test_lib_php_has_only_the_necessary_callbacks` | lógica em `lib.php` (que, hoje, não existe — ver §4) |
| `test_no_own_weighting_of_grades` | ponderação própria de notas, em vez da do Moodle |
| `test_no_threshold_constant_outside_criteria` | constante de limiar fora da área de critérios |
| `test_no_manual_form_or_post_where_there_is_a_moodleform` | formulário ou POST manual onde cabe `moodleform` |
| `test_the_student_data_repository_is_used_only_by_the_privacy_erasure` | uso do repositório de dados do estudante fora da exclusão de privacidade |
| `test_the_history_redaction_writer_is_used_only_by_the_privacy_application` | uso do redator de histórico fora da aplicação de privacidade |
| `test_only_the_erasure_audit_triggers_the_audit_events` | evento de auditoria disparado fora da auditoria de exclusão |

Em resumo: **só `infrastructure/` fala com o banco**; `domain/` é puro; `application/` orquestra;
observers delegam. Mexer nessas fronteiras quebra a suíte, e é assim de propósito.

## 3. O ciclo de processamento

```
evento do Moodle
   └─ observer (classes/observer/)              fino por contrato: lê identificadores do evento,
      │                                          nunca da requisição, e delega
      └─ serviço de evento (application/delivery/, application/<área>/)
         └─ fila lógica                          tabelas event · processing · processing_attempt
            │                                    falha de lock no observer → redeliver_event
            │                                    (tarefa ad hoc, DEC-59, com lock_failure_budget)
            └─ tarefa agendada process_queue
               └─ queue_processor (application/queue/)
                  │   próximo evento executável em ordem de criação; no máximo uma tentativa por
                  │   ciclo; lock por evento; nenhuma transação aberta enquanto o handler roda;
                  │   no máximo três tentativas
                  └─ handler do tipo (handler_registry)
                     ├─ leitura dos dados do Moodle (infrastructure/moodle/)
                     ├─ contexto de material (application/material/, application/context/,
                     │  snapshot em processing_context)
                     ├─ porta de LLM (application/llm/ → infrastructure/llm/)
                     │  chamada fora de transação; chave vinda da configuração de site
                     │  criptografada (DEC-69)
                     └─ resultado gravado em transação curta
                        análise, métricas, notas, fórum, frequência, critérios, síntese
```

Do lado da leitura: `application/query/` alimenta as abas do curso, `application/report/` monta o
relatório atual e as versões históricas, `application/history/` responde pelo histórico e pelo
indicador de fonte removida. Privacidade fica em `classes/privacy/provider.php` com
`application/privacy/`, e a exclusão de estudante tem tarefa própria de repetição
(`retry_student_erasure`).

As cinco tarefas agendadas de `assistente/db/tasks.php`: `process_queue`, `detect_quiz_close`,
`detect_reference_dates`, `reconcile_material_versions` e `report_dispatcher`.

O detalhe de cada peça está no docblock da própria classe, que cita a spec, a DEC e a tarefa que a
originaram — é a melhor fonte, e é por isso que este guia não a repete.

## 4. Pontos de entrada

| Arquivo | Papel |
|---|---|
| `assistente/index.php` | página do assistente no contexto do curso, uma aba por consulta |
| `assistente/configure.php` | configuração por curso, com critérios |
| `assistente/settings.php` | configuração de site (inclui a chave da API de LLM, criptografada) |
| `assistente/db/tasks.php` | tarefas agendadas |
| `assistente/db/events.php` | observers de eventos do Moodle |
| `assistente/db/hooks.php` + `assistente/classes/hook_callbacks.php` | entrada na navegação do curso |
| `assistente/db/access.php` | capacidades (`local/assistente:view`, `:manage`) |
| `assistente/db/install.xml` | **a fonte do esquema** (constituição §1) |
| `assistente/db/upgrade.php` | atualização de esquema, com savepoint (DEC-29) |

**Não existe `assistente/lib.php`**, e isso não é defeito: a `003-*/tasks.md` previu, na linha 58,
que ele só seria criado se a navegação exigisse callback, e a T123 resolveu a navegação pelo hook
do Moodle 5.2. O teste de arquitetura trata `lib.php` como opcional.

## 5. As 21 tabelas

Todas com prefixo `local_assistente_`. A fonte é `assistente/db/install.xml`; a spec-002 a
descreve sem substituí-la.

| Grupo | Tabelas |
|---|---|
| configuração | `config`, `criteria` |
| fila e processamento | `event`, `processing`, `processing_attempt`, `processing_context` |
| materiais | `material`, `material_version` |
| análises | `analysis`, `questionnaire_analysis`, `questionnaire_question`, `question_result`, `questionnaire_metric`, `forum_difficulty`, `performance`, `frequency`, `frequency_activity` |
| resultado e síntese | `criteria_result`, `pedagogical_synthesis` |
| relatórios | `report`, `report_version` |

## 6. Testes

138 arquivos `*_test.php` em `assistente/tests/`, espelhando a árvore de `classes/`, mais 11
features Behat em `assistente/tests/behat/` e um gerador de dados do plugin em
`assistente/tests/generator/lib.php`. A suíte é `local_assistente_testsuite` — veja
[`operacao.md`](operacao.md) para os comandos.

Seis testes na raiz de `tests/` funcionam como porteiros de regras transversais:
`architecture_test.php` (as doze regras acima), `lang_parity_test.php` e `lang_usage_test.php`
(paridade e uso das strings; 149 strings em cada idioma), `access_test.php`,
`navigation_test.php` e `generator_test.php`. Há ainda `tests/db/install_schema_test.php`, que
compara o `install.xml` lido pelo XMLDB com o inventário da spec-002.

Convenções: toda tarefa que altera código inclui os seus testes automatizados, indicados na
própria tarefa (DEC-48); a LLM é sempre exercitada pelo adaptador simulado (DEC-33 D1); cobertura
de linhas é medida e relatada, **sem limiar** — não é critério de aprovação.

## 7. Glossário dos identificadores

Este é o vocabulário que você vai encontrar dentro dos comentários do PHP. Ele é **contrato**: o
código cita 972 vezes um `DEC-nn`, 475 um `Tnnn`, 234 um `CL-nn` e 190 um `spec-NNN`. Nenhum
desses identificadores pode ser renumerado ou renomeado.

| Identificador | O que é | Dono |
|---|---|---|
| `spec-NNN` | uma das 23 especificações; a fonte dos requisitos daquela área | [`specs/README.md`](specs/README.md) |
| `AT-nnn` | um dos 66 requisitos atômicos, AT-001 a AT-066 | [`requirements-traceability.md`](requirements-traceability.md), constituição §2 |
| `DEC-nn` | decisão aprovada pelo responsável, DEC-01 a DEC-72 — os ADRs do projeto, com contexto, regra, artefatos afetados e critério de validação | [`decision-register-DEC-01-25.md`](decision-register-DEC-01-25.md), constituição §11 |
| `CL-nn` | item do registro de fechamento: pendência ou validação técnica, com estado e evidência de fechamento | [`closure-register.md`](closure-register.md) §4 |
| `Tnnn` | tarefa de implementação, T001 a T145, global e única, na `tasks.md` da sua spec | `specs/NNN-*/tasks.md`; ordem em [`specs/README.md`](specs/README.md) |
| `Q-nnn-nn` | questão aberta registrada no plano da spec NNN — `Q-008-05` é a quinta questão do plano 008 | `specs/NNN-*/plan.md` |
| `C-nnn-nn` | contradição registrada no plano da spec NNN | `specs/NNN-*/plan.md` |
| `[E0]`…`[E12]` | rótulo de entrega; define a ordem de execução das tarefas, no lugar do rótulo de história de usuário | [`specs/README.md`](specs/README.md), "Ordem de entregas" |
| `G-nnn` | gate da spec NNN: verificação independente que a entrega precisa passar, como `G-003` e `G-023` | `specs/NNN-*/plan.md` e `tasks.md` |
| `R-nnn-nn` | risco registrado no plano da spec NNN, como `R-020-01` | `specs/NNN-*/plan.md` |
| `F-nn` | fato verificado numa fase, com o método de verificação | [`closure-register.md`](closure-register.md) §1 |
| `D-nn` | divergência registrada e decidida | [`closure-register.md`](closure-register.md) §2 |
| `CR-nn`, `I-nn` | cenários e itens da matriz de validação da CL-07 | [`closure-register.md`](closure-register.md), item CL-07 |
| `AUD-Dn` | achado da auditoria Plan × Specs de 21/10/2026 | registro de decisões §7, seção histórica |

Classes de item do registro de fechamento, definidas no §0 dele:

| Classe | Significado |
|---|---|
| `PROP` | decisão aprovada a propagar; não se reabre |
| `DH` | exige decisão humana ou fonte normativa ainda não localizada |
| `VT` | validação técnica no Moodle 5.2 ainda não executada |
| `VE` | verificação documental, possível sem decisão nova |

Estados: `Aberto` · `Bloqueado` · `Em execução` · `Aguardando revisão` · `Fechado`.

Um item só fica **Fechado** quando a evidência indicada na coluna "Evidência de fechamento"
existir e tiver sido conferida. "Documentado", "planejado", "tarefa criada" e "teste previsto" não equivalem
a "Fechado" — é a regra do §0, e vale para qualquer afirmação de estado neste projeto.

## 8. Regras de trabalho que não são negociáveis

- **A norma é a constituição** ([`.specify/memory/constitution.md`](.specify/memory/constitution.md)),
  e a §1 define a hierarquia das fontes. Leia-a antes de interpretar requisito, propor decisão ou
  declarar entrega conforme.
- **Requisito não se inventa.** Ambiguidade, contradição ou lacuna se registra e se submete à
  decisão do responsável.
- **Documentação acompanha o código, não o contrário.** Quando um documento divergir do código:
  se é decisão registrada que o código não implementa, pare e peça decisão; se é decisão já
  implementada cuja propagação ficou atrasada, corrija o documento preservando o texto substituído
  com a data (constituição §11).
- **Evidência antes de afirmação** (constituição §10): não declare pendência fechada, cobertura
  integral nem teste aprovado sem a evidência conferida.
- **Autorização é por alteração.** Nenhuma alteração de arquivo e nenhuma operação Git de escrita
  sem autorização expressa do responsável para aquela alteração.

## 9. Por onde começar, na prática

1. [`README.md`](../README.md) da raiz — o mapa do código: "preciso mexer em X, vou a Y, confiro
   com Z".
2. [`operacao.md`](operacao.md) — subir o ambiente e rodar as suítes.
3. [`closure-register.md`](closure-register.md), seção "Estado atual" — em que pé está o projeto.
4. A `spec.md` da área que você vai tocar, em [`specs/`](specs/README.md), e a `tasks.md` dela.
5. O registro de decisões, apenas nas DEC que a área citar — ele tem 24.749 palavras e não é para
   ler inteiro.
