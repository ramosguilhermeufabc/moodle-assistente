#!/bin/bash
# mg.sh attempt slot mark comment -> correção manual pela tela comment.php como o professor
. ./lib.sh
att=$1; slot=$2; mark=$3; com=$4
cu -b t.jar -c t.jar -o c.html "$BASE/mod/quiz/comment.php?attempt=$att&slot=$slot&cmid=1"
p="q$att:${slot}_"
seq=$(grep -o "name=\"${p}:sequencecheck\" value=\"[0-9]*\"" c.html | grep -o 'value="[0-9]*"' | grep -o '[0-9]*')
item=$(grep -o "name=\"${p}-comment:itemid\" value=\"[0-9]*\"" c.html | grep -o 'value="[0-9]*"' | grep -o '[0-9]*')
sk=$(grep -o 'name="sesskey" value="[^"]*"' c.html | head -1 | sed 's/.*value="//;s/"//')
cu -b t.jar -c t.jar -o /dev/null -w "comment.php POST %{http_code}\n" --data-urlencode "${p}:sequencecheck=$seq" --data-urlencode "${p}-comment=$com" --data-urlencode "${p}-comment:itemid=$item" --data-urlencode "${p}-commentformat=1" --data-urlencode "${p}-mark=$mark" --data-urlencode "${p}-maxmark=1" --data-urlencode "${p}:minfraction=0" --data-urlencode "${p}:maxfraction=1" --data "attempt=$att&slot=$slot&slots=$slot&sesskey=$sk&submit=Save" "$BASE/mod/quiz/comment.php"
