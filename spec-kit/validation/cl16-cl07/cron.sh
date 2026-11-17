#!/bin/bash
cd /var/www/html && php admin/cli/scheduled_task.php --execute='\local_assistente\task\process_queue' 2>&1 | grep -E "complete|queue cycle|rror" | head -3
