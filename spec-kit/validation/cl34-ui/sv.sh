#!/bin/bash
# sv.sh USERID ITEMID VALOR [override|clear] -> altera uma nota na visão única do gradebook (tela real), como professor (t.jar)
. "$(dirname "$0")/../cl33-ui/lib.sh"
uid=$1; item=$2; val=$3; mode=${4:-override}
url="$BASE/grade/report/singleview/index.php?id=3&item=user&itemid=$uid"
cu -b t.jar -c t.jar -o p.html "$url"
sk=$(grep -o 'name="sesskey" value="[^"]*"' p.html | head -1 | sed 's/.*value="//;s/"//')
old=$(grep -o "name=\"oldfinalgrade_${item}_${uid}\" value=\"[^\"]*\"" p.html | sed 's/.*value="//;s/"//')
oo=$(grep -o "name=\"oldoverride_${item}_${uid}\"[^>]*" p.html | grep -o 'value="[^"]*"' | sed 's/value="//;s/"//')
args=(--data-urlencode "sesskey=$sk" --data-urlencode "oldfinalgrade_${item}_${uid}=$old" --data-urlencode "oldfeedback_${item}_${uid}=" --data-urlencode "oldoverride_${item}_${uid}=$oo" --data-urlencode "oldexclude_${item}_${uid}=" --data-urlencode "feedback_${item}_${uid}=")
if [ "$mode" = "override" ]; then args+=(--data-urlencode "override_${item}_${uid}=1" --data-urlencode "finalgrade_${item}_${uid}=$val"); else args+=(--data-urlencode "finalgrade_${item}_${uid}="); fi
cu -b t.jar -c t.jar -o r.html -w "singleview POST %{http_code}\n" "${args[@]}" "$url"
grep -o 'alert[^>]*>[^<]*<[^>]*>[^<]*' r.html | head -2
