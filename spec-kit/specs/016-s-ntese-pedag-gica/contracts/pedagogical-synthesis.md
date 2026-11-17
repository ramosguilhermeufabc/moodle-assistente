# Contract — Síntese pedagógica (spec-016)

| Operação | Entrada | Saída | Regras |
|---|---|---|---|
| `synthesize(course, processing)` | desempenho; frequência; fóruns/dificuldades; resumos de materiais; resultados relevantes | `pedagogical_synthesis.content` | só com nova informação relevante (Q-016-01); LLM via spec-009; associada ao processing [REQ] *(25/10/2026: escopo curso + seção; Q-016-01 resolvida por DEC-41 D2)* |

## Conjunto enviado à LLM = conjunto comparável *(incluído em 25/10/2026, DEC-41)*

| Bloco | Conteúdo | Fonte |
|---|---|---|
| Desempenho (D1) | por atividade da seção: média, mínimo, máximo, nº `valid`, nº `no_data` das notas atuais (percentual) | spec-012; DEC-37 |
| Frequência (D1) | média, mínimo, máximo, nº `valid`, nº `no_data` dos percentuais da seção | spec-014; DEC-39 |
| Questionário | cobertura e complexidade dos questionários da seção | spec-010 |
| Métricas do questionário | agregados e métricas por questão da análise atual | spec-011 |
| Fórum | resumo (texto) e dificuldades do snapshot atual de cada fórum da seção | spec-013; DEC-38 |
| Materiais | resumos dos materiais elegíveis na seção | spec-008; DEC-26 |
| Ausências | cada bloco sem resultado vai como `no_data` | DEC-41 D3 |

Sem identificadores, nomes ou notas individuais (DEC-34 D3). Sem índice único de desempenho da seção. Critérios da spec-015 ficam fora (âmbito de curso, DEC-40).

## Fingerprint

SHA-256 hexadecimal (64 caracteres) sobre JSON canônico do conjunto acima: chaves ordenadas; entidades ordenadas por ID; números como texto truncado a três casas; textos exatamente como armazenados. Fora: timestamps, IDs de processamento e de análise, metadados da LLM.

## Fluxo

1. Resolver a seção: `cmid` pela spec-004 ou `section_id` explícito, validado (existe, pertence ao curso). Falhou ⇒ sem síntese; pendência registrada.
2. Montar o conjunto. Sem nenhum resultado pedagógico (desempenho, frequência, questionário ou fórum) ⇒ sem síntese.
3. Calcular o fingerprint. Igual ao da versão vigente ⇒ fim, sem LLM e sem versão.
4. Chamar a porta (`pedagogical_synthesis`), sem transação aberta.
5. Resposta válida ⇒ sob Lock API por (curso, seção), gravar numa transação `analysis` (`section_synthesis`) e `pedagogical_synthesis` com `version = max + 1`, `input_fingerprint`, `content`. Falha ou resposta inválida ⇒ nenhuma versão; a vigente permanece.

**Gatilhos (D3):** após cada análise bem-sucedida a montante que afete uma seção (nova versão de material; análises 010, 011, 012, 013, 014), um evento por (curso, seção) afetada; evento pendente para o mesmo par é reutilizado (DEC-21). Sem espera por resultados ausentes.

**Conteúdo:** texto simples; HTML da LLM não é confiável e é exibido escapado.
