#!/bin/sh
# Executa o cron do Moodle a cada minuto (DEC-49 D3), como www-data.
# O caminho de admin/cli é detectado porque o Moodle usa o layout public/ (5.1+).

if [ -f /var/www/html/public/admin/cli/cron.php ]; then
    CRON_PHP=/var/www/html/public/admin/cli/cron.php
else
    CRON_PHP=/var/www/html/admin/cli/cron.php
fi

while true; do
    su -s /bin/sh www-data -c "php ${CRON_PHP}" || echo "cron: execução falhou (Moodle instalado?)" >&2
    sleep 60
done
