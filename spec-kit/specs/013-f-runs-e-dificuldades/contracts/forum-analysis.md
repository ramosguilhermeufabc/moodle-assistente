# Contract — Análise de fórum (spec-013)

**Gatilho**: evento de postagem ou interação no fórum (spec-006, spec-013; DEC-03). *(Alterado em 25/10/2026; texto original: "evento de atualização relevante de fórum (spec-006; Q-013-02).")* *(25/10/2026, DEC-38: mensagem criada ou editada → modo incremental; mensagem ou discussão excluída → reconstrução completa.)*

**Entrada** [REQ §Contratos]: fórum; resumo corrente; nova mensagem; materiais elegíveis da mesma seção. *(25/10/2026, DEC-38 D1: também as dificuldades atuais da discussão afetada e o `discussion_id`; a mensagem é marcada como edição quando for o caso. Sem autor, ID de usuário, nome ou e-mail (DEC-34 D3); pode ir o papel genérico de quem postou (estudante ou professor), que não identifica a pessoa. Nomes citados no próprio texto não são removidos.)*

**Saída** [REQ §Contratos]: resumo do fórum (persistência: Q-013-01); `forum_difficulty` por discussão. *(25/10/2026: Q-013-01 resolvida — `analysis.summary`.)*

**Regras**: incremental; histórico preservado; LLM via porta de spec-009; LLM nunca chamada no observer.

## Modos *(incluído em 25/10/2026, DEC-38)*

| Modo | Quando | `context` | Resultado gravado |
|---|---|---|---|
| Incremental | mensagem criada ou editada | resumo atual + mensagem + dificuldades atuais da discussão afetada + materiais | nova `analysis`: `summary` = `forum_summary` devolvido; dificuldades da discussão afetada = as devolvidas (0..N); demais discussões copiadas sem alteração |
| Reconstrução | mensagem ou discussão excluída | todas as mensagens e discussões que ainda existem + materiais; sem resumo nem dificuldades anteriores | nova `analysis` com resumo e dificuldades de todas as discussões existentes |
| Primeira mensagem | sem análise anterior | resumo vazio, sem dificuldades | como no modo incremental |

**Validação** (contrato da porta, plano 009): no modo incremental, todo `discussion_id` devolvido deve ser o da discussão afetada; na reconstrução, deve pertencer ao `context`. Resposta inválida ⇒ `invalid_response`, a tentativa falha.

**Persistência:** chamada à LLM sem transação aberta; `analysis` (`analysis_type = forum_analysis`, `cmid` do fórum, `summary`) e todas as linhas de `forum_difficulty` do snapshot numa única transação. Falha ou resposta inválida ⇒ nada publicado; o snapshot anterior continua o atual.

**Seção:** derivada do `cmid` do fórum pelo contrato da spec-004; não resolvida ⇒ erro técnico.
