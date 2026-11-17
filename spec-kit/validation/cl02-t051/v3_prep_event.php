<?php
// Cria um evento pending (reference_date_reached) num curso descartavel ja existente, dados o
// course_id e o cmid de um modulo desse curso por argv (por exemplo, os retornados por v2_prep.php
// ou v4_prep.php), para alimentar o worker do teste de recuperacao de worker interrompido (V3).
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
use local_assistente\domain\event\event_type;
use local_assistente\infrastructure\persistence\event_repository;
$courseid = (int) $argv[1];
$cmid = (int) $argv[2];
$now = time();
$event = (new event_repository())->create_event(
    $courseid,
    event_type::REFERENCE_DATE_REACHED,
    ['cmid' => $cmid, 'reference_date' => $now - 1],
    $now
);
echo json_encode(['event' => (int) $event->id]) . "\n";
