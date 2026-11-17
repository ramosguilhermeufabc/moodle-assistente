#!/bin/sh
# (c) Duas execucoes simultaneas do despachante sobre o mesmo relatorio vencido, enquanto um terceiro processo segura a trava por 8 s.
# Uso: sh concurrent.sh <agora> (um instante em que o relatorio esta vencido)
cd /var/www/html || exit 1
NOW="$1"
php /workspaces/moodle-assistente/spec-kit/validation/cl39/hold.php 8 &
sleep 1.5
php /workspaces/moodle-assistente/spec-kit/validation/cl39/run.php concurrent "$NOW" > /tmp/cl39-run-a.json &
php /workspaces/moodle-assistente/spec-kit/validation/cl39/run.php concurrent "$NOW" > /tmp/cl39-run-b.json &
wait
echo "--- run A"; cat /tmp/cl39-run-a.json
echo "--- run B"; cat /tmp/cl39-run-b.json
rm -f /tmp/cl39-run-a.json /tmp/cl39-run-b.json
