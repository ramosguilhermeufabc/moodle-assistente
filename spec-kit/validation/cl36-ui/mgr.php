<?php
// Cria o gerente cl36m no curso de CL-36 (o professor editor nao pode editar o envio de outra pessoa). Requer CL33_PASSWORD.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$u = $gen->create_user(['username' => 'cl36m', 'password' => getenv('CL33_PASSWORD'), 'firstname' => 'cl36m', 'lastname' => 'Validacao', 'email' => 'cl36m@example.com']);
$gen->enrol_user($u->id, 5, 'manager');
echo $u->id . "\n";
