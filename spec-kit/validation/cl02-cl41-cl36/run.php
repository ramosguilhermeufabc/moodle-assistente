<?php
// Validação T036 (CL-02, CL-41) e T032 (CL-36, leitores) no Moodle 5.2 em execução: materiais (PDF pela File API, página, URL do YouTube),
// idioma forçado e padrão do site, disponibilidade por usuário e distinção atividade x recurso. Curso e usuários temporários (prefixo cl3x), removidos ao final.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$tag = 'cl3x' . substr((string) time(), -6);
$out = [];
$ck = function (string $what, $expected, $actual) use (&$out) {
    $ok = $expected === $actual;
    $out[] = sprintf('%-78s esperado=%-22s obtido=%-22s %s', $what, var_export($expected, true), var_export($actual, true), $ok ? 'OK' : 'FALHA');
};
$c = $gen->create_course(['shortname' => $tag, 'fullname' => 'cl3x validacao', 'lang' => 'pt_br', 'enddate' => 2000000000, 'numsections' => 2]);
$c2 = $gen->create_course(['shortname' => $tag . 'n', 'fullname' => 'cl3x sem idioma']);
$s1 = $gen->create_user(['username' => $tag . 's1', 'lang' => 'en', 'email' => "{$tag}s1@example.com"]);
$s2 = $gen->create_user(['username' => $tag . 's2', 'lang' => 'en', 'email' => "{$tag}s2@example.com"]);
foreach ([$s1, $s2] as $u) { $gen->enrol_user($u->id, $c->id, 'student'); }
$g = $gen->create_group(['courseid' => $c->id, 'name' => 'G1']);
groups_add_member($g->id, $s1->id);

$fs = get_file_storage();
$pdf = $gen->create_module('resource', ['course' => $c->id, 'name' => 'Apostila'], ['section' => 1]);
$fs->delete_area_files(\context_module::instance($pdf->cmid)->id, 'mod_resource', 'content');
$fs->create_file_from_string(['contextid' => \context_module::instance($pdf->cmid)->id, 'component' => 'mod_resource', 'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'apostila.pdf'], '%PDF-1.4 conteudo');
$txt = $gen->create_module('resource', ['course' => $c->id, 'name' => 'Planilha'], ['section' => 1]);
$fs->delete_area_files(\context_module::instance($txt->cmid)->id, 'mod_resource', 'content');
$fs->create_file_from_string(['contextid' => \context_module::instance($txt->cmid)->id, 'component' => 'mod_resource', 'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'dados.csv'], 'a,b');
$page = $gen->create_module('page', ['course' => $c->id, 'name' => 'Pagina', 'content' => '<p>Texto da pagina</p>'], ['section' => 1]);
$yt = $gen->create_module('url', ['course' => $c->id, 'name' => 'Video', 'externalurl' => 'https://www.youtube.com/watch?v=abc123'], ['section' => 2]);
$other = $gen->create_module('url', ['course' => $c->id, 'name' => 'Site', 'externalurl' => 'https://example.com/x'], ['section' => 2]);
$avail = json_encode(['op' => '&', 'c' => [['type' => 'group', 'id' => (int) $g->id]], 'showc' => [false]]);
$assign = $gen->create_module('assign', ['course' => $c->id, 'name' => 'Tarefa restrita', 'availability' => $avail], ['section' => 2]);
$open = $gen->create_module('assign', ['course' => $c->id, 'name' => 'Tarefa aberta', 'duedate' => 1900000000], ['section' => 2]);
rebuild_course_cache($c->id, true);

$mr = new \local_assistente\infrastructure\moodle\material_reader();
$sr = new \local_assistente\infrastructure\moodle\course_section_reader();
$mod = new \local_assistente\infrastructure\moodle\course_module_reader();

// T036 / CL-02: materiais.
$mats = $mr->get_materials($c->id);
$byname = []; foreach ($mats as $m) { $byname[$m->name ?? ''] = $m; }
$ids = array_map(fn($m) => (int) $m->cmid, $mats); sort($ids);
$exp = [(int) $pdf->cmid, (int) $page->cmid, (int) $yt->cmid]; sort($exp);
$ck('materiais = PDF, página e URL do YouTube (CSV e URL comum fora)', $exp, $ids);
$types = []; foreach ($mats as $m) { $types[(int) $m->cmid] = $m->type ?? ($m->material_type ?? null); }
$out[] = 'tipos detectados: ' . json_encode($types);
$f = $mr->get_file($pdf->cmid);
$ck('File API devolve o PDF (nome)', 'apostila.pdf', $f ? $f->get_filename() : null);
$ck('File API devolve o PDF (conteúdo legível)', '%PDF-1.4 conteudo', $f ? $f->get_content() : null);
$pg = $mr->get_page_content($page->cmid);
$ck('conteúdo da página lido', true, $pg && str_contains(json_encode($pg), 'Texto da pagina'));
$ck('URL do YouTube lida', 'https://www.youtube.com/watch?v=abc123', $mr->get_url($yt->cmid));
$ck('URL comum não é material', null, $mr->get_material($c->id, (int) $other->cmid));
$ck('CSV não é material', null, $mr->get_material($c->id, (int) $txt->cmid));
$h1 = $mr->get_material($c->id, (int) $page->cmid);
$out[] = 'material página (campos): ' . json_encode($h1);

// T036 / CL-41: idioma.
$ck('idioma forçado do curso com lang', 'pt_br', $sr->forced_language($c->id));
$ck('curso sem idioma forçado', null, $sr->forced_language($c2->id));
$ck('idioma padrão do site', (string) (get_config('core', 'lang') ?: 'en'), $sr->site_language());
$ck('idioma de geração: o forçado', 'pt_br', $sr->generation_language($c->id));
$ck('idioma de geração sem forçado = padrão do site', $sr->site_language(), $sr->generation_language($c2->id));
$ck('fuso do site', (string) (get_config('core', 'timezone')), $sr->site_timezone());
$ck('course.enddate', 2000000000, $sr->course_end_date($c->id));

// T032 / CL-36: atividade x recurso e disponibilidade por usuário.
$ck('is_resource(resource)', true, $mod->is_resource('resource'));
$ck('is_resource(page)', true, $mod->is_resource('page'));
$ck('is_resource(url)', true, $mod->is_resource('url'));
$ck('is_resource(assign)', false, $mod->is_resource('assign'));
$m1 = $mod->get_module($c->id, (int) $assign->cmid, $s1->id);
$m2 = $mod->get_module($c->id, (int) $assign->cmid, $s2->id);
$out[] = 'módulo restrito (campos) s1: ' . json_encode($m1);
$ck('restrição por grupo: estudante no grupo vê', true, (bool) ($m1->available_to_user ?? false));
$ck('restrição por grupo: estudante fora do grupo não vê', false, (bool) ($m2->available_to_user ?? false));
$o = $mod->get_module($c->id, (int) $open->cmid, $s2->id);
$ck('tarefa sem restrição: disponível ao estudante fora do grupo', true, (bool) ($o->available_to_user ?? false));
$ck('data de entrega lida da tarefa aberta', 1900000000, (int) ($o->duedate ?? $o->due_date ?? 0));

echo implode("\n", $out) . "\n";
$fail = count(array_filter($out, fn($l) => str_ends_with($l, 'FALHA')));
foreach ([$s1, $s2] as $u) { delete_user($u); }
delete_course($c->id, false); delete_course($c2->id, false);
foreach ($DB->get_records_select('tool_recyclebin_category', "shortname LIKE ?", [$tag . '%']) as $r) { (new \tool_recyclebin\category_bin($r->categoryid))->delete_item($r); }
echo 'limpeza: cursos=' . $DB->count_records_select('course', "shortname LIKE ?", [$tag . '%']) . ' usuarios=' . $DB->count_records_select('user', "username LIKE ? AND deleted=0", [$tag . '%']) . "\n";
echo $fail ? "RESULTADO: $fail FALHA(S)\n" : "RESULTADO: TODOS OK\n";
