# Contract — Métricas de questionário (spec-011)

| Operação | Entrada | Saída | Regras |
|---|---|---|---|
| `computeQuestionResults(questionnaire, responses)` | respostas processadas | `question_result` por questão/versão | determinístico; partial = corretos respondidos / total corretos [REQ] |
| `computeAggregate(questionnaire, responses)` | todas as respostas (no fechamento) | `questionnaire_metric` | mean, desvio populacional, mín, máx [REQ] |

Não chama a porta LLM [REQ §Regras]. Gatilhos: Q-011-03. *(25/10/2026: Q-011-03 resolvida por DEC-36 D4; ver abaixo.)*

## Regras *(incluído em 25/10/2026, DEC-36)*

**Tentativas consideradas (D3):** só tentativas concluídas; em andamento e abandonadas ficam fora; pré-visualizações do professor sempre fora. As mesmas tentativas valem para as duas operações.

**Por questão (D1)**, só respostas com nota atribuída (correção manual pendente fica fora até ser avaliada):

| Campo | Definição |
|---|---|
| `average_correctness` | média da fração obtida (nota/nota máxima da questão), em percentual |
| `correct_count` | respostas com fração integral |
| `incorrect_count` | respostas com fração zero (parciais não entram em nenhuma das contagens) |
| `partial_result` | média, entre as respostas que têm o indicador, de `correct_items_answered / total_correct_items`, em percentual; só para questões com múltiplos itens corretos; `NULL` nas demais e quando nenhuma resposta tem o indicador. *(26/10/2026, DEC-52 / Q-011-07: itens = alternativas corretas selecionadas sobre o total de corretas; qualquer incorreta selecionada dá 0; resposta sem registro fica fora da média, não é zero; independente da nota do Moodle.)* |

**Itens do `partial_result` por tipo de questão** *(incluído em 03/11/2026, sem alterar DEC-36 nem DEC-52; regra de `match` e `ddwtos` pela DEC-64; implementação em `questionnaire_reader` (`choices`, `parts`) e `results_handler`)*. O reader entrega o dado; a média e o percentual são do `metrics_calculator` (proporção interna de 0 a 1, persistida como percentual de 0 a 100, truncado a três casas, DEC-36 D2). Uma questão só tem o indicador com mais de um item correto.

| Tipo | Itens | `partial_result` da resposta | Fora da média |
|---|---|---|---|
| Múltipla escolha com várias respostas corretas (`qtype_multichoice_multi_question`) | Alternativas com fração positiva | Corretas selecionadas / total de corretas; qualquer incorreta selecionada = 0 (DEC-52) | Sem `choiceN` registrado |
| `match` | Hastes com escolha correta | Hastes certas / total de hastes, de `get_num_parts_right()`; item errado ou vazio só reduz o numerador (DEC-64 D1, D3, D4) | Resposta sem nota (`gaveup`, nunca respondida, enviada em branco) (DEC-64 D5) |
| `ddwtos` | Lacunas | Lacunas certas / total de lacunas, de `get_num_parts_right()`; idem (DEC-64 D2, D3, D4) | Idem (DEC-64 D5) |
| `randomsamatch` | — | `NULL`: herda de `qtype_match_question`, mas é excluído pelo nome exato do tipo (DEC-64 D6) | — |
| `multianswer` (Cloze) | Subquestões incorporadas | Subquestões em estado `gradedright` / total de subquestões, de `get_num_parts_right()`; parcial, incorreta ou sem resposta fica fora do numerador e no denominador; Cloze com uma só subquestão = `NULL` (complemento de 03/11/2026 da DEC-64) | Resposta sem nota (`gaveup`, nunca respondida, sem fração) |
| `truefalse`, `shortanswer`, `numerical`, `essay` e demais de resposta única | — | `NULL` | — |

Regras comuns de `match` e `ddwtos` (DEC-64 D3, D4, D5 e D7): o dado só é produzido com a questão em estado avaliado e fração não nula; `get_last_qt_data()` não é sinal de resposta (devolve o passo inicial quando nada foi respondido); a contagem de partes é usada, e não a fração da questão (que carrega penalidade de tentativas e nota manual); a regra de zerar da DEC-52 não vale para esses tipos. Tipos não listados não foram avaliados e ficam sem o indicador. *(Alterado em 03/11/2026 pelo complemento da DEC-64: a linha de `multianswer` da tabela dizia "Unidade de item não definida; pendente de decisão própria (DEC-64 D6)" com `NULL`. As regras comuns acima valem também para `multianswer`, que não tem regra de zerar: a subquestão parcial ou incorreta só fica fora do numerador. Para `multianswer`, a elegibilidade é: questão avaliada e fração registrada (não `NULL`), inclusive quando o valor da fração é zero.)*

**Agregado (D3):** um resultado final por estudante, segundo o método de avaliação do questionário (maior nota, média, primeira ou última tentativa), em percentual da nota máxima; `mean`, `population_standard_deviation` (populacional), `minimum`, `maximum` sobre esses resultados.

**Escala (D2):** percentuais 0–100 em `number(10,3)`, truncados a três casas; exibição truncada a duas casas. Ex.: 2/3 → `66.666` → "66,66%".

**Questões sem linha analisada (D5):** nova versão de questão analisada → `questionnaire_question_id` da linha da mesma entrada do banco de questões; `question_id`/`question_version` = versão respondida. Questão adicionada ou substituída depois da publicação, sem linha → sem `question_result`; entra no agregado e é listada no contexto do processamento. Nenhuma nova análise inicial (DEC-31).

**Estados (DEC-24, P-06), sem campo novo:**

| Estado | Como se obtém |
|---|---|
| `valid` | linha existe numa `analysis` bem-sucedida (inclusive valor real 0) |
| `no_data` | processamento bem-sucedido sem a linha: questão sem resposta com nota (sem `question_result`) ou nenhum estudante com resultado (sem `questionnaire_metric`) |
| `error` | processamento em `failure`; nunca convertido em zero |

**Gatilhos (D4):** submissão de tentativa, correção manual e reavaliação entram na fila e recalculam. A Scheduled Task (spec-007) detecta `timeclose` passado e gera uma recalculação final com todas as respostas; sem `timeclose`, agregados parciais.

**Histórico:** cada execução grava uma `analysis` imutável `quiz_results` (resultados e agregado na mesma transação); a atual é a última bem-sucedida.
