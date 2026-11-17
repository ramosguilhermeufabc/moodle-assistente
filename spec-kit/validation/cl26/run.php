<?php
// Validação CL-26 / T041: controle de acesso no Moodle 5.2 em execução (site real), cenários Q1-Q3 e Q7-Q9 do quickstart 005.
// Cria dois cursos e usuários temporários (prefixo cl26), chama o access_service (o backend) por usuário, imprime a matriz e REMOVE tudo.
// Não altera definição de papéis do site, nem version.php. A sobrescrita do Q9 fica no contexto do curso temporário.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/accesslib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$svc = new \local_assistente\application\access_service();
$tag = 'cl26' . substr((string) time(), -6);
$out = [];
$check = function (string $q, string $what, bool $expected, bool $actual) use (&$out) {
    $out[] = sprintf('%-4s %-70s esperado=%-6s obtido=%-6s %s', $q, $what, $expected ? 'SIM' : 'NAO', $actual ? 'SIM' : 'NAO', $expected === $actual ? 'OK' : 'FALHA');
};
$roles = $DB->get_records_menu('role', null, '', 'shortname,id');

// Estado do site: capability e arquétipos padrão como estão instalados.
$cap = $DB->get_record('capabilities', ['name' => 'local/assistente:view'], '*', MUST_EXIST);
$def = $DB->get_records_sql("SELECT r.shortname, rc.permission FROM {role_capabilities} rc JOIN {role} r ON r.id = rc.roleid WHERE rc.capability = ? AND rc.contextid = ? ORDER BY r.shortname",
    ['local/assistente:view', \context_system::instance()->id]);
$out[] = 'capability view: contextlevel=' . $cap->contextlevel . ' captype=' . $cap->captype . '; permissões no sistema: ' . implode(', ', array_map(fn($r) => "$r->shortname=$r->permission", $def));

$a = $gen->create_course(['shortname' => $tag . 'A', 'fullname' => 'CL26 curso A']);
$b = $gen->create_course(['shortname' => $tag . 'B', 'fullname' => 'CL26 curso B']);
$mk = function (string $n) use ($gen, $tag) { return $gen->create_user(['username' => $tag . $n, 'lang' => 'en', 'email' => "$tag$n@example.com"]); };
$prof = $mk('prof'); $profb = $mk('profb'); $nonedit = $mk('nonedit'); $stu = $mk('stu'); $mgr = $mk('mgr'); $custom = $mk('custom'); $removed = $mk('removed');
$gen->enrol_user($prof->id, $a->id, 'editingteacher');
$gen->enrol_user($profb->id, $b->id, 'editingteacher');
$gen->enrol_user($nonedit->id, $a->id, 'teacher');
$gen->enrol_user($stu->id, $a->id, 'student');
role_assign($roles['manager'], $mgr->id, \context_system::instance()->id);
$customrole = create_role('CL26 sem capability', $tag . 'role', 'papel temporario da validacao CL-26');
set_role_contextlevels($customrole, [CONTEXT_COURSE]);

role_assign($customrole, $custom->id, \context_course::instance($a->id)->id);
$gen->enrol_user($removed->id, $a->id, 'editingteacher');
// Q9: papel de professor com a capability negada no curso (sobrescrita local ao curso temporário).
assign_capability('local/assistente:view', CAP_PROHIBIT, $roles['editingteacher'], \context_course::instance($b->id)->id, true);
$gen->enrol_user($removed->id, $b->id, 'editingteacher');

$admin = get_admin();
$check('Q1', 'professor editor no próprio curso A: view', true, $svc->can_view($a->id, $prof->id));
$check('Q1', 'professor editor no próprio curso A: manage', true, $svc->can_manage($a->id, $prof->id));
$check('Q2', 'professor do curso B no curso A (chamada direta): view', false, $svc->can_view($a->id, $profb->id));
$threw = false; try { $svc->require_view($a->id, $profb->id); } catch (\core\exception\required_capability_exception $e) { $threw = true; }
$check('Q2', 'require_view do professor alheio lança required_capability_exception', true, $threw);
$threw = false; try { $svc->require_manage($a->id, $stu->id); } catch (\core\exception\required_capability_exception $e) { $threw = true; }
$check('Q2', 'require_manage do estudante lança required_capability_exception', true, $threw);
$check('Q3', 'administrador do site: view e manage no curso A', true, $svc->can_view($a->id, $admin->id) && $svc->can_manage($a->id, $admin->id));
$check('Q3', 'administrador do site: view no curso B', true, $svc->can_view($b->id, $admin->id));
$check('Q7', 'teacher (sem edição) sem concessão: view', false, $svc->can_view($a->id, $nonedit->id));
$check('Q7', 'estudante: view', false, $svc->can_view($a->id, $stu->id));
$check('Q8', 'manager no contexto de sistema: view no curso A', true, $svc->can_view($a->id, $mgr->id));
$check('Q8', 'manager no contexto de sistema: manage no curso B', true, $svc->can_manage($b->id, $mgr->id));
$check('Q8', 'manager: accessible_course_ids inclui A e B', true, !array_diff([(int) $a->id, (int) $b->id], $svc->accessible_course_ids($mgr->id)));
$check('Q9', 'professor editor com a capability negada no curso B: view', false, $svc->can_view($b->id, $removed->id));
$check('Q9', 'o mesmo usuário (editor) no curso A, sem a negação: view', true, $svc->can_view($a->id, $removed->id));
$check('Q9', 'papel personalizado sem a capability: view', false, $svc->can_view($a->id, $custom->id));
$check('Q1', 'accessible_course_ids do professor = só o curso A', true, $svc->accessible_course_ids($prof->id) === [(int) $a->id]);
$check('--', 'curso inexistente: can_view falso', false, $svc->can_view(99999999, $prof->id));

echo implode("\n", $out) . "\n";
$fail = count(array_filter($out, fn($l) => str_ends_with($l, 'FALHA')));

// Limpeza: usuários, papel e cursos temporários.
foreach ([$prof, $profb, $nonedit, $stu, $mgr, $custom, $removed] as $u) { delete_user($u); }
delete_role($customrole);
delete_course($a->id, false); delete_course($b->id, false);
echo 'limpeza: cursos restantes=' . $DB->count_records_select('course', "shortname LIKE ?", [$tag . '%']) . ' usuarios restantes (não excluídos)=' .
    $DB->count_records_select('user', "username LIKE ? AND deleted = 0", [$tag . '%']) . ' papel restante=' . $DB->count_records('role', ['shortname' => $tag . 'role']) . "\n";
echo $fail ? "RESULTADO: $fail FALHA(S)\n" : "RESULTADO: TODOS OK\n";
exit($fail ? 1 : 0);
