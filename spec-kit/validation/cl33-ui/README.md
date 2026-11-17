# Validação CL-33 pela interface real (T075)

Scripts usados em 26/10/2026 para exercitar o Moodle 5.2.4 real (Apache do Codespace, banco `moodle`, plugin instalado por
`php admin/cli/upgrade.php`) como um navegador, por HTTP com cookies, e processar a fila pela tarefa agendada real
(`php admin/cli/scheduled_task.php --execute='\local_assistente\task\process_queue'`). Não fazem parte do plugin nem dos testes PHPUnit.

Requisitos: `CL33_PASSWORD` definido (senha dos usuários de teste). `curl` fala com `localhost:8080` desviado para a porta 80 do
Apache local (`--connect-to`), porque o `wwwroot` do site é `http://localhost:8080`.

- `prep.php`: curso, professor (`cl33t`), estudantes `cl33s1` e `cl33s2`, assistente habilitado e um questionário com verdadeiro/falso,
  numérica e dissertativa (via gerador de dados do Moodle; os dados reais do site).
- `lib.sh`: `login`, `sesskey`.
- `attempt.sh USER JAR TF NUM TEXTO`: uma tentativa completa pela interface (`startattempt.php`, `processattempt.php`, resumo, envio).
- `mg.sh TENTATIVA SLOT NOTA COMENTARIO`: correção manual pela tela `comment.php` como professor (login prévio em `t.jar`).
- `prep_many_attempts.php`: 40 estudantes com uma tentativa cada, criadas pela API do quiz (não pela interface), só para alongar o lote.
- Reavaliação pela interface: `POST /mod/quiz/report.php` com `regrade=1&sesskey=...&id=<cmid>&mode=overview` como professor.
- Janela da reavaliação: um `LOCK TABLES mdl_quiz_attempts READ` de outra sessão segura o `UPDATE` final de `sumgrades` da etapa
  `update_overall_grades`, sem impedir a reavaliação de cada tentativa; com o lock aberto, a fila é executada; `pkill -KILL -P 1 apache2`
  interrompe o pedido de verdade.

Resultados: `spec-kit/closure-register.md`, CL-33, "MATRIZ T075 (interface real)".
