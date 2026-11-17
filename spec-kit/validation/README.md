# Scripts de validação em Moodle real

> **`VIGENTE`** · Índice das 17 pastas desta árvore. *(08/11/2026, Fase 4: 18 pastas, com a
> `docs-check/`. Fase 2: o índice passou a descrever as 17 pastas então existentes. Texto anterior desta nota, preservado: "este índice descreve 8 das 17
> pastas desta árvore; completá-lo, e rotular os documentos de cada pasta, depende de autorização
> do responsável.")* Índice dos documentos: [`../README.md`](../README.md). Para subir o ambiente e
> rodar as suítes: [`../operacao.md`](../operacao.md).

Cada pasta tem scripts PHP ou shell de linha de comando que rodam contra o Moodle 5.2 em execução
(`cd /var/www/html && php <script>`), e cada uma tem a sua matriz de validação no item
correspondente de [`../closure-register.md`](../closure-register.md). O resultado de uma validação
técnica **nunca** conta como teste aprovado (DEC-48).

## As 18 pastas

| Pasta | Item | O que valida ou registra | Grava no site? |
|---|---|---|---|
| `cl02-cl41-cl36/` | CL-02, CL-41, CL-36 | `run.php` | sim, cursos e usuários temporários (prefixo `cl3x`) |
| `cl02-publication/` | CL-02 | `run.php` e `worker.php`: publicação e concorrência | sim, cursos e usuários temporários |
| `cl02-t051/` | CL-02, T051, CL-44 | dois cenários com processos reais (V1–V4): exclusão mútua da Scheduled Task e lock por evento (DEC-32), e a hipótese de recuperação da CL-44 — falha real de persistência depois de o handler já ter concluído, sem duplicar o efeito gravado | sim (prefixo `cl02t051`, `cl44v4`); ver os avisos abaixo |
| `cl16-cl07/` | CL-16, CL-07, T099 | a síntese pedagógica no Moodle real: tarefa agendada real (`scheduled_task.php --execute='\local_assistente\task\process_queue'`), lock `mysql_lock_factory` (`GET_LOCK`) e processos PHP independentes para a concorrência | sim |
| `cl26/` | CL-26 | `run.php` | sim, cursos e usuários temporários (prefixo `cl26`) |
| `cl33-ui/` | CL-33, T075 | análise inicial e métricas de questionário pela interface real: cliente HTTP com cookies como se fosse um navegador, e a tarefa agendada real para processar a fila. `lib.sh` daqui é reaproveitado pelas outras pastas `*-ui` | sim; exige `CL33_PASSWORD` |
| `cl34-ui/` | CL-34, T080 | notas e desempenho, mesmo método da `cl33-ui/` | sim (professor `cl34t`, estudantes `cl34s1` e `cl34s2`); exige `CL33_PASSWORD` |
| `cl35-reader/` | CL-35 | `run.php` | sim, cursos e usuários temporários (prefixo `cl35r`) |
| `cl35-ui/` | CL-35, T084 | fóruns e dificuldades, mesmo método da `cl33-ui/` | sim (professor `cl35t`, estudantes `cl35s1` e `cl35s2`); exige `CL33_PASSWORD` |
| `cl36-ui/` | CL-36, T090 | frequência e participação, mesmo método da `cl33-ui/`, sem navegador gráfico | sim; exige `CL33_PASSWORD` |
| `cl39/` | CL-39, T117 | o despachante de relatórios, rodado **sem** a Scheduled Task (um método de um curso, por reflexão), porque o despachante varre todos os cursos habilitados | sim; ver os avisos abaixo |
| `a2-initial-sync-cost/` | A2, risco R-020-01 | **sem scripts.** Registro das medições de custo da sincronização inicial, com a separação entre o medido, o emulado, as extrapolações e o que não foi medido. Não fecha a CL-40 | não |
| `observer-lock-contention/` | risco aberto | **sem scripts.** Registro de um risco: espera de até 30 s por entrega e evento perdido sem recuperação quando uma trava fica presa. Separado do diagnóstico de desempenho da `a2-initial-sync-cost/`. Não resolve nem altera CL-02, CL-07 ou CL-40 | não |
| `t130/` | T130 | preparação e verificação da checagem de usabilidade feita pelo responsável: `prepare_course.php` monta os cursos T130A e T130B, `verify_course.php` confere, e `verify-output.txt` é a saída registrada. O resultado está em `../specs/023-*/checklists/usability-records.md` | sim, cursos de teste |
| `t139/` | T139, T140 | execução completa das suítes em 04/11/2026: `run.md` com os comandos exatos e os números, `phpunit-output.txt`, `phpunit-junit.xml`, `behat-output.txt`, `coverage.txt`, a configuração de cobertura `phpunit-t139.xml`, e os scripts `coverage_summary.py` e `generate_evidence.py`, que geram `../specs/023-*/checklists/test-evidence.md` | não; é evidência de execução |
| `pendencias-final/` | etapa 5a, T140 | `classificar.py`: varredura de pendências em todo o `spec-kit/`, classificada em histórico preservado, legenda, limitação aceita e pendência não tratada. Roda da raiz do repositório e sai com código 1 se achar pendência não tratada. É a evidência que a tabela "Estado atual" cita para "Pendências abertas: nenhuma" | não |
| `propagation-review/` | revisão de propagação | `revisao-propagacao.md` e `revisao-planos.md`: revisões de 04 e 08/11/2026 da propagação das decisões aos artefatos. Ambos trazem aviso de documento histórico | não |
| `docs-check/` | Fase 4 da reorganização documental | `verificar.py`: cinco checagens de leitura sobre a documentação — links relativos quebrados, caminhos citados inexistentes em documento `VIGENTE`, identificadores `DEC`/`CL`/`T` citados no código sem fonte, codepoints de uso privado em arquivo de texto, e rótulo de ciclo de vida mais presença no índice. Roda da raiz do repositório e sai com código 1 se qualquer checagem reprovar. Não diz se o conteúdo é verdadeiro | não |

## Avisos antes de rodar qualquer script

- **Os scripts operam sobre o site Moodle em que forem executados.** Vários criam cursos e
  usuários temporários e os removem ao final. Os prefixos em uso são `cl26`, `cl3x`, `cl35r`,
  `cl02t051` e `cl44v4`.
- Se um deles falhar no meio, `php cleanup_temp.php --dry-run` lista o resíduo e
  `php cleanup_temp.php` o remove.
- Nenhum deles altera `version.php`, roda `upgrade.php` ou toca nos cursos 5 e 6.
- O Moodle mantém o usuário removido como registro, com `deleted = 1`.
- As pastas `*-ui` exigem a variável `CL33_PASSWORD`, que é a senha dos usuários criados pelo
  `prep.php` de cada uma.
- Em `cl02-t051/`, dois avisos específicos: `v2_cycle.php` processa **qualquer** evento pendente do
  site, não só o de teste; e `v4_worker.php` grava um efeito real e imutável se for apontado para
  um evento de produção. Leia o README da pasta antes.
- Em `cl39/`, o despachante varre todos os cursos habilitados, inclusive os que não fazem parte do
  cenário; é por isso que o script chama um método de um curso por reflexão, em vez de rodar a
  Scheduled Task.
