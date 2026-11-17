#!/bin/bash
# run_conc.sh MODO SECAO N_PROCESSOS RODADAS -> N processos PHP simultaneos; imprime a saida de cada um
mode=$1; section=$2; n=$3; rounds=$4
start=$(php -r 'echo microtime(true) + 4;')
cd /var/www/html
for i in $(seq 1 $n); do php /workspaces/moodle-assistente/spec-kit/validation/cl16-cl07/worker.php $mode $section $rounds $start w$i 2>&1 | grep -E "^worker|rror|xception" & done
wait
