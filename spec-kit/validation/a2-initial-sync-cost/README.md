# A2 — custo da sincronização inicial (diagnóstico e medições)

> **(08/11/2026) Documento histórico: registro das medições de custo da sincronização inicial,
> feitas no commit `0caffcb`.** As medições e os limites de ambiente seguem valendo como
> evidência. Está superada a afirmação de que CL-40 continua aberto (linhas 4 e 124): CL-40 foi
> fechado em 03/11/2026, com limitações aceitas pelo responsável. O estado vigente está em
> `closure-register.md`. O texto abaixo foi preservado sem reescrita.

Registro de evidência do risco R-020-01 (sincronização de curso grande, spec-020, T121). **Não decide nada e não altera nenhuma decisão
normativa.** CL-40 continua aberto: este registro é insumo para a decisão sobre tarefa assíncrona retomável, não a decisão.

Os instrumentos de medição (testes PHPUnit descartáveis) ficaram fora do repositório; aqui estão só os resultados e o método.

## Limites do ambiente (valem para todas as tabelas)

- Tempos absolutos são **específicos deste ambiente de teste** (Codespaces, MariaDB 11.4.13, PHP 8.3.36, banco de testes do PHPUnit, CLI com
  `max_execution_time` 0 e `memory_limit` 2G). **Não são estimativas de produção.** O PHP web usa 30 s e 128M, e o xdebug (`debug,develop`)
  fica carregado; por isso cada medição foi repetida com xdebug ativo e com `-d xdebug.mode=off`.
- Os dados são sintéticos (estudantes, atividades, notas, mensagens e tentativas gerados por inserção direta ou pelos geradores de teste). Não
  há curso real nem usuário real.
- Maior curso medido: 80 estudantes × 20 atividades (notas e participação), 160 estudantes × 3 fóruns (1923 mensagens), 160 estudantes × 3
  questionários (960 tentativas concluídas). **Não há medição de curso real de grande porte.**
- Não foi medido o tempo total de `initial_sync_service::run()` ponta a ponta nem em servidor web com os limites de produção.

## O que foi feito (commit 0caffcb, A2)

Sem alteração de esquema, índice, migração, contrato de identidade, processamento assíncrono nem limites do PHP:

- `event_service::enqueue_many()` entrega as ocorrências em blocos de 250, lendo as identidades do (curso, tipo) uma vez por bloco
  (`event_repository::identities_of`), sob as mesmas travas por identidade e por aluno do `enqueue()`, adquiridas antes da transação;
  repetições na mesma chamada são criadas uma vez; tipos que se fundem enquanto pendentes seguem pelo caminho unitário.
- `gradebook_reader::get_final_grades()` lê as notas de uma atividade juntas; `participation_reader::get_participation()` aceita a nota já lida.
- Testes: lote, paridade com o caminho unitário e concorrência com processos PHP reais (mesma ocorrência, mesmo lote, unitário contra lote,
  identidades diferentes, travas de identidade e de aluno seguradas por outro processo). Uma mutação (lote sem as travas) foi detectada.

## Medido: notas e participação (antes → depois de A2, medição direta com o código de cada época)

Fase de notas / fase de participação, em segundos, xdebug ativo:

| Estudantes × atividades | Notas | Participação |
|---|---|---|
| 10 × 4 | 0,13 → 0,06 | 0,19 → 0,10 |
| 20 × 8 | 0,59 → 0,13 | 0,78 → 0,26 |
| 40 × 12 | 1,93 → 0,37 | 2,39 → 0,89 |
| 80 × 20 | 8,59 → 1,28 | 10,70 → 2,38 |

Sem xdebug, 80 × 20: notas 0,97 s e participação 1,92 s. O expoente do tempo (tempo ∝ pares^p) passou de cerca de 2 para cerca de 1. Pico de
memória por fase: abaixo de 6 MB. Antes de A2 a causa era a busca de identidade que carregava e decodificava todos os eventos do (curso, tipo)
para cada evento criado (custo O(E²) para E eventos).

## Medido: fóruns e questionários com tentativas concluídas (código atual, sem xdebug)

**Comparação emulada com a API pública:** o código anterior a A2 não existe mais na árvore. A coluna "anterior" é um laço que, para cada
estudante e módulo, lê a participação sem a nota repassada e entrega cada evento por `participation_event_service::participation_changed()`
(`enqueue()` unitário); a coluna "atual" é `sync_participation()`. É uma aproximação do caminho anterior, não a execução do código antigo.

Fase de participação, só módulos de fórum ou de questionário:

| Cenário (estudantes × módulos × itens por estudante) | Volume | Só leitura | Anterior (emulado) | Atual | Leituras anterior → atual | Escritas |
|---|---|---|---|---|---|---|
| Fórum 10 × 3 × 4 mensagens | 123 | 0,05 s | 0,16 s | 0,05 s | 481 → 235 | 30 |
| Fórum 40 × 3 × 4 | 483 | 0,20 s | 0,55 s | 0,22 s | 1920 → 925 | 120 |
| Fórum 80 × 3 × 4 | 963 | 0,50 s | 1,12 s | 0,49 s | 3840 → 1845 | 240 |
| Fórum 160 × 3 × 4 | 1923 | 1,06 s | 2,63 s | 1,24 s | 7680 → 4010 | 480 |
| Fórum 40 × 3 × 32 | 3843 | 0,35 s | 0,76 s | 0,34 s | 1920 → 925 | 120 |
| Questionário 10 × 3 × 2 tentativas | 60 | 0,07 s | 0,12 s | 0,06 s | 450 → 232 | 21 |
| Questionário 80 × 3 × 2 | 480 | 0,71 s | 1,14 s | 0,60 s | 3600 → 1821 | 168 |
| Questionário 160 × 3 × 2 | 960 | 1,62 s | 2,69 s | 1,45 s | 7200 → 3814 | 336 |
| Questionário 40 × 3 × 16 | 1920 | 0,48 s | 0,67 s | 0,46 s | 1800 → 913 | 84 |

- Variando de 10 a 160 estudantes, o expoente do tempo é de 1,00 a 1,16 em todas as fases; com xdebug ativo o pior caso (160 estudantes) leva
  de 1,5 a 1,9 s. Pico de memória abaixo de 3 MB. As escritas são as mesmas antes e depois.
- A redução de leituras é de cerca de 2× (a nota deixa de ser lida em cada célula); o ganho de tempo é de 1,3× a 2,2×, e nos fóruns grandes
  e nos questionários é pequeno ou nulo.
- No questionário, cada célula (estudante × questionário) faz 4 leituras e traz todas as tentativas do questionário; no fórum, 2 leituras e um
  `GROUP BY` sobre todas as mensagens do fórum. O custo por célula sobe devagar com o volume (questionário de 2,3 ms por célula com 10
  estudantes para 3,4 ms com 160; fórum de 1,6 ms com 2 mensagens por estudante para 2,9 ms com 32), o que indica um termo quadrático ainda
  pequeno nos tamanhos medidos.

## Medido: `enqueue()` unitário, o caminho dos observers (sem xdebug)

Custo de uma ocorrência nova com E eventos do mesmo tipo já no curso (média de 5 execuções):

| E | Fórum (`forum_post_created`) | Questionário (`quiz_attempt_submitted`) | `participation_changed` | Leituras por `enqueue()` |
|---|---|---|---|---|
| 250 | 3,2 ms | 3,2 ms | 3,3 ms | 8 a 10 |
| 1000 | 4,4 ms | 5,3 ms | 6,0 ms | 8 a 10 |
| 8000 | 26 ms | 25 ms | 35 ms | 8 a 10 |

Expoente de 0,6 a 0,7 de 250 a 8000 eventos. Rajada de N ocorrências novas, curso vazio:

| N | Um a um | Em lote (`enqueue_many`) | Leituras um a um / lote |
|---|---|---|---|
| 250 | 0,63 s | 0,17 s | 2000 / 755 |
| 1000 | 3,91 s | 0,67 s | 8000 / 3020 |
| 2000 | 10,71 s | 1,49 s | 16000 / 6040 |
| 4000 | 34,49 s | 2,75 s | 32000 / 12080 |

Expoente um a um: 1,44; em lote: 1,01. As escritas são iguais (N). O lote usa mais memória (10,1 MB contra 0,7 MB com 4000 eventos).

## Extrapolações (não são medições)

Ajuste linear sobre poucos pontos, só para dimensionar o risco; só uma medição naquele porte confirmaria:

- Questionário: a inclinação observada é de cerca de 5 µs por linha de tentativa por célula. Com 1000 estudantes, 3 tentativas e 5
  questionários, o termo quadrático chegaria a dezenas de segundos.
- Fórum: cerca de 0,4 µs por mensagem por célula. Com 1000 estudantes e fóruns com dezenas de milhares de mensagens, também chegaria a dezenas
  de segundos.
- Os valores acima dependem da forma do ajuste e podem errar por uma ordem de grandeza.

## Riscos ainda não medidos

- Cursos reais de grande porte (milhares de estudantes, dezenas de atividades, fóruns com dezenas de milhares de mensagens).
- Execução ponta a ponta de `run()` em requisição web, com 30 s e 128M.
- Reconstrução completa dos fóruns na habilitação (CL-47, implementada em 31/10/2026 como um evento `forum_rebuild_requested` por fórum): o custo da
  reconstrução (pode ocorrer até uma chamada à LLM por fórum, quando há mensagem a analisar, com as mensagens existentes no contexto; um fórum sem mensagem que entre na análise não chama a LLM) não foi medido; o custo em cursos com muitos fóruns continua sem medição, e esta medição de A2 é anterior à implementação (R-020-01).
- Materiais e questionários na sincronização continuam pelo caminho unitário (`sync_materials`, `sync_quizzes`); o número de eventos é pequeno
  (um por material ou questionário) e não foi o gargalo nas medições.
- Concorrência medida só entre processos da mesma máquina e travas do MariaDB (`GET_LOCK`); outros servidores e backends de trava não foram
  cobertos (CL-02, CL-07).
- Os 8 testes que falhavam entre 00:00 e 00:10 de Perth foram corrigidos (commit 87097a4), mas a suíte não foi executada nessa janela.

## Leitura do resultado

- A2 eliminou o crescimento quadrático da sincronização de notas e participação nos tamanhos medidos; fóruns e questionários crescem de forma
  quase linear nos tamanhos medidos.
- O `enqueue()` unitário continua com custo superlinear em rajadas (expoente 1,44); `enqueue_many` evita isso. Só um índice ou coluna de
  identidade o eliminaria, e isso não foi autorizado.
- Nenhuma fase medida passou de 3 s no maior tamanho. **Isso não é evidência suficiente para decidir sobre a tarefa assíncrona retomável nem
  para fechar CL-40**; a decisão continua pendente de medição em porte maior.

## Adendo — memória de uma execução para as contagens (alternativa A, ainda sem commit no momento deste registro)

`participation_reader::with_run_memo()` guarda, só durante a fase de participação de uma sincronização, as contagens de mensagens de cada fórum
e os usuários com tentativa concluída de cada questionário; descarta tudo ao fim, também se houver exceção. Sem persistência, sem cache do site,
sem estado entre requisições. Medido no mesmo ambiente e com os mesmos cenários (fase de participação, sem xdebug; "A2" é a coluna "Atual" da
tabela acima):

| Cenário | Anterior (emulado) | A2 | A2 + memória | Leituras A2 → com memória |
|---|---|---|---|---|
| Fórum 80 × 3 × 4 | 1,21 s | 0,49 s | 0,26 s | 1845 → 1134 |
| Fórum 160 × 3 × 4 | 2,85 s | 1,24 s | 0,59 s | 4010 → 2579 |
| Fórum 40 × 3 × 32 (3843 mensagens) | 0,69 s | 0,34 s | 0,14 s | 925 → 574 |
| Questionário 80 × 3 × 2 | 1,17 s | 0,60 s | 0,21 s | 1821 → 873 |
| Questionário 160 × 3 × 2 | 2,84 s | 1,45 s | 0,42 s | 3814 → 1906 |
| Questionário 40 × 3 × 16 (1920 tentativas) | 0,68 s | 0,46 s | 0,10 s | 913 → 445 |

- Expoente do tempo de 10 a 160 estudantes: fórum 1,14 → 1,02 e questionário 1,16 → 0,98. Com 40 estudantes, o tempo deixou de crescer com o volume
  (fórum de 243 a 3843 mensagens: 0,13 → 0,14 s; questionário de 120 a 1920 tentativas: 0,10 → 0,10 s; antes, 0,23 → 0,34 s e 0,25 → 0,46 s).
  Isso retira o termo que a extrapolação acima apontava como possível crescimento quadrático.
- Eventos criados: mesma quantidade e mesmas identidades com e sem a memória, nas 16 configurações (e em teste automatizado). Escritas e pico de
  memória inalterados.
- Com xdebug ativo o resultado é o mesmo (fórum 160 × 3 × 4: 1,52 s → 0,73 s; questionário 160 × 3 × 2: 1,93 s → 0,58 s).
- A semântica é a de uma fotografia, **deliberada**: o cache não é leitura sempre atualizada. Uma mensagem ou tentativa que surge durante a própria fase não é vista pelos estudantes consultados depois dela (a primeira consulta de cada módulo define o que todos veem naquela execução).
- **Correção de método:** uma primeira rodada "com memória" não mostrou ganho porque o instrumento chamava `sync_participation` sem passar pela
  abertura da memória feita em `run()`. Foi descartada e refeita; os números acima são da segunda rodada.
- Continuam valendo as limitações do ambiente e a ausência de medição em curso real de grande porte. Os caminhos unitários dos observers
  (alternativa B) não foram alterados.

## Adendo 2 — rajadas reais nos observers (medição de `forum_delete_discussion` e de recálculo de notas)

Medido em ambiente descartável (banco de testes do PHPUnit), com observers reais do plugin; "sem plugin" é a mesma operação num curso sem o
assistente habilitado. Tempos específicos do ambiente, **não estimativas de produção**. Instrumentos fora do repositório.

**Exclusão de uma discussão (`forum_delete_discussion`)**, sem xdebug; o número de mensagens inclui a mensagem inicial:

| Mensagens | Sem plugin | Com plugin | Tempo nos observers | Eventos criados | Leituras / escritas | Pico de memória |
|---|---|---|---|---|---|---|
| 11 | 0,06 s | 0,08 s | 0,05 s | 22 | 251 / 68 | 1,1 MB |
| 31 | 0,09 s | 0,22 s | 0,14 s | 62 | 690 / 188 | 1,1 MB |
| 101 | 0,29 s | 0,71 s | 0,47 s | 157 | 2185 / 563 | 1,2 MB |
| 301 | 0,85 s | 2,18 s | 1,42 s | 392 | 6420 / 1598 | 1,6 MB |
| 1001 | 3,22 s | 10,63 s | 7,46 s | 1343 | 21371 / 5349 | 2,7 MB |
| 2001 | 8,02 s | 29,63 s | 22,22 s | 2899 | 42927 / 10905 | 4,4 MB |

- Com xdebug ativo: 101 mensagens em 0,90 s, 1001 em 12,60 s e 2001 em 38,39 s (observers 30,42 s), acima de 30 s.
- Todos os eventos esperados foram persistidos: uma entrega `forum_post_deleted` por mensagem, uma `forum_discussion_deleted` e
  `participation_changed` por autor e segundo. Nenhum evento se perdeu sem falha.
- Custo por entrega nos observers: cerca de 2,3 ms até 301 mensagens (expoente do tempo ≈ 1,0), 3,7 ms com 1001 e 5,6 ms com 2001 (expoente 1,4 a
  1,6): é a varredura de identidade do `enqueue()` unitário. O Moodle sozinho cresce quase linear (expoente 1,0 a 1,3).
- Os observers respondem por cerca de 75% do tempo total com 2001 mensagens.
- Limite: foi medida **uma** discussão por vez, com 30 autores; não foram medidas outras distribuições de autores, nem exclusões de fórum
  inteiro nem de curso.

**Recálculo de notas** (agregação da categoria do curso alterada, depois `grade_regrade_final_grades`), sem xdebug:

| Alunos × atividades | Sem plugin | Com plugin | Tempo nos observers | Eventos criados | Pico |
|---|---|---|---|---|---|
| 20 × 5 | 0,05 s | 0,10 s | 0,05 s | 14 | 0,2 MB |
| 50 × 10 | 0,18 s | 0,40 s | 0,20 s | 50 | 1,2 MB |
| 100 × 10 | 0,36 s | 0,63 s | 0,34 s | 100 | 1,3 MB |
| 200 × 10 | 0,76 s | 1,20 s | 0,68 s | 200 | 1,4 MB |

- Crescimento linear. Esta operação só alterou o total do curso: gerou **um** `grade_changed` por aluno. **Não foi medido** um recálculo que
  altere cada item (muito mais eventos por aluno), e a série com xdebug ativo foi interrompida antes de terminar (a sessão caiu) e não é
  usada aqui.
- O Moodle já eleva o limite de tempo do recálculo (`\core_php_time_limit::raise()` em `grade_regrade_final_grades`).

**Leitura:** nos volumes medidos o plugin acrescenta menos de 30 s até cerca de 1000 mensagens numa única exclusão; a 2001 mensagens
(com xdebug) passa de 30 s. O problema de desempenho dos observers aparece em exclusões muito grandes. **A alternativa B (buffer com
`enqueue_many` nos observers) fica suspensa:** introduziria uma janela em que o evento só existe em memória, e o ganho só importa nesses
volumes extremos. Nenhuma alternativa que mude esquema ou índice foi autorizada.

O risco de contenção de trava, que apareceu nessa medição e **não** é de volume, está registrado à parte em
[../observer-lock-contention/README.md](../observer-lock-contention/README.md).
