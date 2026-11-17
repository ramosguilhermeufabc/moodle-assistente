# Histórico do Spec Kit — índice

> **`VIGENTE`** · Este índice descreve a pasta; os artefatos indexados são todos `HISTÓRICO`.

Esta pasta guarda os artefatos que são **retrato de uma data** e não descrevem o projeto de hoje.
Nenhum deles é fonte de requisito, de estado, de decisão ou de esquema. Eles ficam no repositório
porque a constituição §11 manda preservar o texto substituído, e ficam **separados** dos
documentos vigentes para que não sejam lidos como orientação atual.

Para o que vale hoje: o índice dos documentos é [`../README.md`](../README.md); o estado do
projeto é de [`../closure-register.md`](../closure-register.md); as decisões são de
[`../decision-register-DEC-01-25.md`](../decision-register-DEC-01-25.md); a ordem e as
dependências das specs são de [`../specs/README.md`](../specs/README.md).

## Artefatos

| Artefato | Caminho anterior | Data do retrato | Por que é histórico |
|---|---|---|---|
| [`migracao-estrutural.md`](migracao-estrutural.md) | corpo de `spec-kit/README.md` | 21/10/2026 | registro da migração estrutural das 23 especificações do pacote de origem. Duas afirmações dele já estavam marcadas como superadas em 08/11/2026: a de que não havia `plan.md` nem `tasks.md`, e a tabela de dependências, cuja versão vigente é a de `../specs/README.md` |
| [`install_auditado.xml`](install_auditado.xml) | `spec-kit/install_auditado.xml` | esquema de 08/11/2026 | cópia congelada do esquema auditado. A fonte primária do esquema é `assistente/db/install.xml` (constituição §1). Era byte a byte idêntico a ela, sha256 `88ddda4c…dd7e`, medido antes do comentário de cópia congelada acrescentado no topo do arquivo. A fidelidade da transcrição está registrada na T011 de `../specs/002-esquema-xmldb/tasks.md` |
| [`source-audit-66.md`](source-audit-66.md) | `spec-kit/source-audit-66.md` | 21/10/2026 | reconciliação dos 66 requisitos candidatos na Etapa 1, já marcada como histórica em 04/11/2026. A matriz vigente é `../requirements-traceability.md` |
| [`decision-propagation.md`](decision-propagation.md) | `spec-kit/decision-propagation.md` | cobria DEC-01 a DEC-25 | plano de reconciliação das decisões com as specs, de uma fase concluída. Recebeu nota de escopo em 08/11/2026. A execução da propagação está na §3 de `../closure-register.md` |
| [`research.md`](research.md) | `spec-kit/research.md` | 21/10/2026 | notas de pesquisa do pacote de origem. Contém marcadores de citação não resolvíveis, registrados como achado da análise de 08/11/2026 |
| [`PROMPT-CLAUDE-SPECKIT.md`](PROMPT-CLAUDE-SPECKIT.md) | `spec-kit/PROMPT-CLAUDE-SPECKIT.md` | 21/10/2026 | o próprio arquivo se declara orientação histórica consolidada e remete aos quatro prompts |
| [`prompts/PROMPT-1…4`](prompts/) | `spec-kit/prompts/` | 21/10/2026 | os quatro prompts das fases Create, Configure, Plan e Tasks, todas executadas e encerradas. A sequência obrigatória está na constituição §3 |
| [`migration-manifest.json`](migration-manifest.json) | `spec-kit/migration-manifest.json` | 21/10/2026 | manifesto da migração estrutural, arquivo a arquivo. Não é citado por nenhum documento, script ou código |

Cada arquivo `.md` desta pasta recebeu, no topo, uma linha de procedência com a data do move e o
caminho anterior. O conteúdo não foi editado. Em `install_auditado.xml` a procedência está num
comentário XML no início do arquivo, e em `migration-manifest.json`, que não aceita comentário,
está apenas nesta tabela.

Em `migracao-estrutural.md` os destinos de link foram repontados por causa da mudança de pasta —
os 23 da tabela de dependências, que já estavam quebrados antes do move, passaram a
`../specs/NNN-slug/spec.md`. Nenhuma palavra do texto foi alterada.

## Lacuna que já não existe

Até 08/11/2026 as skills do projeto não ficavam disponíveis na raiz de um clone novo, porque
`.claude/skills` era um symlink não versionado. O commit `0ad24dd` versionou o symlink. Registrado
aqui para que a lacuna não seja reaberta por um relato antigo.

## Verificação do move (08/11/2026)

| Verificação | Antes | Depois |
|---|---|---|
| varredura de pendências (`../validation/pendencias-final/classificar.py`) | 356 — (i) 305, (ii) 46, (iii) 5, **(iv) 0** | 356 — (i) 305, (ii) 46, (iii) 5, **(iv) 0** |
| links Markdown relativos quebrados no repositório | 24 | 1, uma citação literal preservada (ver abaixo) |

As quatro regras do `classificar.py` que dependiam do caminho relativo de um documento passaram a
comparar o nome do arquivo, para que nenhum move futuro produza falsa pendência. Sem esse ajuste,
quatro linhas de `source-audit-66.md` teriam voltado a ser classificadas como pendência não
tratada.

O link restante está em `../validation/propagation-review/revisao-planos.md`, dentro de um trecho
citado literalmente do `plan.md` da spec-009. Corrigi-lo alteraria uma citação preservada, o que a
constituição §11 não permite; ele fica como está.
