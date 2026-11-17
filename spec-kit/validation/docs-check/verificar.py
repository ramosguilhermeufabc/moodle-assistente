#!/usr/bin/env python3
"""Conferência da documentação (Fase 4 da reorganização documental, 08/11/2026).

Uso, na raiz do repositório:  python3 spec-kit/validation/docs-check/verificar.py

Cinco checagens de leitura, sem dependência externa, que não alteram nenhum arquivo:

  1. links Markdown relativos cujo destino não existe;
  2. caminhos citados entre backticks que não existem, nos documentos `VIGENTE`;
  3. identificadores DEC/CL/T citados no código PHP sem fonte no registro correspondente;
  4. codepoints de uso privado (U+E000 a U+F8FF) em arquivos de texto do repositório;
  5. rótulo de ciclo de vida e presença no índice, na raiz do spec-kit/ e em history/.

Sai com código 1 se qualquer checagem reprovar. O que ele NÃO faz: dizer se o conteúdo da
documentação é verdadeiro. Ele acha link quebrado, caminho inexistente, identificador pendurado,
resíduo invisível e documento fora do índice; frase desatualizada continua sendo trabalho de
leitura (constituição §10).

A varredura de pendências tem dono próprio e não é repetida aqui:
spec-kit/validation/pendencias-final/classificar.py.

Linha de base de 08/11/2026, com as fases 1 a 3 aplicadas: todas as cinco checagens aprovadas.
"""
import os
import re
import sys

RAIZ = os.getcwd()
SK = "spec-kit"

# Documentos VIGENTE cujos caminhos citados são conferidos pela checagem 2. Os 185 artefatos das
# specs e os documentos dentro das subpastas de validation/ ficam fora: são REGISTRO ou evidência,
# e citam legitimamente caminhos de outras datas.
VIGENTES = [
    "README.md", "CLAUDE.md",
    f"{SK}/README.md", f"{SK}/operacao.md", f"{SK}/onboarding.md",
    f"{SK}/history/README.md", f"{SK}/specs/README.md", f"{SK}/validation/README.md",
]

# Documentos que precisam declarar rótulo de ciclo de vida (checagem 5).
ROTULADOS = [f"{SK}/specs/README.md", f"{SK}/validation/README.md"]

# Pastas que a varredura ignora.
IGNORA = (".git", "vendor", "container-volumes", "node_modules", "lib/pdfparser")

# Markdown de terceiros (skills do Spec Kit e templates), que o projeto não mantém.
IGNORA_MD = (f"{SK}/.claude/", f"{SK}/.specify/templates/")

# Exceção de link, com o motivo e a data. Uma entrada aqui é uma decisão registrada, não um
# silenciamento: ela diz por que o destino não pode ser corrigido.
# 08/11/2026: a célula reproduz literalmente uma linha do plan.md da spec-009, onde o link resolve.
# Corrigir o destino alteraria uma citação preservada (constituição §11).
LINKS_ACEITOS = {
    (f"{SK}/validation/propagation-review/revisao-planos.md", "research.md"),
}

# Checagem 2, regra A: a linha declara o caminho como anterior, movido ou preservado.
ANTERIOR = re.compile(r"anterior|movid|preservad|texto original|caminho antigo|até \d{2}/\d{2}/\d{4}",
                      re.I)
# Checagem 2, regra B: o próprio texto declara que o caminho não existe.
INEXISTENTE = re.compile(r"não existe|nunca criado|nao existe|removid|deixa de existir|não é fonte",
                         re.I)

ROTULO = re.compile(r"\*\*`(VIGENTE|REGISTRO|GERADO|HISTÓRICO)`\*\*")
LINK = re.compile(r"\[[^\]]*\]\(([^)\s#]+)(?:#[^)]*)?\)")
CAMINHO = re.compile(r"`((?:assistente|spec-kit|\.devcontainer)/[A-Za-z0-9_./*-]+)`")


def relativo(p):
    return os.path.relpath(p, RAIZ).replace(os.sep, "/")


def arquivos(exts):
    for dp, dn, fn in os.walk(RAIZ):
        rel = relativo(dp)
        if any(parte in rel for parte in IGNORA):
            continue
        for f in sorted(fn):
            if f.endswith(exts):
                yield os.path.join(dp, f)


def ler(p):
    with open(p, encoding="utf-8", errors="replace") as fh:
        return fh.read()


def checagem_1_links():
    falhas = []
    for p in arquivos((".md",)):
        rel = relativo(p)
        if rel.startswith(IGNORA_MD):
            continue
        for m in LINK.finditer(ler(p)):
            alvo = m.group(1)
            if alvo.startswith(("http://", "https://", "mailto:")):
                continue
            if (rel, alvo) in LINKS_ACEITOS:
                continue
            if not os.path.exists(os.path.normpath(os.path.join(os.path.dirname(p), alvo))):
                falhas.append(f"{rel} -> {alvo}")
    return falhas


def checagem_2_caminhos():
    falhas = []
    for rel in VIGENTES:
        p = os.path.join(RAIZ, rel)
        if not os.path.exists(p):
            falhas.append(f"{rel} (documento VIGENTE ausente)")
            continue
        cabecalho_anterior = False
        for n, linha in enumerate(ler(p).split("\n"), 1):
            if linha.startswith("|") and "---" not in linha:
                if ANTERIOR.search(linha) and "`" not in linha:
                    cabecalho_anterior = True   # linha de cabeçalho da tabela
            elif not linha.startswith("|"):
                cabecalho_anterior = False
            for m in CAMINHO.finditer(linha):
                c = m.group(1)
                if "*" in c or os.path.exists(os.path.join(RAIZ, c)):
                    continue
                if ANTERIOR.search(linha) or INEXISTENTE.search(linha) or cabecalho_anterior:
                    continue
                falhas.append(f"{rel}:{n} -> {c}")
    return falhas


def checagem_3_identificadores():
    reg = ler(os.path.join(RAIZ, SK, "decision-register-DEC-01-25.md"))
    clo = ler(os.path.join(RAIZ, SK, "closure-register.md"))
    tarefas = "".join(ler(p) for p in arquivos(("tasks.md",)) if f"{SK}/specs/" in relativo(p))
    fontes = {
        "DEC": set(re.findall(r"\bDEC-\d{2}\b", reg)),
        "CL": set(re.findall(r"\bCL-\d{2}\b", clo)),
        "T": set(re.findall(r"\bT\d{3}\b", tarefas)),
    }
    usados = {"DEC": set(), "CL": set(), "T": set()}
    for p in arquivos((".php",)):
        if not relativo(p).startswith("assistente/"):
            continue
        for m in re.finditer(r"\b(DEC-\d{2}|CL-\d{2}|T\d{3})\b", ler(p)):
            k = m.group(1)
            usados["DEC" if k.startswith("DEC") else "CL" if k.startswith("CL") else "T"].add(k)
    falhas = []
    for familia, fonte in fontes.items():
        for ident in sorted(usados[familia] - fonte):
            falhas.append(f"{ident} citado no código e ausente da fonte de {familia}")
    contagem = {k: len(v) for k, v in usados.items()}
    return falhas, contagem


def checagem_4_codepoints():
    falhas = []
    for p in arquivos((".md", ".php", ".xml", ".json", ".py", ".sh", ".mustache")):
        n = sum(1 for c in ler(p) if 0xE000 <= ord(c) <= 0xF8FF)
        if n:
            falhas.append(f"{relativo(p)}: {n} codepoint(s) de uso privado")
    return falhas


def checagem_5_indice():
    falhas = []
    pares = [(SK, f"{SK}/README.md"), (f"{SK}/history", f"{SK}/history/README.md")]
    for pasta, indice in pares:
        texto = ler(os.path.join(RAIZ, indice))
        for f in sorted(os.listdir(os.path.join(RAIZ, pasta))):
            if f.startswith(".") or os.path.isdir(os.path.join(RAIZ, pasta, f)):
                continue
            if f == "README.md":
                continue
            if f not in texto:
                falhas.append(f"{pasta}/{f} não está citado em {indice}")
            if f.endswith(".md"):
                cabeca = "\n".join(ler(os.path.join(RAIZ, pasta, f)).split("\n")[:8])
                if not ROTULO.search(cabeca):
                    falhas.append(f"{pasta}/{f} não declara rótulo de ciclo de vida")
    for rel in [f"{SK}/README.md", f"{SK}/history/README.md"] + ROTULADOS:
        cabeca = "\n".join(ler(os.path.join(RAIZ, rel)).split("\n")[:8])
        if not ROTULO.search(cabeca):
            falhas.append(f"{rel} não declara rótulo de ciclo de vida")
    return falhas


def main():
    if not os.path.isdir(os.path.join(RAIZ, SK)):
        print("Rode da raiz do repositório (onde está spec-kit/).", file=sys.stderr)
        return 2

    ident, contagem = checagem_3_identificadores()
    blocos = [
        ("1. links Markdown relativos quebrados", checagem_1_links(),
         f"{len(LINKS_ACEITOS)} exceção(ões) declarada(s) no próprio script"),
        ("2. caminhos citados inexistentes em documento VIGENTE", checagem_2_caminhos(),
         f"{len(VIGENTES)} documentos conferidos"),
        ("3. identificadores do código sem fonte", ident,
         f"DEC {contagem['DEC']}, CL {contagem['CL']}, T {contagem['T']} conferidos"),
        ("4. codepoints de uso privado em arquivo de texto", checagem_4_codepoints(), ""),
        ("5. rótulo de ciclo de vida e presença no índice", checagem_5_indice(), ""),
    ]
    total = 0
    for titulo, falhas, nota in blocos:
        marca = "OK  " if not falhas else "FALHA"
        print(f"{marca} {titulo}: {len(falhas)}" + (f"  ({nota})" if nota else ""))
        for f in falhas:
            print(f"        {f}")
        total += len(falhas)
    print(f"\ntotal de falhas: {total}")
    return 1 if total else 0


if __name__ == "__main__":
    sys.exit(main())
