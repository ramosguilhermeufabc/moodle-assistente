# Validação CL-35 pela interface real (T084)

Mesmo método de `../cl33-ui/` e `../cl34-ui/` (cliente HTTP com cookies contra o Moodle 5.2.4 real; `lib.sh` vem de `../cl33-ui/`).
Requer `CL33_PASSWORD`. `prep.php` cria o curso, o professor `cl35t`, os estudantes `cl35s1` e `cl35s2`, habilita o assistente e cria um fórum
geral. Depois de mudar `db/events.php` é preciso `php admin/cli/purge_caches.php`: o cache de observadores do Moodle não vê o arquivo novo.

- `fp.sh JAR MODO ALVO ASSUNTO MENSAGEM`: operações reais de `post.php`: `new` (alvo = id do fórum), `reply`, `edit` e `delete` (alvo = id da
  mensagem; excluir a primeira mensagem exclui a discussão). `PRIV=1` marca a resposta como privada.
- `snap.sh BASE_LOG BASE_FILA`: eventos do `mod_forum` no registro do Moodle e eventos novos da fila do plugin.
- `capture.php`: roda a fila real com um adaptador simulado que imprime o contexto enviado à LLM (para conferir privacidade e modos).

Resultados: `spec-kit/closure-register.md`, CL-35, "MATRIZ T084".
