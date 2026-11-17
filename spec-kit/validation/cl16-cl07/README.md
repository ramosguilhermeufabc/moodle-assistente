# Validação CL-16 e CL-07 da síntese no Moodle real (T099)

Método: scripts PHP de linha de comando contra o Moodle 5.2.4 real (site local, MariaDB 11.4.13, lock `mysql_lock_factory`, ou seja `GET_LOCK`),
a tarefa agendada real (`admin/cli/scheduled_task.php --execute='\local_assistente\task\process_queue'`) e processos PHP independentes para a
concorrência. Não houve navegador nem pedidos HTTP: a síntese não tem tela nesta etapa. Os observers reais dispararam os eventos (`grade_update`).

- `prep.php`: cria UMA vez o curso controlado `CL16CL07` (id 6), dois estudantes, o assistente habilitado, uma tarefa com nota e um fórum na
  seção comum, uma subseção delegada com um fórum, e uma página. Não cria nada no curso 5.
- `resolve.php JSON`: matriz de resolução de seção (CL-16), pelas APIs reais.
- `grade.php`, `request.php`, `snap.sh`, `cron.sh`: dar nota, pedir a síntese de uma seção, ver fila e sínteses, rodar a tarefa agendada.
- `worker.php` e `run_conc.sh`: concorrência (CL-07) com N processos que partem no mesmo instante: `save` (repositório com lock e transação),
  `nolock` (escritor que pulou o lock, só o índice único protege) e `handler` (síntese completa com uma LLM lenta).

Dados deixados no site (identificáveis, nada apagado): curso 6 e seus eventos; sínteses das seções do curso 6; 32 versões de `worker.php save`
na seção 3 (id 19); 20 versões e 12 análises sem versão de `worker.php nolock` na seção 2 (id 18) (as análises órfãs são o efeito esperado de gravar
sem transação); uma versão sintética (fingerprint `ffff…`) na seção delegada (id 20); o evento 453 em `failure` (seção de outro curso).

Resultados: `spec-kit/closure-register.md`, CL-16 e CL-07, "MATRIZ T099".
