# Validação CL-34 pela interface real (T080)

Mesmo método de `../cl33-ui/` (cliente HTTP com cookies contra o Moodle 5.2.4 real, tarefa agendada real para a fila); `lib.sh` vem de lá.
Requer `CL33_PASSWORD`. Curso novo (id 3 no site da validação) com `prep.php`: professor `cl34t`, estudantes `cl34s1` e `cl34s2`, professor
não editor `cl34p`, usuário sem matrícula `cl34n`, três tarefas (0 a 100, 5 a 10 e escala de quatro níveis) e um fórum com duas notas.

- `sv.sh USERID ITEMID VALOR [override|clear]`: altera ou limpa uma nota na visão única do gradebook (tela real, modo de edição ligado
  por `POST /editmode.php` com `setmode=1&context=<contexto do curso>`). Notas de tarefa só mudam por substituição.
- Pesos: `POST /grade/edit/tree/index.php?id=3` com `weightoverride_<item>=1&weight_<item>=<peso>` e `sesskey`.
- Apagar um item: `GET /grade/edit/tree/index.php?id=3&action=delete&eid=ig<item>&confirm=1&sesskey=...`.
- `show.sh USERID`: nota persistida e percentual calculado no banco, e linhas das análises do plugin.
- `roles.php`: notas dadas por API a um professor, a um não avaliado e a um usuário sem matrícula (a tela só lista quem pode ser avaliado).
- `manual.php`: cria um item manual descartável; `items.php`: itens que o leitor do plugin devolve.

Resultados: `spec-kit/closure-register.md`, CL-34, "MATRIZ T080".
