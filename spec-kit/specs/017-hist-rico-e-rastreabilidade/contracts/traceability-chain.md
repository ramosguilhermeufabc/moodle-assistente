# Contract — Rastreabilidade (spec-017)

| Operação | Entrada | Saída | Regras |
|---|---|---|---|
| `traceResult(result)` | qualquer linha de resultado | analysis → processing → attempts → context snapshot → event → fonte Moodle | cadeia completa [REQ §Contratos] |
| `history(entity)` | material / relatório / resultado | versões anteriores com timestamp e versão | estado anterior preservado [REQ §Regras] |

Invariantes: nenhuma atualização sobrescreve o estado anterior; a remoção de fonte não apaga o histórico (Q-017-03). *(26/10/2026: Q-017-03 resolvida por DEC-42; exceções em D1 e D2.)*

## Regras *(incluído em 26/10/2026, DEC-42 e tratamentos da revisão)*

**Execuções:** cada execução é uma `analysis` imutável; a atual é a última bem-sucedida por escopo (tipo + curso/estudante/seção/fórum conforme o plano produtor).

**Elos não relacionais:** `processing_context.context_json` registra os `material_version` usados e a disponibilidade; `report_version.snapshot_json` registra os `analysis_id` e as versões de síntese usados (conteúdo restante no plano 019).

**Fonte removida dentro do curso (D3):** histórico e referências preservados; `sourceRemoved(ref)` = o registro Moodle referenciado não existe mais (consulta na leitura, sem campo). O snapshot atual do fórum já é reconstruído sem conteúdo excluído (DEC-38); as análises históricas mantêm o texto.

| Operação de exceção | Gatilho | Efeito | Registro |
|---|---|---|---|
| `exportUserData(user, contexts)` (D1) | Privacy API: exportação | exporta `performance`, `frequency`, `frequency_activity`, `criteria_result` e as entradas do estudante nos snapshots | — |
| `deleteUserData(user, contexts)` (D1) | Privacy API: exclusão por usuário ou por contexto | remove essas linhas e as entradas identificáveis do estudante nos snapshots (inclusive acesso ao curso, DEC-39 D3); preserva agregados e textos da LLM somente se a verificação não encontrar dados que o identifiquem (Q-017-07, DEC-56: busca do nome e redação com `[removido]`); preserva `event` e `analysis` e neutraliza o `userid` e a `identity` dos eventos finais do estudante (DEC-57) | log de processamento |
| `deleteCourseData(course)` (D2) | exclusão do curso no Moodle (mecanismo em CL-37) | remove todos os dados do plugin do curso: eventos, processamentos, snapshots, análises, resultados, sínteses, relatórios e versões | log de processamento |

**Retenção (D4):** sem expurgo automático.
