#!/usr/bin/env python3
"""Gera spec-kit/specs/023-testes-e-qualidade-transversal/checklists/test-evidence.md (T139; repetido na T140).

Uso (a partir da raiz do repositório):
    python3 spec-kit/validation/t139/generate_evidence.py

Entradas (todas versionadas):
  - matriz de cenários: spec-kit/specs/023-*/tasks.md (seção "Matriz completa");
  - tarefas (arquivos de teste): spec-kit/specs/*/tasks.md (linhas "- [x] Tnnn ...");
  - resultado do PHPUnit por arquivo: spec-kit/validation/t139/phpunit-junit.xml;
  - resultado do Behat por feature: spec-kit/validation/t139/behat-output.txt;
  - estado das CL: spec-kit/closure-register.md;
  - cabeçalho: bloco entre as marcas RESUMO em spec-kit/validation/t139/run.md.

Regras de ligação (não forçadas):
  - PHPUnit: o cenário liga à tarefa da matriz; os arquivos *_test.php citados na linha da tarefa
    (assistente/tests/...) são a evidência; o resultado é o do arquivo no JUnit (granularidade: arquivo,
    não cenário). Sem arquivo citado, arquivo ausente do JUnit ou arquivo só com testes ignorados: "a conferir".
  - Behat: a tarefa (T128) cita "x.feature (NNN Qa–Qb; Qc ...)"; o cenário liga à feature que o cita
    com o seu número de spec e Q. Sem citação inequívoca, ou feature ausente da saída do Behat: "a conferir".
  - Cenários sem arquivo na tarefa, ou cuja tarefa cita arquivos de outros cenários, usam a tabela MANUAL (decisões do
    responsável de 04/11/2026). Uma entrada pode ser 'tests/x_test.php' (nível de arquivo) ou 'tests/x_test.php::metodo'
    (nível de método: o método tem de existir no JUnit e ter passado). A MANUAL tem precedência sobre os arquivos da tarefa.
  - Verificação permanente: todo cenário `| Qn |` dos quickstart.md 001 a 022 tem de ter linha na matriz; se faltar algum,
    o script lista os que faltam e termina com código 1.
  - CL: a evidência é a CL, com o estado do closure-register; nunca conta como teste aprovado.
"""
import glob
import os
import re
import sys
import xml.etree.ElementTree as ET

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..', '..'))
SK = os.path.join(ROOT, 'spec-kit')
T139 = os.path.join(SK, 'validation', 't139')
OUT = os.path.join(SK, 'specs', '023-testes-e-qualidade-transversal', 'checklists', 'test-evidence.md')


def read(p):
    with open(p, encoding='utf-8') as f:
        return f.read()


# ---------- matriz ----------
tasks023 = read(glob.glob(os.path.join(SK, 'specs', '023-*', 'tasks.md'))[0])
matrix = []
for l in tasks023.splitlines():
    m = re.match(r'\| (\d{3}) Q(\d+) \| ([^|]+?) \| ([^|]+?) \|', l)
    if m:
        matrix.append((m.group(1), int(m.group(2)), m.group(3).strip(), m.group(4).strip()))

# ---------- tarefas ----------
task_line = {}
for f in sorted(glob.glob(os.path.join(SK, 'specs', '*', 'tasks.md'))):
    for l in read(f).splitlines():
        m = re.match(r'- \[.\] (T\d+) ', l)
        if m:
            task_line[m.group(1)] = l

TESTFILE = re.compile(r'(?:assistente/)?(tests/[\w/\-]+\.(?:php|feature))')


# Ligações decididas pelo responsável em 04/11/2026 (a tarefa não cita o arquivo; a evidência está no quickstart da 008 e na T142,
# conferida no phpunit-junit.xml da T139). Valem só para estes quatro cenários.
_Q = 'tests/application/quiz/results_handler_parts_test.php'
_MS = 'tests/application/material/material_service_test.php'
_MR = 'tests/infrastructure/persistence/material_repository_test.php'
MANUAL = {
    # 04/11/2026: 008 Q12 a Q14, por correspondência direta entre o resultado esperado do quickstart e o nome do teste (decisão do responsável)
    '008 Q12': ['tests/observer/material_observer_test.php::test_rename_visibility_and_restrictions_create_no_version',
                _MS + '::test_reevaluation_moves_the_material_both_ways_without_a_version',
                _MR + '::test_availability_transitions_leave_versions_and_current_version_alone'],
    '008 Q13': [_MS + '::test_each_version_keeps_the_availability_observed_at_its_creation',
                _MR + '::test_completing_persists_the_summary_and_keeps_the_availability'],
    '008 Q14': [_MS + '::test_no_students_keeps_the_value_held',
                _MS + '::test_availability_none_is_unavailable_and_no_students_is_pending',
                _MS + '::test_failed_evaluation_neither_changes_the_state_nor_stops_the_processing'],
    # 04/11/2026: 011 Q15 a Q25 (DEC-64 e complemento multianswer): a T075 cita outros arquivos; os testes dos cenários estão em results_handler_parts_test
    '011 Q15': [_Q], '011 Q16': [_Q], '011 Q20': [_Q],
    '011 Q17': [_Q + '::test_other_types_keep_their_rules'],
    '011 Q18': [_Q + '::test_randomsamatch_is_out_of_the_indicator'],
    '011 Q19': [_Q + '::test_regrade_with_another_key_is_recounted'],
    '011 Q21': [_Q + '::test_the_count_of_parts_is_used_not_the_fraction'],
    '011 Q22': [_Q + '::test_cloze_counts_the_subquestions_graded_right'],
    '011 Q23': [_Q + '::test_cloze_never_answered_is_no_data', _Q + '::test_cloze_without_a_fraction_is_out'],
    '011 Q24': [_Q + '::test_cloze_mean_leaves_the_unanswered_out', _Q + '::test_cloze_percentage_is_truncated',
                _Q + '::test_cloze_with_one_subquestion_has_no_indicator'],
    '011 Q25': [_Q + '::test_cloze_with_a_numerical_left_out_gives_no_php_warning', _Q + '::test_other_warnings_of_the_count_stay_visible'],
    '008 Q16': ['tests/infrastructure/persistence/material_repository_test.php'],
    '008 Q17': ['tests/infrastructure/persistence/material_monotonic_concurrency_test.php'],
    '008 Q18': ['tests/application/material/material_service_test.php'],
    '017 Q13': ['tests/privacy/erasure_retry_test.php', 'tests/application/privacy/erasure_retry_scheduler_test.php'],
}


def task_files(t, kind):
    """Arquivos de teste citados na linha da tarefa (php para PHPUnit)."""
    ext = '_test.php' if kind == 'PHPUnit' else '.feature'
    out = []
    for p in TESTFILE.findall(task_line.get(t, '')):
        if p.endswith(ext) and p not in out:
            out.append(p)
    return out


# ---------- JUnit ----------
jmeth = {}  # 'tests/x_test.php' -> {metodo: [reprovado_ou_ignorado, ...]}
junit = {}  # 'tests/x_test.php' -> dict(tests, fail, err, skip)
jp = os.path.join(T139, 'phpunit-junit.xml')
if os.path.exists(jp):
    for tc in ET.parse(jp).getroot().iter('testcase'):
        f = tc.get('file', '')
        if 'local/assistente/' not in f:
            continue
        key = f.split('local/assistente/', 1)[1]
        d = junit.setdefault(key, dict(tests=0, fail=0, err=0, skip=0))
        mname = tc.get('name', '').split(' with data set')[0]
        jmeth.setdefault(key, {}).setdefault(mname, []).append(tc.find('failure') is not None or tc.find('error') is not None or tc.find('skipped') is not None)
        d['tests'] += 1
        if tc.find('failure') is not None:
            d['fail'] += 1
        if tc.find('error') is not None:
            d['err'] += 1
        if tc.find('skipped') is not None:
            d['skip'] += 1

# ---------- Behat ----------
behat = {}  # 'tests/behat/x.feature' -> dict(scen, failed)
bp = os.path.join(T139, 'behat-output.txt')
if os.path.exists(bp):
    txt = read(bp)
    failed_section = txt.split('--- Failed scenarios:', 1)[1] if '--- Failed scenarios:' in txt else ''
    for m in re.finditer(r'^\s*Scenario(?: Outline)?: .*?#\s*(\S*local/assistente/(tests/behat/[\w\-]+\.feature)):(\d+)', txt, re.M):
        d = behat.setdefault(m.group(2), dict(scen=0, failed=0))
        d['scen'] += 1
    for m in re.finditer(r'(tests/behat/[\w\-]+\.feature):\d+', failed_section):
        behat.setdefault(m.group(1), dict(scen=0, failed=0))['failed'] += 1


def behat_map():
    """feature -> conjunto de (spec, Q) citados na T128 (e outras tarefas que citem 'x.feature (...)')."""
    mp = {}
    # trechos "; Q5 sem ..." herdam o spec anterior: tratados abaixo
    for t, l in task_line.items():
        for m in re.finditer(r'`([\w\-]+\.feature)` \(([^)]*)\)', l):
            feat = 'tests/behat/' + m.group(1)
            spec = None
            for part in m.group(2).split(';'):
                part = part.strip()
                sm = re.match(r'(\d{3}) (.*)', part)
                if sm:
                    spec, rest = sm.group(1), sm.group(2)
                else:
                    rest = part
                if spec is None:
                    continue
                for a, b in re.findall(r'Q(\d+)(?:\s*[–-]\s*Q?(\d+))?', rest):
                    for q in range(int(a), int(b or a) + 1):
                        mp.setdefault((spec, q), set()).add(feat)
    return mp


BMAP = behat_map()

# ---------- CL ----------
cl_state = {}
for l in read(os.path.join(SK, 'closure-register.md')).splitlines():
    m = re.match(r'\| (CL-\d+) \|', l)
    if m and m.group(1) not in cl_state:
        s = re.search(r'\*\*((?:Fechad[oa]|FECHAD[OA]|Abert[oa]|ABERT[OA]|Bloquead[oa]|BLOQUEAD[OA])[^*]{0,90}?)\*\*', l)
        cl_state[m.group(1)] = s.group(1).strip().rstrip(':') if s else None


def cl_ids(txt):
    # forma abreviada "CL-02, 33–37, 40, 41"
    body = re.search(r'\(([^)]*)\)', txt).group(1)
    ids = []
    for part in re.split(r',', body):
        part = part.strip().replace('CL-', '')
        m = re.match(r'(\d+)\s*[–-]\s*(\d+)$', part)
        if m:
            ids += list(range(int(m.group(1)), int(m.group(2)) + 1))
        elif part.isdigit():
            ids.append(int(part))
    return ['CL-%02d' % i for i in ids]


# ---------- data e versão ----------
def moodle_version():
    run = os.path.join(T139, 'run.md')
    if os.path.exists(run):
        m = re.search(r'Moodle (\d[^,]*?\(Build: \d+\))', read(run))
        if m:
            return 'Moodle ' + m.group(1)
    return 'ver run.md'


def run_date():
    run = os.path.join(T139, 'run.md')
    if os.path.exists(run):
        m = re.search(r'(\d{2}/\d{2}/\d{4})', read(run))
        if m:
            return m.group(1)
    return '?'


DATE, MVER = run_date(), moodle_version()

rows, confer = [], []
n_ok = n_cl = 0
for spec, q, cls, task in matrix:
    name = f'{spec} Q{q}'
    kind = 'CL' if cls.startswith('CL') else ('Behat' if cls.startswith('Behat') else 'PHPUnit')
    if kind == 'CL':
        ids = cl_ids(cls)
        parts = [f'{c}: {cl_state.get(c) or "estado não identificado"}' for c in ids]
        if any(cl_state.get(c) is None for c in ids):
            confer.append((name, cls, task, 'estado da CL não identificado no closure-register'))
            continue
        rows.append((name, cls, f'closure-register.md {"; ".join(parts)}', 'CL (não conta como teste aprovado)', DATE, MVER))
        n_cl += 1
        continue
    if kind == 'PHPUnit':
        files = task_files(task, 'PHPUnit')
        manual = False
        if name in MANUAL:
            files, manual = MANUAL[name], True
        methods = [f for f in files if '::' in f]
        if methods:
            # nível de método: cada método tem de existir no JUnit e ter passado
            bad = [m for m in methods if m.split('::')[0] not in jmeth or m.split('::')[1] not in jmeth[m.split('::')[0]]]
            if bad:
                confer.append((name, cls, task, 'método ausente do JUnit: ' + ', '.join(bad)))
                continue
            bad = [m for m in methods if any(jmeth[m.split('::')[0]][m.split('::')[1]])]
            ids = [m.rsplit('/', 1)[-1].replace('.php', '') for m in methods]
            res = ('REPROVADO' if bad else 'aprovado') + f' ({len(methods)} métodos de teste; nível de método)'
            rows.append((name, cls, '; '.join(ids) + ' (ligação decidida pelo responsável em 04/11/2026)', res, DATE, MVER))
            n_ok += 1
            continue
        if not files:
            confer.append((name, cls, task, 'a tarefa não cita arquivo de teste PHPUnit'))
            continue
        miss = [f for f in files if f not in junit]
        if miss:
            confer.append((name, cls, task, 'arquivo ausente do JUnit: ' + ', '.join(miss)))
            continue
        bad = [f for f in files if junit[f]['fail'] or junit[f]['err']]
        allskip = [f for f in files if junit[f]['skip'] == junit[f]['tests']]
        if allskip:
            confer.append((name, cls, task, 'arquivo só com testes ignorados: ' + ', '.join(allskip)))
            continue
        tot = sum(junit[f]['tests'] for f in files)
        sk = sum(junit[f]['skip'] for f in files)
        res = ('REPROVADO' if bad else 'aprovado') + f' ({tot} testes{", %d ignorados" % sk if sk else ""}; nível de arquivo)'
        rows.append((name, cls, ', '.join(files) + (' (ligação decidida pelo responsável em 04/11/2026)' if manual else ''), res, DATE, MVER))
        n_ok += 1
    else:
        feats = sorted(BMAP.get((spec, q), []))
        if not feats:
            confer.append((name, cls, task, 'nenhuma feature Behat cita este cenário na tarefa'))
            continue
        miss = [f for f in feats if f not in behat]
        if miss:
            confer.append((name, cls, task, 'feature ausente da saída do Behat: ' + ', '.join(miss)))
            continue
        bad = [f for f in feats if behat[f]['failed']]
        res = ('REPROVADO' if bad else 'aprovado') + ' (%d cenários Behat da feature; nível de feature)' % sum(behat[f]['scen'] for f in feats)
        rows.append((name, cls, ', '.join(feats), res, DATE, MVER))
        n_ok += 1

# ---------- verificação permanente: quickstart.md 001 a 022 x matriz ----------
in_matrix = {(a, b) for a, b, _, _ in matrix}
missing = []
for qf in sorted(glob.glob(os.path.join(SK, 'specs', '*', 'quickstart.md'))):
    spec = os.path.basename(os.path.dirname(qf))[:3]
    if not spec.isdigit() or not 1 <= int(spec) <= 22:
        continue
    for q in sorted({int(m.group(1)) for m in re.finditer(r'^\| Q(\d+) \|', read(qf), re.M)}):
        if (spec, q) not in in_matrix:
            missing.append(f'{spec} Q{q}')

# ---------- cabeçalho ----------
head = '(run.md ainda não gravado)'
rp = os.path.join(T139, 'run.md')
if os.path.exists(rp):
    m = re.search(r'<!-- RESUMO:inicio -->(.*?)<!-- RESUMO:fim -->', read(rp), re.S)
    if m:
        head = m.group(1).strip()

out = ['# Evidência por cenário (T139)', '',
       'Gerado por `spec-kit/validation/t139/generate_evidence.py` (repetido na T140). Evidência bruta em `spec-kit/validation/t139/` (`run.md`, `phpunit-output.txt`, `phpunit-junit.xml`, `coverage.txt`, `behat-output.txt`).', '',
       '## Resumo da execução', '', head, '',
       '## Totais da matriz', '',
       f'- Cenários na matriz: {len(matrix)}',
       f'- Com evidência de teste aprovado (PHPUnit/Behat): {n_ok}',
       f'- CL, com a evidência da CL e o estado (não contam como teste aprovado): {n_cl}',
       f'- Em "a conferir": {len(confer)}',
       f'- Cenários dos quickstarts 001 a 022 sem linha na matriz: {len(missing)}' + (' (' + ', '.join(missing) + ')' if missing else ''),
       f'- Reprovados: {sum(1 for r in rows if r[3].startswith("REPROVADO"))}', '',
       '*(04/11/2026: a matriz passou de 289 para 310 cenários com a inclusão de 008 Q12–Q14, 009 Q11–Q17 e 011 Q15–Q25, que já tinham teste aprovado na T139. Por classificação: 265 PHPUnit, 9 Behat e 36 CL; com evidência de teste aprovado, 274 (265 PHPUnit e 9 Behat) e 36 CL; antes: 245 PHPUnit, 9 Behat e 35 CL, 254 aprovados.)*', '',
       '**Granularidade:** a tarefa indica os arquivos de teste; o resultado é o do arquivo (PHPUnit: JUnit; Behat: feature), não o de cada método ou cenário Behat individual. Cenários CL nunca contam como teste aprovado.', '',
       '## Evidência por cenário', '',
       '| Cenário | Classificação | Evidência | Resultado | Data | Versão do Moodle |', '|---|---|---|---|---|---|']
for r in rows:
    out.append('| ' + ' | '.join(x.replace('|', '\\|') for x in r) + ' |')
out += ['', '## A conferir', '']
if confer:
    out += ['| Cenário | Classificação | Tarefa | Motivo |', '|---|---|---|---|']
    for c in confer:
        out.append('| ' + ' | '.join(c) + ' |')
else:
    out.append('Nenhum.')
out.append('')
with open(OUT, 'w', encoding='utf-8') as f:
    f.write('\n'.join(out))
if missing:
    print('CENÁRIOS DOS QUICKSTART SEM LINHA NA MATRIZ DA 023 (%d): %s' % (len(missing), ', '.join(missing)))
print(f'cenarios={len(matrix)} aprovados={n_ok} cl={n_cl} a_conferir={len(confer)} -> {os.path.relpath(OUT, ROOT)}')
sys.exit(1 if missing else 0)
