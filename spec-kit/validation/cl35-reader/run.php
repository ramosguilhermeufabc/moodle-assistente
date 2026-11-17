<?php
// Validação T035 (CL-35, parte dos readers) no Moodle 5.2 em execução: forum_reader lê discussões, mensagens e o papel genérico de quem postou
// (estudante x professor editor x professor sem edição). Curso e usuários temporários (cl35r), removidos ao final.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$tag = 'cl35r' . substr((string) time(), -5);
$out = [];
$ck = function (string $what, $exp, $act) use (&$out) { $out[] = sprintf('%-70s esperado=%-12s obtido=%-12s %s', $what, var_export($exp, true), var_export($act, true), $exp === $act ? 'OK' : 'FALHA'); };
$c = $gen->create_course(['shortname' => $tag, 'fullname' => 'cl35r']);
$mk = fn($n, $role) => tap($gen, $c, $n, $role, $tag);
function tap($gen, $c, $n, $role, $tag) { $u = $gen->create_user(['username' => $tag . $n, 'lang' => 'en', 'email' => "$tag$n@example.com"]); $gen->enrol_user($u->id, $c->id, $role); return $u; }
$stu = $mk('stu', 'student'); $edt = $mk('edt', 'editingteacher'); $net = $mk('net', 'teacher');
$forum = $gen->create_module('forum', ['course' => $c->id, 'name' => 'Forum'], ['section' => 1]);
$fgen = $gen->get_plugin_generator('mod_forum');
$d = $fgen->create_discussion(['course' => $c->id, 'forum' => $forum->id, 'userid' => $stu->id, 'message' => 'pergunta do estudante']);
$p1 = $fgen->create_post(['discussion' => $d->id, 'parent' => $d->firstpost, 'userid' => $edt->id, 'message' => 'resposta do professor editor']);
$p2 = $fgen->create_post(['discussion' => $d->id, 'parent' => $d->firstpost, 'userid' => $net->id, 'message' => 'resposta do professor sem edicao']);
$fr = new \local_assistente\infrastructure\moodle\forum_reader();
$ck('fórum lido pelo cmid', true, $fr->get_forum($forum->cmid) !== null);
$ck('1 discussão', 1, count($fr->get_discussions($forum->cmid)));
$msgs = $fr->get_messages($d->id);
$ck('3 mensagens', 3, count($msgs));
$roleof = []; foreach ($msgs as $m) { $roleof[(int) $m->user_id] = $m->role; }
$ck('papel do estudante', 'student', $roleof[(int) $stu->id] ?? null);
$ck('papel do professor editor', 'teacher', $roleof[(int) $edt->id] ?? null);
$ck('papel do professor sem edição (teacher)', 'student', $roleof[(int) $net->id] ?? null);
$ck('primeira mensagem com parent_id 0', 0, (int) ($msgs[0]->parent_id ?? -1));
$ck('has_posted do estudante', true, $fr->has_posted($forum->cmid, $stu->id));
$out[] = 'contagem por usuário: ' . json_encode($fr->get_post_counts($forum->cmid));
echo implode("\n", $out) . "\n";
$fail = count(array_filter($out, fn($l) => str_ends_with($l, 'FALHA')));
foreach ([$stu, $edt, $net] as $u) { delete_user($u); }
delete_course($c->id, false);
foreach ($DB->get_records_select('tool_recyclebin_category', "shortname LIKE ?", [$tag . '%']) as $r) { (new \tool_recyclebin\category_bin($r->categoryid))->delete_item($r); }
echo 'limpeza: cursos=' . $DB->count_records_select('course', "shortname LIKE ?", [$tag . '%']) . "\n" . ($fail ? "RESULTADO: $fail FALHA(S)\n" : "RESULTADO: TODOS OK\n");
