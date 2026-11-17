<?php
// Remove os dados temporários deixados por scripts de validação que falharam no meio (cursos, usuários e papéis com os prefixos abaixo,
// mais a lixeira de cursos). Só toca em registros com esses prefixos; nunca nos cursos do site. Uso: php cleanup_temp.php [--dry-run]
//
// delete_course() aciona o observer do próprio plugin (course_observer::course_deleted), que já apaga os registros de
// local_assistente_* do curso (event, config, processing, analysis etc.) — por isso este script nunca executa DELETE direto
// nessas tabelas; cobrir um curso aqui é suficiente para cobrir também os dados do plugin daquele curso.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
\core\session\manager::set_user(get_admin());
$dry = in_array('--dry-run', $argv, true);
$prefixes = ['cl26', 'cl3x', 'cl35r', 'cl02t051', 'cl44v4'];  // 'cl26' cobre também 'cl26pub'; 'cl02t051' e 'cl44v4' são os
// cursos/usuário descartáveis de `cl02-t051/` (CL-02/T051/CL-44: shortnames `CL02T051`, `CL44V4`, usuário `cl44v4student`)
$like = fn(string $f) => '(' . implode(' OR ', array_map(fn($p) => "$f LIKE '$p%'", $prefixes)) . ')';
$courses = $DB->get_records_select('course', $like('shortname'));
$users = $DB->get_records_select('user', $like('username') . ' AND deleted = 0');
$roles = $DB->get_records_select('role', $like('shortname'));
$bin = $DB->get_records_select('tool_recyclebin_category', $like('shortname'));
echo count($courses) . " cursos, " . count($users) . " usuarios, " . count($roles) . " papeis, " . count($bin) . " itens na lixeira" . ($dry ? " (dry-run)\n" : "\n");
if ($dry) { exit(0); }
foreach ($courses as $c) { delete_course($c->id, false); }
foreach ($users as $u) { delete_user($u); }
foreach ($roles as $r) { delete_role($r->id); }
foreach ($DB->get_records_select('tool_recyclebin_category', $like('shortname')) as $r) { (new \tool_recyclebin\category_bin($r->categoryid))->delete_item($r); }
echo "restantes: " . $DB->count_records_select('course', $like('shortname')) . "/" . $DB->count_records_select('user', $like('username') . ' AND deleted=0') . "/" . $DB->count_records_select('role', $like('shortname')) . "/" . $DB->count_records_select('tool_recyclebin_category', $like('shortname')) . "\n";
