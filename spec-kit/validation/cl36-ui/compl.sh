#!/bin/bash
# compl.sh JAR CMID ESTADO(1|0) -> marca/desmarca a conclusao manual pelo mesmo servico AJAX que o botao da pagina do curso usa
. "$(dirname "$0")/lib36.sh"
jar=$1; cm=$2; st=$3
cu -b $jar -c $jar -o course.html "$BASE/course/view.php?id=5"
sk=$(grep -o '"sesskey":"[^"]*"' course.html | head -1 | sed 's/.*:"//;s/"//')
cu -b $jar -c $jar -w "\ncompletion %{http_code}\n" -H 'Content-Type: application/json' --data "[{\"index\":0,\"methodname\":\"core_completion_update_activity_completion_status_manually\",\"args\":{\"cmid\":$cm,\"completed\":$([ "$st" = 1 ] && echo true || echo false)}}]" "$BASE/lib/ajax/service.php?sesskey=$sk&info=core_completion_update_activity_completion_status_manually"
