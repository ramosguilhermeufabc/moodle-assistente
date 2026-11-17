#!/bin/bash
# submit.sh JAR CMID TEXTO -> envia (ou salva) o texto online de uma tarefa pela tela real do aluno; com SUBMIT=1 depois envia para avaliacao
. "$(dirname "$0")/lib36.sh"
jar=$1; cm=$2; txt=${3:-Resposta}
cu -b $jar -c $jar -o sub.html "$BASE/mod/assign/view.php?id=$cm&action=editsubmission"
args=(); while IFS= read -r l; do n=$(echo "$l" | grep -o 'name="[^"]*"' | head -1 | sed 's/name="//;s/"//'); v=$(echo "$l" | grep -o 'value="[^"]*"' | head -1 | sed 's/value="//;s/"$//'); case $n in "onlinetext_editor[text]") ;; "") ;; *) args+=(--data-urlencode "$n=$v");; esac; done < <(grep -o '<input[^>]*type="hidden"[^>]*>' sub.html)
cu -b $jar -c $jar -o subres.html -w "savesubmission POST %{http_code} %{url_effective}\n" "${args[@]}" --data-urlencode "onlinetext_editor[text]=<p>$txt</p>" --data-urlencode "submitbutton=Save changes" "$BASE/mod/assign/view.php"
grep -o 'Submission status[^<]*<[^>]*>[^<]*<[^>]*>[^<]*<[^>]*>[^<]*' subres.html | head -1
grep -io 'Draft (not submitted)\|Submitted for grading' subres.html | sort -u
