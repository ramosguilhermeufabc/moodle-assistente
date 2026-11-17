# Contract — Observers e hooks (spec-022)

```text
Moodle event (verificado no 5.2) -> observer (fino) -> application service -> enqueue (spec-006)
```

| Regra | Fonte |
|---|---|
| Observer não executa LLM nem regra de negócio | §Contratos, §Regras |
| Nenhum evento/hook inventado; cada um com evidência no Moodle 5.2 | §Regras |
| Hooks só quando apropriados e necessários | §Contratos *(26/10/2026: navegação do curso; exclusão de curso, se confirmado)* |
| Eventos próprios persistidos na fila | §Regras |
| *(26/10/2026)* Curso sem configuração ou com `config.enabled = 0`: o observer não cria evento | DEC-45 D3 |
| *(26/10/2026)* O serviço aplica a deduplicação por tipo concreto de evento antes de enfileirar | DEC-21; DEC-08 |
| *(26/10/2026)* Sem observer de matrícula nem de atribuição de papéis; listas por estudante filtradas na leitura | DEC-47 |

## Fluxo do observer *(incluído em 26/10/2026)*

1. Recebe o evento Moodle.
2. Verifica se o curso tem configuração e `enabled = 1`; senão, termina sem efeito.
3. Extrai só os identificadores necessários (curso, módulo, usuário, objeto) e o tipo lógico do catálogo do plano.
4. Chama o serviço de enfileiramento, que aplica a identidade por tipo (DEC-21) e reutiliza o evento existente quando é a mesma ocorrência (DEC-08).
5. Não abre transação longa, não chama LLM, não calcula resultados.
