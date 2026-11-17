#!/usr/bin/env python3
"""Busca final de pendências e classificação (etapa 5a, TAREFA 5; repetida pela T140).

Uso, na raiz do repositório:  python3 spec-kit/validation/pendencias-final/classificar.py

Busca (a mesma do prompt, sem os modelos e skills do spec-kit):
  grep -rnE 'NEEDS CLARIFICATION|A CONFIRMAR|Aguardando revisão|\\| (Aberto|Bloqueador|Parcial|Contradição|Risco) \\||BLOQUEADO|^\\s*- \\[ \\]' \\
       spec-kit --include=*.md --exclude-dir=.specify --exclude-dir=.claude
Classes: (i) histórico preservado; (ii) legenda ou rótulo; (iii) pendência conhecida aceita; (iv) pendência não tratada.
As regras estão em closure-register.md (seção "Atualização de 04/11/2026 (etapa 5a)").
"""
import re, subprocess, collections, sys

CMD = (r"grep -rnE 'NEEDS CLARIFICATION|A CONFIRMAR|Aguardando revisão|\| (Aberto|Bloqueador|Parcial|Contradição|Risco) \||BLOQUEADO|incomplet|parcialmente|Pendente de plano|^\s*- \[ \]' "
       r"spec-kit --include=*.md --exclude-dir=.specify --exclude-dir=.claude")
PAT = re.compile(r'NEEDS CLARIFICATION|A CONFIRMAR|Aguardando revisão|\| (?:Aberto|Bloqueador|Parcial|Contradição|Risco) \||BLOQUEADO|incomplet|parcialmente|Pendente de plano|^\s*- \[ \]')
HIST = re.compile(r'texto anterior|texto original|estado original|original|originalmente|preservad|histórico|anteriormente|estado anterior|anterior:|tipo original|substituíd|era[m]?:|eram |Alterado em|alterado em|redação anterior|reavaliado|Reavaliado|superado|Superado', re.I)
SUP = re.compile(r'deixou de valer|deixaram de valer|passa |passou|resolvid|superad|substituíd|Reavaliado em 04/11/2026|reavaliado em 04/11/2026|texto anterior|\(Alterado em|fechad|Fechad|Resolvid|estava Aguardando|histórico', re.I)
# 04/11/2026 (T140, encerramento): a lista de pendências conhecidas ficou só com a T136 (cancelada, DEC-65). Texto anterior preservado:
# KNOWN = re.compile(r'T007|T008|T130|T139|T140|T136|CL-22')
KNOWN = re.compile(r'T136')


# 04/11/2026: termos acrescentados ao padrão (incomplet, parcialmente, Pendente de plano) e as regras R10 a R12 que os classificam.
NEW = ('incomplet', 'parcialmente', 'Pendente de plano')
# vocabulário de domínio ou relação entre decisões, não pendência: "altera parcialmente" (DEC-49 sobre DEC-28), status de resposta da LLM
# ("resposta incompleta", "incomplete_details"), conclusão/evento/dado incompleto, subquestão "parcialmente correta" e o achado F1 da CL-37.
VOC = re.compile(r'altera parcialmente|alterado parcialmente|parcialmente corret|resposta incompleta|incomplete_details|\(incompleto,|conclusão incompleta|evento incompleto|dado incompleto|silenciosamente incompleta')


# 08/11/2026: as comparacoes por caminho desta funcao passaram a comparar o NOME do arquivo
# (f.split('/')[-1]) em vez do caminho relativo a spec-kit/. Motivo: a reorganizacao documental de
# 08/11/2026 moveu source-audit-66.md para spec-kit/history/, e a regra por igualdade exata de
# caminho deixaria de casar, classificando quatro linhas daquele arquivo (a legenda de estados e os
# itens AT-031, AT-058 e AT-065) como pendencia nao tratada. Com o ajuste, o resultado permanece
# 356 ocorrencias: (i) 305, (ii) 46, (iii) 5, (iv) 0. A regra de validation/propagation-review
# continua por prefixo de caminho, porque depende do diretorio e esse diretorio nao foi movido.
# Texto anterior das comparacoes: f == 'source-audit-66.md', f == 'closure-register.md' (duas
# ocorrencias) e f.startswith('decision-register').

def enclosing(t, pos):
    best = None
    for m in re.finditer(r'\*\(.*?\)\*', t):
        if m.start() <= pos < m.end():
            best = m.group(0)
    return best


def classify(f, n, t):
    if t.startswith('**Atualização de 04/11/2026 (etapa 5a)'):
        return 'ii'   # descreve a própria busca e as regras de classificação
    if '**Legenda**' in t or t.startswith('Estados:') or 'Plano | Princípios BLOQUEADO' in t:
        return 'ii'
    if f.startswith('validation/propagation-review') and re.match(r'\| (Contradição|Faltando) \|', t):
        return 'ii'
    if f.split('/')[-1] == 'closure-register.md' and re.search(r'estado máximo possível é|significa um item justificado', t):
        return 'ii'
    # (anterior, até a T140: `or '/checklists/usability-researcher.md' in f` e a regra do §5 BLOQUEADO de 001 e 023, CL-22; encerradas em 04/11/2026)
    if re.match(r'\s*- \[ \] Conferir com leitor de tela', t) and '/checklists/usability-researcher.md' in f:
        return 'iii'   # limitação aceita (CL-41, 03/11/2026): NÃO EXECUTADO; nota na linha seguinte da checklist
    if KNOWN.search(t):
        if not re.search(r'texto anterior|preservad', t, re.I) or re.match(r'\s*- \[ \]', t):
            return 'iii'
    if re.match(r'\| AT-006 \|', t):
        return 'iii'
    ms = list(PAT.finditer(t))
    if ms and all(m.group(0) in NEW and any(v.start() <= m.start() < v.end() for v in VOC.finditer(t)) for m in ms):
        return 'ii'   # R10: só vocabulário de domínio ou relação entre decisões
    ok_all = bool(ms)
    for m in ms:
        pos, tok, ok = m.start(), m.group(0), False
        enc = enclosing(t, pos)
        if enc and HIST.search(enc):
            ok = True                                                  # R1
        if tok == 'NEEDS CLARIFICATION' and re.match(r'NEEDS CLARIFICATION[^|]{0,160}?\(04/11/2026: resolvido', t[pos:pos + 200]):
            ok = True                                                  # R2
        if tok == 'BLOQUEADO' and re.match(r'\| §\d+ [^|]*\| BLOQUEADO \|', t) and t[:pos].count('|') == 2:
            ok = True                                                  # R3 (coluna Pré)
        if t.startswith('**Resultado do gate**') and re.search(r'Reavaliado em 04/11/2026|texto anterior|original', t):
            ok = True                                                  # R5
        if tok == 'BLOQUEADO' and SUP.search(t[pos:]):
            ok = True                                                  # R4
        if tok in ('A CONFIRMAR', 'Aguardando revisão') and (any(q.start() <= pos < q.end() for q in re.finditer(r'"[^"]*"', t)) or SUP.search(t[max(0, pos - 160):pos]) or (tok == 'A CONFIRMAR' and re.search(r'confirma(ç|d)', t[pos:pos + 200]))):
            ok = True                                                  # R6 (citação entre aspas ou nota histórica logo antes)
        if re.search(r'\*\(\d\d/\d\d/2026[^)]{0,400}(confirm|resolvid|feita|superad|deixou de valer|passa|obsoleto|Incorporada|vale só)', t[pos:pos + 600]):
            ok = True                                                  # R8 (nota de superação logo depois)
        if tok.startswith('| ') and re.search(r'tipo original', t, re.I) and re.match(r'\| [A-Z][A-Za-z0-9-]+ \| (Resolvid|Aceito|Mitigad)', t):
            ok = True                                                  # estado novo com tipo original preservado
        if re.search(r'não é o estado atual|[Ee]stado anterior|[Tt]exto anterior preservado', t[max(0, pos - 300):pos]) or re.match(r'A CONFIRMAR\] = ', t[pos:pos + 20]):
            ok = True                                                  # R9 (estado anterior preservado, ou legenda de proveniência da DEC-59)
        if tok in NEW:
            if any(v.start() <= pos < v.end() for v in VOC.finditer(t)):
                ok = True                                              # R10 (ii): vocabulário de domínio (a linha só com esses termos é (ii), ver acima)
            if any(q.start() <= pos < q.end() for q in re.finditer(r'"[^"]*"', t)):
                ok = True                                              # R11 (i): citação entre aspas do estado anterior
            if SUP.search(t[pos:]):
                ok = True                                              # R12 (i): nota de superação ou de histórico na mesma linha, depois do termo (como R4)
            if f.split('/')[-1] == 'source-audit-66.md':
                ok = True                                              # R13 (i): documento marcado como histórico em 04/11/2026
        ok_all = ok_all and ok
    if ok_all:
        return 'i'
    # R7: registros de datas passadas (auditoria de 21/10/2026, relatório de 02/11/2026, passos já feitos)
    if (f.split('/')[-1].startswith('decision-register') and re.match(r'\| AUD-D[45] \||Os artefatos do Plan têm \*\*0 referências', t)) \
            or (f.endswith('quickstart.md') and '002-' in f and re.match(r'\*\*12\. Decisão que depende|\*\*Abertas\.\*\* A T020|- As linhas de decisão do registro que diziam', t)) \
            or (f.split('/')[-1] == 'closure-register.md' and 'Passo 3 feito por revisão com ajustes pontuais' in t):
        return 'i'   # R7: registros de data passada
    return 'iv'


# 04/11/2026: codificação quebrada (UTF-8 lido como Latin-1/cp1252): "Ã" ou "Â" seguida de caractere de continuação, em qualquer .md do spec-kit.
MOJIBAKE = re.compile('[\u00c2\u00c3][\u0080-\u00bf\u20ac\u201a\u0192\u201e\u2026\u2020\u2021\u02c6\u2030\u0160\u2039\u0152\u017d\u2018\u2019\u201c\u201d\u2022\u2013\u2014\u02dc\u2122\u0161\u203a\u0153\u017e\u0178]')


def mojibake():
    import os
    res = []
    for root, dirs, files in os.walk('spec-kit'):
        dirs[:] = [d for d in dirs if d not in ('.specify', '.claude')]
        for name in files:
            if name.endswith('.md'):
                path = os.path.join(root, name)
                for n, line in enumerate(open(path, encoding='utf-8', errors='replace'), 1):
                    if MOJIBAKE.search(line):
                        res.append((path.replace('spec-kit/', '', 1), n, line.rstrip('\n')[:160]))
    return res


def main():
    out = subprocess.run(CMD, shell=True, capture_output=True, text=True).stdout
    cnt, iv = collections.Counter(), []
    for l in out.split('\n'):
        m = re.match(r'([^:]+):(\d+):(.*)', l)
        if not m:
            continue
        f, n, t = m.group(1).replace('spec-kit/', '', 1), int(m.group(2)), m.group(3)
        c = classify(f, n, t)
        cnt[c] += 1
        if c == 'iv':
            iv.append((f, n, t[:160]))
    for f, n, t in mojibake():
        cnt['iv'] += 1
        iv.append((f, n, 'CODIFICAÇÃO QUEBRADA: ' + t))
    print('comando:', CMD)
    print('total:', sum(cnt.values()), dict(cnt))
    for f, n, t in iv:
        print('PENDÊNCIA NÃO TRATADA', f, n, t)
    sys.exit(1 if iv else 0)


main()
