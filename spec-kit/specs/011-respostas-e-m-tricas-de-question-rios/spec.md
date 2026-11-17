# spec-011 — Respostas e Métricas de Questionários

## Objetivo & Escopo

Calcular métricas determinísticas das respostas processadas.

## Dependências de Specs Anteriores

spec-007, spec-010

## Contratos & Interfaces

Persistência:
`local_assistente_question_result`
`local_assistente_questionnaire_metric`

Por questão:
- average_correctness;
- correct_count;
- incorrect_count;
- partial_result.

Agregado:
- mean;
- population_standard_deviation;
- minimum;
- maximum.

## Regras de Validação & Requisitos Técnicos

Partial result:
`correct_items_answered / total_correct_items`.
Exemplo: 2/3 = 66,66%.
*(Incluído em 03/11/2026, sem alterar DEC-36 nem DEC-52: semântica de `partial_result` como implementada e validada em CL-33/T075; itens (b), (c) e (d) formalizados pela DEC-64 em 03/11/2026; item (e) incluído depois pelo complemento de 03/11/2026 da DEC-64.)* O indicador é a média, entre as respostas válidas, de `correct_items_answered / total_correct_items`. A proporção de cada resposta (0 a 1) é interna; o valor persistido é o percentual de 0 a 100, truncado a três casas e exibido truncado a duas (DEC-36 D1 e D2; resolve a Q-011-02). Só se aplica a questões com mais de um item correto e é `NULL` nas demais. Considera apenas respostas com nota atribuída; resposta sem registro fica fora da média e nunca vira zero. Não é inferido da fração da questão nem da nota do Moodle. Itens por tipo de questão: (a) múltipla escolha com várias respostas corretas (`qtype_multichoice_multi_question`): alternativas corretas selecionadas sobre o total de corretas, e qualquer alternativa incorreta selecionada dá 0 (DEC-52); (b) `match`: hastes associadas corretamente sobre o total de hastes; (c) `ddwtos`: lacunas preenchidas corretamente sobre o total de lacunas; em (b) e (c) a contagem vem de `get_num_parts_right()` do Moodle, só existe com a resposta avaliada (estado com fração atribuída) e um item incorreto ou vazio apenas reduz o numerador, sem zerar a resposta; a regra de zerar de (a) não vale para (b) e (c); (d) `randomsamatch` (que herda de `match` no Moodle e é excluído pelo nome do tipo), e os tipos de resposta única ficam sem o indicador (`NULL`); (e) `multianswer` (Cloze): subquestões incorporadas em estado `gradedright` sobre o total de subquestões, pela contagem de `get_num_parts_right()`, com a subquestão parcialmente correta, incorreta ou sem resposta fora do numerador e dentro do denominador, só com a questão avaliada e fração atribuída, e um Cloze com uma só subquestão sem o indicador (complemento de 03/11/2026 da DEC-64) *(texto anterior do trecho de (d): "`multianswer` (Cloze, unidade de item não definida; pendente de decisão própria, DEC-64 D6) e os tipos de resposta única ficam sem o indicador (`NULL`)")*. A DEC-52 continua restrita a (a); a regra de (b) e (c) é a DEC-64 (D1 a D7), que aplica a DEC-36 D1 a esses tipos sem alterá-la, e a de (e) é o complemento de 03/11/2026 da DEC-64 *(alterado em 03/11/2026 com o registro da DEC-64; texto anterior: "tem por base a DEC-36 D1 (sem restrição de tipo) e a instrução do responsável de 02 e 03/11/2026, e não há decisão própria no registro")*.
Quando o denominador de uma métrica de questionário for zero, o resultado numérico é 0 com estado `no_data`. Um zero obtido com denominador positivo é resultado válido (`valid`). Falha técnica tem estado `error` e não é convertida em zero. O estado acompanha o valor no contrato do serviço. Fração × percentual e arredondamento continuam em aberto (plano 011, Q-011-02). *(Incluído em 22/10/2026 por DEC-01, DEC-24 e contrato P-06.)* *(04/11/2026, revisão da propagação: fração × percentual e arredondamento foram decididos pela DEC-36 D1 e D2 (percentual de 0 a 100, truncado a três casas) e a Q-011-02 foi resolvida, como a seção anterior já registra; exibição truncada a duas casas conforme a DEC-39. Texto anterior, preservado: "Fração × percentual e arredondamento continuam em aberto (plano 011, Q-011-02)".)*
No fechamento do questionário, agregados consideram todas as respostas.
Cálculos não usam LLM.

## Conformidade Moodle

A implementação deve consultar os dados de tentativas/respostas usando APIs Moodle disponíveis e usar cálculos determinísticos.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
