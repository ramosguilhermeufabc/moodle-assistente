# Data Model — spec-020

Sem entidades próprias. Os formulários mapeiam para:

| Formulário | Destino | Campos conhecidos | Estado |
|---|---|---|---|
| Configuração | `config` (spec-005) | — *(26/10/2026: `enabled` 0/1)* | Q-020-01 *(26/10/2026: resolvida, DEC-45 D3)* |
| Critérios | `criteria` (spec-005) | atividade (numérico), fórum (binário), frequência (numérico) | faixas Q-020-04 *(26/10/2026: resolvida — 0–100 com até três casas; fórum 0/1; DEC-40)* |
| Filtros | parâmetros de consulta/relatório (018/019) | — *(26/10/2026: grupo, seção, versão/data)* | Q-020-02 *(26/10/2026: resolvida)* |

*(Incluído em 26/10/2026, DEC-45:)*
- `criteria` continua sem versionamento; a correção administrativa sobrescreve os limites, e cada `analysis` `criteria_check` preserva no seu contexto os limites usados (interpretação histórica).
- `config.enabled = 0` por padrão; nenhum campo novo para a sincronização.
