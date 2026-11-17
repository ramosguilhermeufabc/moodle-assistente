#!/bin/bash
# fp.sh JAR MODO ALVO ASSUNTO MENSAGEM -> operações reais do fórum (post.php) como o usuário do cookie
#   MODO new (ALVO = id do fórum), reply (ALVO = id da mensagem), edit (ALVO = id da mensagem), delete (ALVO = id da mensagem)
. "$(dirname "$0")/../cl33-ui/lib.sh"
jar=$1; mode=$2; target=$3; subject=${4:-Assunto}; message=${5:-Texto}
[ -n "$PRIV" ] && PRIVQ="&privatereply=1"
case $mode in new) url="$BASE/mod/forum/post.php?forum=$target";; reply) url="$BASE/mod/forum/post.php?reply=$target$PRIVQ";; edit) url="$BASE/mod/forum/post.php?edit=$target";; delete) url="$BASE/mod/forum/post.php?delete=$target";; esac
cu -b $jar -c $jar -o form.html "$url"
args=()
if [ "$mode" = "delete" ]; then
  # tela de confirmação: usa o formulário de confirmação do Moodle
  while IFS= read -r line; do n=$(echo "$line" | grep -o 'name="[^"]*"' | head -1 | sed 's/name="//;s/"//'); v=$(echo "$line" | grep -o 'value="[^"]*"' | head -1 | sed 's/value="//;s/"$//'); [ -n "$n" ] && args+=(--data-urlencode "$n=$v"); done < <(sed -n '/<form[^>]*post.php/,/<\/form>/p' form.html | grep -o '<input[^>]*type="hidden"[^>]*>')
  cu -b $jar -c $jar -o res.html -w "delete POST %{http_code} %{url_effective}\n" "${args[@]}" "$BASE/mod/forum/post.php"
else
  while IFS= read -r line; do n=$(echo "$line" | grep -o 'name="[^"]*"' | head -1 | sed 's/name="//;s/"//'); v=$(echo "$line" | grep -o 'value="[^"]*"' | head -1 | sed 's/value="//;s/"$//;s/&amp;/\&/g'); case $n in subject|"message[text]"|"message[format]"|submitbutton|"") ;; *) args+=(--data-urlencode "$n=$v");; esac; done < <(grep -o '<input[^>]*type="hidden"[^>]*>' form.html | grep 'name=')
  [ -n "$PRIV" ] && args+=(--data-urlencode "isprivatereply=1")
  cu -b $jar -c $jar -o res.html -w "$mode POST %{http_code} %{url_effective}\n" "${args[@]}" --data-urlencode "subject=$subject" --data-urlencode "message[text]=<p>$message</p>" --data-urlencode "message[format]=1" --data-urlencode "submitbutton=Post to forum" "$BASE/mod/forum/post.php"
fi
