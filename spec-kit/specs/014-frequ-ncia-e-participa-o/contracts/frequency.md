# Contract — Frequência (spec-014)

| Operação | Entrada | Saída | Regras |
|---|---|---|---|
| `computeFrequency(course)` | curso; fontes: acesso, atividades avaliadas/conclusão/entrega, fóruns | `frequency` por estudante/seção + `frequency_activity` | presença ≥ 1; % = part/total × 100; referência = data final/devida; sem LLM [REQ] |

Gatilho: Q-014-06. *(25/10/2026: resolvida por DEC-39 D2; ver abaixo.)*

## Regras *(incluído em 25/10/2026, DEC-39; DEC-25 sem alteração)*

**Elegibilidade (D1):** módulo de **atividade** do Moodle com pelo menos uma das categorias: avaliação numérica ou por escala; conclusão habilitada; submissão; fórum. Recursos (página, arquivo, URL, livro, pasta, rótulo) ficam fora, mesmo com conclusão. A atividade só conta para o estudante se estiver disponível para ele (visível e com restrições atendidas, DEC-26).

**Participação (D1):** basta um de: submissão enviada; tentativa concluída ou nota atribuída; conclusão completa, aprovada ou reprovada; ao menos uma postagem do estudante no fórum. Uma atividade conta uma vez por estudante e seção (DEC-25).

**Data de referência (D2):** data de entrega ou encerramento da atividade; senão `course.enddate`; sem nenhuma, a atividade fica fora e é listada no contexto do processamento.

**Denominador por estudante (D2):** a atividade elegível entra quando `reference_date` passou ou, antes disso, se o estudante já participou. Linhas de `frequency_activity` só para as atividades que entram; `total_activities` = número dessas linhas; `participating_activities` ≤ `total_activities` (nunca acima de 100%).

**Acesso ao curso (D3):** fora do percentual; o snapshot de contexto registra, por estudante, `acessou` (sim/não) e a data do último acesso até o cálculo.

**Escala:** `percentage` truncado a três casas em `number(10,3)`; exibição truncada a duas (1/3 → `33.333` → "33,33%").

**Estados (DEC-24), sem campo novo:**

| Estado | Como se obtém |
|---|---|
| `valid` | `total_activities > 0` (inclusive participação zero → 0.000) |
| `no_data` | `total_activities = 0` → `percentage = 0` |
| `error` | processamento em `failure`; nunca convertido em zero |

**Gatilhos (D2):** eventos de participação (submissão, conclusão, postagem, nota) entram na fila e recalculam o estudante no curso; a Scheduled Task detecta `reference_date` vencidas e recalcula o curso.

**Histórico e escopo:** cada execução é uma `analysis` imutável `frequency`; a atual é a última bem-sucedida. Seção de cada atividade pelo contrato da spec-004 (não resolvida ⇒ erro técnico). Estudantes: papéis avaliados do gradebook (como DEC-37). Sem LLM.
