#!/usr/bin/env python3
"""Gera coverage.txt a partir do bloco 'Code Coverage Report' de phpunit-output.txt (total, por diretório de classes, por classe)."""
import os, re, collections
d = os.path.dirname(os.path.abspath(__file__))
txt = open(os.path.join(d, 'phpunit-output.txt'), encoding='utf-8').read()
blk = txt[txt.index('Code Coverage Report:'):]
summary = blk[:blk.index('local_assistente')].rstrip()
per = re.findall(r'^(local_assistente\\\S+|\S*\.php)\n\s+Methods:\s+([\d.]+)% \(\s*(\d+)/\s*(\d+)\)\s+Lines:\s+([\d.]+)% \(\s*(\d+)/\s*(\d+)\)', blk, re.M)
dirs = collections.OrderedDict()
for name, _, _, _, _, c, t in per:
    parts = name.split('\\')
    key = '\\'.join(parts[:2]) if len(parts) > 1 else name
    a = dirs.setdefault(key, [0, 0, 0]); a[0] += int(c); a[1] += int(t); a[2] += 1
o = ['Cobertura de linhas (pcov 1.0.12), PHPUnit 11.5.55. Sem limiar. Sem relatório HTML.', '',
     'Filtro de cobertura: arquivo de configuração derivado do phpunit.xml gerado pelo Moodle, com o bloco <source> substituído por:',
     '  <directory suffix=".php">public/local/assistente/classes</directory>',
     '  <directory suffix=".php">public/local/assistente/db</directory>',
     '  <file>public/local/assistente/{configure,index,settings,version}.php</file>',
     'Fora do filtro: tests/ (inclusive tests/generator), lang/, templates/ e lib/pdfparser (biblioteca de terceiros, thirdpartylibs.xml).',
     'pcov: -d pcov.enabled=1 -d pcov.directory=/var/www/html/public/local/assistente; XDEBUG_MODE=off.',
     'Obs.: o relatório do PHPUnit lista só as classes com métodos executáveis; o total "Classes" (107/179) conta as classes do filtro, e a linha por classe só aparece para as listadas.', '',
     '== TOTAL ==', summary, '', '== POR DIRETÓRIO DE CLASSES (linhas cobertas/total; classes listadas) ==']
for k, (c, t, n) in dirs.items():
    o.append(f'{k:60s} {100*c/t if t else 0:6.2f}% ({c}/{t})  [{n} classes]')
sc=sum(v[0] for v in dirs.values()); st=sum(v[1] for v in dirs.values())
o += [f'Soma das classes listadas: {sc}/{st} linhas; o total do relatório inclui linhas que ele não atribui a nenhuma classe listada (não detalhadas pelo PHPUnit).']
o += ['', '== POR CLASSE (saída do PHPUnit) ==', blk[blk.index('local_assistente'):].rstrip(), '']
open(os.path.join(d, 'coverage.txt'), 'w', encoding='utf-8').write('\n'.join(o))
print(len(per), 'classes;', len(dirs), 'diretórios')
