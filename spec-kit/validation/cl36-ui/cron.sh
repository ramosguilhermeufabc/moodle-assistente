#!/bin/bash
# cron.sh -> executa a tarefa agendada real de processamento da fila
cd /var/www/html && php admin/cli/scheduled_task.php --execute='\local_assistente\task\process_queue' 2>&1 | tail -4
