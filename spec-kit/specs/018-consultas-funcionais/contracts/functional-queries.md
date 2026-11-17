# Contract — Consultas funcionais (spec-018)

Todas as consultas: `query(user, course, [turma: Q-018-01], filtros: Q-018-03) → view model`. A autorização é verificada no backend (spec-005). *(26/10/2026: turma = grupo do Moodle, DEC-43 D1; filtros no plano 020.)*

| Consulta | Campos permitidos | Spec de origem |
|---|---|---|
| notas | estudante, atividade, nota final | 012 *(26/10/2026: nota em percentual e total do curso, DEC-37)* |
| métricas de questionários | average_correctness, correct/incorrect_count, partial_result; mean, σ pop., mín, máx | 011 *(26/10/2026: também cobertura e complexidade da 010, com "sem materiais" distinto de 0.0, DEC-35 D2)* |
| frequência | total, participadas, percentual; participação por atividade | 014 *(26/10/2026: acesso ao curso como evidência complementar, DEC-39 D3)* |
| fóruns | resumo do fórum; dificuldades por discussão | 013 *(26/10/2026: snapshot atual, DEC-38)* |
| alunos abaixo dos critérios | status por critério | 015 *(26/10/2026: estudantes com `0` em qualquer dimensão; `1`/`0`/`2`, com `2` = "sem dados", DEC-40)* |
| resumos de materiais | resumo da versão corrente | 008 |
| síntese | content | 016 *(26/10/2026: versão vigente por curso e seção, exibida escapada, DEC-41)* |
| histórico | versões anteriores com timestamp/versão | 017 *(26/10/2026: execuções anteriores, versões de material, de síntese e de relatório, DEC-42)* |

Proibido: indicadores adicionais e perguntas livres em linguagem natural [REQ].

## Regras comuns *(incluído em 26/10/2026, DEC-43 e tratamentos da revisão)*

| Regra | Aplicação |
|---|---|
| Escopo de acesso | capability `local/assistente:view` no curso ou no sistema (DEC-30); "função" = papel do usuário, que não muda o conteúdo |
| Turma (D1) | filtro opcional por grupo do Moodle nas consultas individuais (notas, frequência, abaixo dos critérios); associação lida na consulta; agregadas sem filtro de grupo |
| Estudantes visíveis (D2) | em grupos separados e sem a capability de ver todos os grupos, só os estudantes dos grupos que o usuário pode ver; senão, todos os do curso; agregadas sem restrição por grupo |
| Execução exibida | a atual (última bem-sucedida por escopo); o histórico mostra as anteriores |
| Estados | `valid` / `no_data` / `error` (DEC-24) conforme cada plano produtor; `no_data` e `error` nunca exibidos como zero |
| Valores | percentuais truncados a duas casas na exibição (DEC-36 D2, DEC-37 D2, DEC-39) |
| Fonte removida | indicação quando o registro Moodle referenciado não existe mais (DEC-42 D3) |
| Ordenação | padrão por nome A–Z; ordenação pelo valor exibido com desempate por nome (DEC-02) |
| Paginação | nativa do Moodle |
| Dados pessoais | nada sai do sistema por estas consultas |
