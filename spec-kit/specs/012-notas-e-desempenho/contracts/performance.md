# Contract — Desempenho (spec-012)

| Operação | Entrada | Saída | Regras |
|---|---|---|---|
| `collectPerformance(course)` | curso | linhas `performance` (estudante × atividade × nota final) | fonte: gradebook; sem pesos próprios [REQ] *(25/10/2026: por evento, escopo curso × estudante, DEC-37 D1)* |
| `studentAverage(course, student)` | curso, estudante | média | derivada conforme configuração Moodle [REQ]; local de cálculo: Q-012-03 *(25/10/2026: total final do curso do gradebook, no snapshot de contexto, DEC-37 D3)* |

Gatilho: Q-012-01. *(25/10/2026: resolvida por DEC-37 D1; ver abaixo.)*

## Regras *(incluído em 25/10/2026, DEC-37 e tratamentos da revisão)*

**Gatilho (D1):** eventos do Moodle de atribuição, alteração (inclusive substituição) e remoção de nota entram na fila. Cada evento gera uma `analysis` `grade_performance` para o estudante no curso, com todas as suas atividades avaliadas. Mudanças de peso ou agregação chegam pelos eventos que o Moodle emite ao reavaliar (CL-34).

**Atividades:** itens do gradebook vinculados a módulo de atividade (`cmid`), com nota numérica ou escala. *(27/10/2026, DEC-53: quando o módulo tem mais de um item de nota, vale o item principal, `itemnumber = 0`, sem combinar notas e sem escolher o item que tiver nota; item 0 sem nota = `no_data`.)* Fora: categorias, itens manuais sem `cmid`, total do curso como atividade, itens sem tipo de nota, notas só textuais. Estudantes: papéis avaliados do gradebook.

**Unidade (D2):** `grade = (nota final − min) / (max − min) × 100`, truncado a três casas em `number(10,3)`; exibição truncada a duas. Escalas usam o mesmo cálculo com o mínimo e o máximo da escala. Sem ponderação própria.

**Média (D3):** total final do curso fornecido pelo gradebook (pesos, agregação e notas vazias conforme o professor), convertido por D2 e registrado no snapshot de contexto da análise. O plugin não calcula média.

**Estados (DEC-24, P-06), sem campo novo:**

| Estado | Como se obtém |
|---|---|
| `valid` | linha existe numa `analysis` bem-sucedida (inclusive nota 0) |
| `no_data` | atividade sem nota atribuída, ou item com intervalo nulo: sem linha; total do curso ausente: registrado como ausente no snapshot |
| `error` | processamento em `failure`; nunca convertido em zero |

**Histórico:** cada execução é imutável; a atual, por estudante, é a última bem-sucedida. Sem LLM.
