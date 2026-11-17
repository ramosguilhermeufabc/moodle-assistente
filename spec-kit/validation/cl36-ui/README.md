# Validação CL-36 pela interface real (T090)

Mesmo método de `../cl33-ui/`, `../cl34-ui/` e `../cl35-ui/`: cliente HTTP com cookies contra o Moodle 5.2.4 real (Apache local), tarefa agendada
real (`admin/cli/scheduled_task.php`). Não foi usado navegador gráfico. Requer `CL33_PASSWORD` (a senha dos usuários criados pelo `prep.php`).
Antes de executar foi preciso `php admin/cli/upgrade.php --non-interactive` no site real (a versão 2026102602 da T088 estava pendente e a tarefa
agendada se recusa a rodar com upgrade pendente) e `purge_caches.php` (cache de observadores).

- `prep.php`: curso `CL36UI` (id 5, `enddate` em 30 dias, conclusão ligada), professor `cl36t`, estudantes `cl36s1` e `cl36s2`, assistente
  habilitado, duas tarefas (uma com envio direto, uma com rascunho), página e arquivo com conclusão manual, fórum.
- `mgr.php`: gerente `cl36m` (usado só para enviar em nome de outro; ver o README da matriz: o professor editor não tem
  `mod/assign:editothersubmission` e nenhum papel o tem por padrão, então o gerente recebeu a capacidade por substituição no curso).
- `lib36.sh`, `snap.sh`, `cron.sh`: pontos de partida, eventos do Moodle, fila do plugin e frequência gravada; tarefa agendada real.
- `submit.sh` (tarefa), `compl.sh` (conclusão manual pelo serviço AJAX do botão), `gv.sh` (nota pela visão única), `../cl35-ui/fp.sh` (fórum).
- `boundary.php`: fronteira temporal da DEC-54 com relógio e banco reais.

Resultados: `spec-kit/closure-register.md`, CL-36, "MATRIZ T090".
