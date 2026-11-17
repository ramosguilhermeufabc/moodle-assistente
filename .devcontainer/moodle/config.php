<?php

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype = getenv('MOODLE_DBTYPE');
$CFG->dblibrary = 'native';
$CFG->dbhost = getenv('MOODLE_DBHOST');
$CFG->dbname = getenv('MOODLE_DBNAME');
$CFG->dbuser = getenv('MOODLE_DBUSER');
$CFG->dbpass = getenv('MOODLE_DBPASSWORD');
$CFG->prefix = getenv('MOODLE_PREFIX') ?: 'mdl_';

$CFG->dboptions = [
    'dbpersist' => 0,
    'dbport' => getenv('MOODLE_DBPORT'),
    'dbsocket' => '',
];

// wwwroot (DEC-49): no Codespaces, a URL encaminhada da porta 8080 com sslproxy;
// fora dele, http://localhost:8080.
// As variáveis do Codespaces nem sempre chegam ao Apache e ao cron (remoteEnv/secrets só alcançam
// processos lançados pela ferramenta; o container pode ser anterior à configuração). Por isso, na falta
// delas, vale a URL gravada em moodledata por scripts/write-codespaces-wwwroot.sh, lida por Web, CLI e cron.
// Nada vem de cabeçalhos da requisição; o conteúdo do arquivo só vale se tiver o formato esperado.
$codespacename = getenv('CODESPACE_NAME');
$codespacedomain = getenv('GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN');
$codespacewwwroot = '';
if ($codespacename && $codespacedomain) {
    $codespacewwwroot = 'https://' . $codespacename . '-8080.' . $codespacedomain;
} else if (is_readable('/var/www/moodledata/codespaces-wwwroot')) {
    $stored = trim((string) file_get_contents('/var/www/moodledata/codespaces-wwwroot'));
    if (preg_match('~^https://[a-z0-9-]+-8080\.[a-z0-9.-]+$~', $stored)) {
        $codespacewwwroot = $stored;
    }
}
// Requisições do navegador do Behat (Selenium) chegam pelo nome do serviço, em HTTP.
$isbehatrequest = PHP_SAPI !== 'cli' && ($_SERVER['HTTP_HOST'] ?? '') === 'moodle';

if ($codespacewwwroot !== '') {
    $CFG->wwwroot = $codespacewwwroot;
    if (!$isbehatrequest) {
        $CFG->reverseproxy = true;
        $CFG->sslproxy = true;
    }
} else {
    $CFG->wwwroot = 'http://localhost:8080';
}

$CFG->dirroot = '/var/www/html/public';
$CFG->libdir = $CFG->dirroot . '/lib';
$CFG->dataroot = '/var/www/moodledata';

$CFG->admin = 'admin';

// PHPUnit e Behat do Moodle (DEC-48 D1, DEC-49 D1).
$CFG->phpunit_prefix = 'test_';
$CFG->phpunit_dataroot = '/var/www/phpunitdata';

$CFG->behat_prefix = 'beh_';
$CFG->behat_dataroot = '/var/www/behatdata';
$CFG->behat_faildump_path = '/var/www/behatfaildumps';
$CFG->behat_wwwroot = 'http://moodle';
$CFG->behat_profiles = [
    'default' => [
        'browser' => 'chrome',
        'wd_host' => 'http://selenium:4444/wd/hub',
    ],
];

$CFG->lang = 'pt_br';

$CFG->noreplyaddress = 'noreply@example.com';
$CFG->smtphosts = getenv('MOODLE_SMTPHOSTS');

@error_reporting(E_ALL);
@ini_set('display_errors', '1');

$CFG->debug = E_ALL;
$CFG->debugdisplay = 1;

require_once(__DIR__ . '/lib/setup.php');
