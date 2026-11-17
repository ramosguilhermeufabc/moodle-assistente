# Validação CL-39 do despachante de relatórios (T117)

> **ATENÇÃO: estes scripts operam sobre o site Moodle em que forem executados, que é o site
> compartilhado do ambiente (`/var/www/html/config.php`), e alteram dados dele.**
> - `prep.php` **cria o curso `CL39UI`** (com assistente habilitado, uma tarefa, um questionário e
>   um fórum de datas futuras). Não cria usuários e não registra a tarefa agendada.
> - `run.php` e `hold.php` só funcionam se `CL39UI` existir (falham se não existir). `run.php`
>   cria o relatório e as versões desse curso; o modo `moved` também **altera a data de entrega**
>   da tarefa do curso.
> - Não use em produção. Para limpar, é preciso apagar o curso `CL39UI` pelo Moodle.

Estes scripts rodam o código do plugin sobre esse site **sem a Scheduled Task** (um método de um
curso, por reflexão), porque o despachante varre todos os cursos habilitados, inclusive os que não
podem ser tocados. A Scheduled Task pelo cron real foi validada à parte, numa cópia isolada do
Moodle (prefixo `iso39_`, diretório de dados próprio), removida ao final; os scripts dessa cópia
não são versionados. O procedimento e os resultados estão em `spec-kit/closure-register.md`, CL-39.

Execução com o Moodle, o banco, a Lock API (`mysql_lock_factory`) e o fuso do site reais. O
instante "agora" é informado, para simular a passagem do tempo; nada espera 72 horas.

- `prep.php`: cria o curso `CL39UI`.
- `run.php first|moved|resume|concurrent`: (a) fuso, (d) data mais distante, (b) retomada depois
  de 100 h, (c) uma execução esperando a trava.
- `hold.php` e `concurrent.sh`: (c) um processo segura a trava do relatório por 8 s enquanto duas
  execuções esperam.
