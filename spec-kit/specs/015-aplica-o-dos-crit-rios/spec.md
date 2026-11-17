# spec-015 — Aplicação dos Critérios

## Objetivo & Escopo

Identificar estudantes abaixo dos critérios definidos para o curso.

## Dependências de Specs Anteriores

spec-005, spec-011, spec-012, spec-013, spec-014

## Contratos & Interfaces

Entrada:
- resultados de atividade;
- resultado de fórum;
- frequência;
- critérios do curso.

Saída:
`local_assistente_criteria_result`.

## Regras de Validação & Requisitos Técnicos

Aplicar os critérios sem criar limiares implícitos.
Critério numérico de mínimo: atende se valor >= limite; fica abaixo se valor < limite. Critério numérico de máximo: atende se valor <= limite; ultrapassa se valor > limite. A comparação respeita a natureza do critério. A unidade de comparação do critério de atividade (Q-015-02) e o critério binário de fórum (Q-015-03, CL-05) continuam em aberto. *(Incluído em 22/10/2026 por DEC-11.)* *(04/11/2026, revisão da propagação: a unidade do critério de atividade e o critério binário de fórum foram definidos pela DEC-40: critérios em percentual de 0 a 100 (D1), critério de atividade sobre o total final do curso (D2), fórum binário pelas postagens (D3); CL-05 e CL-12 fechadas. Texto anterior, preservado: "A unidade de comparação do critério de atividade (Q-015-02) e o critério binário de fórum (Q-015-03, CL-05) continuam em aberto".)*
Atividade = critério numérico.
Fórum = binário.
Frequência = critério configurado.
O valor de frequência comparado ao critério é o percentual calculado pela spec-014, em que o acesso ao curso não entra no denominador. *(Incluído em 25/10/2026 por DEC-25.)*
Resultado disponível nas consultas/relatórios.
Estudantes com pontuação igual são exibidos em ordem alfabética crescente do nome. *(Incluído em 22/10/2026 por DEC-02.)*

## Conformidade Moodle

Regras determinísticas; não usar LLM para decidir se um valor está abaixo do critério.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
