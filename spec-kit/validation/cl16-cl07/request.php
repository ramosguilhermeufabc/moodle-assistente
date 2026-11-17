<?php
// T099: pede a síntese de uma seção pelo gatilho real. Uso: php request.php SECTION_ID
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
$r = (new \local_assistente\application\synthesis\synthesis_trigger())->request_section(6, (int) $argv[1]);
echo $r->status->value . ' event ' . $r->event->id . "\n";
