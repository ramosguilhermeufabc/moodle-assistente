#!/bin/bash
# gv.sh JAR USERID ITEMID VALOR|clear -> altera uma nota na visao unica do gradebook (tela real), como professor; curso 5
. "$(dirname "$0")/lib36.sh"
jar=$1; uid=$2; item=$3; val=$4
url="$BASE/grade/report/singleview/index.php?id=5&item=user&itemid=$uid"
cu -b $jar -c $jar -o p.html "$url"
sk=$(grep -o 'name="sesskey" value="[^"]*"' p.html | head -1 | sed 's/.*value="//;s/"//')
old=$(grep -o "name=\"oldfinalgrade_${item}_${uid}\" value=\"[^\"]*\"" p.html | sed 's/.*value="//;s/"//')
oo=$(grep -o "name=\"oldoverride_${item}_${uid}\"[^>]*" p.html | grep -o 'value="[^"]*"' | sed 's/value="//;s/"//')
args=(--data-urlencode "sesskey=$sk" --data-urlencode "oldfinalgrade_${item}_${uid}=$old" --data-urlencode "oldfeedback_${item}_${uid}=" --data-urlencode "oldoverride_${item}_${uid}=$oo" --data-urlencode "oldexclude_${item}_${uid}=" --data-urlencode "feedback_${item}_${uid}=")
if [ "$val" != "clear" ]; then args+=(--data-urlencode "override_${item}_${uid}=1" --data-urlencode "finalgrade_${item}_${uid}=$val"); else args+=(--data-urlencode "finalgrade_${item}_${uid}="); fi
cu -b $jar -c $jar -o r.html -w "singleview POST %{http_code}\n" "${args[@]}" "$url"
