# Validação CL-02 / T051 / CL-44 — fila, lock e recuperação (V1–V4)

> **ATENÇÃO: estes scripts operam sobre o site Moodle em que forem executados, que é o site
> compartilhado do ambiente (`/var/www/html/config.php`), e podem alterar dados dele.** Alguns
> (`v2_cycle.php`, `v4_worker.php`) chamam código real de processamento que não se limita ao curso
> de teste — leia os avisos de cada seção antes de rodar qualquer um.
> - Não use em produção.
> - Use sempre curso(s) e usuário(s) **descartáveis**, criados pelos próprios scripts de preparo
>   (`v2_prep.php`, `v4_prep.php`), nunca um curso ou evento real do site.
> - `v3_prep_event.php` recebe `course_id`/`cmid` por parâmetro: só passe os de um curso descartável.

## Objetivo

Validar, contra um Moodle 5.2 e um MariaDB reais (não PHPUnit), os critérios da DEC-32 que o
`closure-register.md` atribui à CL-02 (T051) e a hipótese de recuperação da CL-44:

- agendamento nativo da Scheduled Task e obtenção do horário da próxima execução (evidência
  preexistente de outras tasks; não há script aqui para isso);
- backend de lock usado pela fila (`mysql_lock_factory`);
- **exclusão mútua da Scheduled Task** entre processos reais (V1);
- **lock por evento entre processos reais** (V2);
- **recuperação de um worker interrompido** por morte abrupta do processo (V3);
- **recuperação de uma falha real de persistência** (`complete_attempt`) depois de o handler já
  ter concluído e gravado seu efeito, sem duplicar esse efeito (V4, cenário específico da CL-44).

Nenhum destes scripts reimplementa cron, eventos ou a Lock API do Moodle: todos chamam a mesma API
pública que a produção usa (`event_repository`, `unit_of_work`, `queue_processor`,
`lock_config::get_lock_factory`, ou a própria sequência interna de `admin/cli/scheduled_task.php`),
só não através do executor oficial quando este estiver bloqueado (ver V1).

## Scripts × cenários

| Script | Cenário | Papel |
|---|---|---|
| `v1_execute.php` | V1 | Reproduz a sequência nativa de lock de uma Scheduled Task (`core_cron`, lock da task, `run_inner_scheduled_task`) — usado quando o executor oficial (`admin/cli/scheduled_task.php`) está bloqueado por um upgrade de plugin pendente, sem rodar `upgrade.php` |
| `v1_hold_task_lock.php` | V1 | Segura o lock nativo da task `process_queue` por N segundos, para forçar o caminho de rejeição por timeout |
| `v2_prep.php` | V2 (e V3) | Cria um curso descartável com o assistente habilitado e um evento `pending` |
| `v2_hold.php` | V2 | Segura o lock de um evento (`event_<id>`) por N segundos, simulando outro processo processando-o |
| `v2_cycle.php` | V2, V3, V4 | Roda um ciclo normal da fila (`queue_processor::run_cycle()`) diretamente, sem passar pelo executor de Scheduled Tasks — **processa qualquer evento pendente do site**, não só o de teste |
| `v3_prep_event.php` | V3 | Cria mais um evento `pending` num curso descartável já existente (dados `course_id`/`cmid` por parâmetro) |
| `v3_worker.php` | V3 | Inicia uma tentativa real (`begin_attempt`) e dorme no lugar do handler, para ser interrompido externamente (`kill -9`) enquanto o evento está em `processing` |
| `v4_prep.php` | V4 | Cria um curso descartável, um estudante matriculado e um evento `pending` |
| `v4_locker.php` | V4 | Segura, via `SELECT ... FOR UPDATE` do InnoDB, a linha de `local_assistente_processing_attempt` que `complete_attempt` grava, forçando uma falha real de persistência por timeout de lock |
| `v4_worker.php` | V4 | Executa o handler real até o fim, e só então tenta `complete_attempt` — a falha real (provocada pelo `v4_locker.php`) acontece exatamente aí |

## Pré-requisitos

- Moodle 5.2 em execução, com o plugin instalado e reconhecido (CL-01).
- Acesso de administrador via CLI (os scripts chamam `\core\session\manager::set_user(get_admin())`
  quando precisam criar dados).
- `testing_data_generator` disponível (`$CFG->libdir . '/testing/generator/lib.php'`) — o mesmo
  gerador usado pelos testes PHPUnit e pelos demais scripts de `validation/`.
- Nenhuma dependência de rede externa; nenhum dos handlers exercitados (`frequency_handler`) chama
  a LLM.

## Ordem de execução

Os quatro cenários são independentes entre si (cada um prepara seus próprios dados), mas dentro de
cada um a ordem importa:

**V1** — não precisa de preparo:
```
php v1_execute.php '\local_assistente\task\process_queue'   # roda uma vez, sozinho, para conferir
# corrida natural: duas instâncias de v1_execute.php lançadas quase ao mesmo tempo
# rejeição por timeout: v1_hold_task_lock.php em segundo plano, depois v1_execute.php
```

**V2** (lock por evento):
```
php v2_prep.php                              # -> {"course":N,"cmid":N,"event":E}
php v2_hold.php E 10 &                        # processo A, segura o lock do evento E
sleep 1.5
php v2_cycle.php E                            # processo B, deve registrar skipped_locked=1
wait                                          # espera A liberar
php v2_cycle.php E                            # processa normalmente, sem duplicar
```

**V3** (worker interrompido), reaproveitando o curso/cmid de V2 ou de um preparo próprio:
```
php v3_prep_event.php <course_id> <cmid>      # -> {"event":E2}
php v3_worker.php E2 60 &                     # inicia a tentativa, dorme "dentro do handler"
# confirme no banco: evento E2 em processing, tentativa em processing
kill -9 <pid do worker>                       # interrompe só esse processo
php v2_cycle.php E2                           # recupera e processa
```

**V4** (falha real de persistência, cenário específico da CL-44):
```
php v4_prep.php                                       # -> {"course":N,"cmid":N,"student":S,"event":E3}
php v4_worker.php E3 6 &                               # worker: begin_attempt, handler real, espera 6s
# aguarde a linha JSON "attempt_started" e capture o id da tentativa
php v4_locker.php <attempt_id> 15                      # segura a linha por 15s (mais que os 6s de espera)
wait                                                   # o worker tenta complete_attempt, falha de verdade, termina sozinho
php v2_cycle.php E3                                    # ciclo normal recupera e reprocessa sem duplicar
```

## Parâmetros esperados

| Script | Parâmetros (posicionais) |
|---|---|
| `v1_execute.php` | `[classname]` — nome da task, com `\` escapado (padrão: `process_queue`) |
| `v1_hold_task_lock.php` | `[seconds]` — padrão 15 |
| `v2_prep.php` | nenhum |
| `v2_hold.php` | `eventid seconds` — `eventid` obrigatório |
| `v2_cycle.php` | `[eventid]` — opcional, só para o relatório final sobre esse evento |
| `v3_prep_event.php` | `course_id cmid` — ambos obrigatórios, de um curso descartável já existente |
| `v3_worker.php` | `eventid [seconds]` — `eventid` obrigatório, padrão 60s de espera |
| `v4_prep.php` | nenhum |
| `v4_locker.php` | `attempt_id [seconds]` — `attempt_id` obrigatório, padrão 15s |
| `v4_worker.php` | `eventid [seconds_de_espera_pelo_locker]` — `eventid` obrigatório, padrão 4s |

Nenhum script lê variável de ambiente nem arquivo de configuração próprio; tudo vem de `argv` ou é
gerado em tempo de execução.

## Riscos de executar contra um site compartilhado

- `v2_cycle.php` chama `queue_processor::run_cycle()` **sem filtro de curso**: ele processa
  qualquer evento `pending`/`processing` de qualquer curso habilitado no site no momento em que
  rodar, não só o evento de teste. Se houver eventos reais pendentes, eles serão processados de
  verdade (handlers reais, inclusive os que chamam a LLM, se configurada). Rode isto só quando
  souber que não há trabalho real pendente que não deva ser tocado agora, ou num site isolado.
- `v4_worker.php` executa o **handler real** (`frequency_handler`) sobre o evento informado. Se
  `$eventid` apontar para um evento de produção, o efeito gravado em `local_assistente_analysis` é
  real e **imutável** — nenhum método do plugin altera ou apaga uma análise, e a limpeza deste
  diretório não desfaz esse efeito.
- `v4_locker.php` trava, de fato, a linha indicada por `attempt_id` pelo tempo informado. Usado
  sobre o id de uma tentativa **real**, qualquer processo da fila que precise dessa linha — inclusive
  um ciclo normal processando aquele evento — vai esperar o tempo todo do hold, ou falhar por
  timeout exatamente como no teste.
- `v1_hold_task_lock.php` e `v2_hold.php` seguram locks nomeados reais (`core\lock`); nenhum efeito
  além da espera de quem precisar do mesmo recurso enquanto o hold durar.
- Nenhum script altera `version.php`, roda `upgrade.php` ou toca nos cursos 5 e 6.

## Dados que podem ser criados

- Um curso (`testing_data_generator::create_course`), com o assistente habilitado
  (`config_repository::create`) e um módulo `assign` — shortname `CL02T051` (V2/V3) ou `CL44V4`
  (V4).
- Em V4, também um usuário (`cl44v4student`), matriculado como `student` no curso `CL44V4`.
- Eventos (`local_assistente_event`), suas tentativas (`local_assistente_processing_attempt`) e,
  quando o handler roda de verdade (V2 depois do release, V3 depois da recuperação, V4), uma
  análise de frequência (`local_assistente_analysis` + `local_assistente_frequency`), e os eventos
  em cascata que o `frequency_handler` enfileira (`course_criteria_check_requested`,
  `synthesis_requested`), também processados dentro do mesmo curso de teste.
- Ao excluir o curso de teste pelo mecanismo nativo (`delete_course`), uma entrada temporária em
  `tool_recyclebin_category` (lixeira de cursos excluídos).

Nenhum dado de estudante real, credencial ou identificador do ambiente (chaves, segredos) é
envolvido em nenhum dos scripts.

## Procedimento de limpeza

`delete_course($courseid, false)` aciona o observer do próprio plugin
(`course_observer::course_deleted`), que já apaga todos os registros `local_assistente_*` daquele
curso (eventos, configuração, processamentos, análises) — não é preciso (e não se deve) apagar
essas tabelas diretamente. Depois de excluir o curso, a entrada de `tool_recyclebin_category` com o
mesmo `shortname` ainda precisa ser removida (`\tool_recyclebin\category_bin::delete_item`), e, em
V4, o usuário `cl44v4student` precisa ser removido (`delete_user`, que o Moodle mantém como registro
com `deleted = 1`).

`spec-kit/validation/cleanup_temp.php` cobre isso automaticamente: os prefixos `cl02t051` e
`cl44v4` foram incluídos nele (courses `CL02T051`/`CL44V4`, usuário `cl44v4student`), com o mesmo
mecanismo e as mesmas garantias já usadas para `cl26`/`cl3x`/`cl35r` — veja esse arquivo. Rode:

```
php cleanup_temp.php --dry-run   # lista o que seria removido
php cleanup_temp.php             # remove
```

Isso cobre uma execução que falhou no meio (curso criado, mas não removido). **Não cobre**:
tentativas abertas presas por um `kill -9` em `v3_worker.php` sem a recuperação subsequente ter
rodado — nesse caso a própria exclusão do curso já resolve, porque apaga o evento e a tentativa
junto com o curso; não há necessidade de recuperar a tentativa antes de limpar.

## Cursos e usuários descartáveis

Todos os cenários V1–V4 devem ser exercitados apenas sobre cursos e usuários criados pelos próprios
scripts de preparo (`v2_prep.php`, `v4_prep.php`) para esse fim — nunca sobre um curso, evento ou
usuário real do site. Os scripts não verificam isso por si mesmos (não há, por desenho, nenhuma
lista de "cursos protegidos" codificada neles); a disciplina de só passar ids de recursos
descartáveis é de quem os executa.
