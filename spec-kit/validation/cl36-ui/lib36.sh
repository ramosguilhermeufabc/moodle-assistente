#!/bin/bash
# funcoes comuns: base (pontos de partida), rep (relatorio apos a acao e a tarefa agendada real)
. "$(dirname "${BASH_SOURCE[0]}")/../cl33-ui/lib.sh"
D="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
M="mysql -h db -umoodle -ppassword moodle -N -e"
base() { B1=$($M "select max(id) from mdl_logstore_standard_log"); B2=$($M "select max(id) from mdl_local_assistente_event"); B3=$($M "select max(id) from mdl_local_assistente_analysis"); }
rep() { echo "### apos a acao, antes da tarefa"; "$D/snap.sh" $B1 $B2 $B3 | sed -n '/--- eventos/,/--- frequencia/p' | head -40; echo "### tarefa agendada real"; "$D/cron.sh"; echo "### apos a tarefa"; "$D/snap.sh" $B1 $B2 $B3; }
