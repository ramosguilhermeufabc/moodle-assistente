# Quickstart (validação) — spec-009

> Nada foi executado. Todos os cenários usam o adapter mock ou respostas HTTP simuladas, exceto Q10 e Q17, que são validação técnica (CL-32). *(31/10/2026: Q11 a Q17 incluídos por DEC-60; nenhum cenário chama o OpenRouter nos testes automatizados, DEC-48 D1.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Mock devolve uma resposta válida | `structured_result` validado e entregue | §Contratos |
| Q2 | Mock devolve uma resposta inválida | Falha; nada persistido pelo consumidor | §Contratos |
| Q3 | Mock simula timeout | Falha registrada como tentativa (spec-007) | §Contratos; spec-023 |
| Q4 | Executar métricas, frequência e critérios | Nenhuma chamada à porta LLM | §Regras |
| Q5 | Procurar credenciais no Git e no banco | Nenhuma | §Regras |
| Q6 | Mock devolve `coverage` ou `complexity` = 1.1, -0.1 ou 0.35 | `error`/`invalid_response`; nada persistido | spec-010 §Regras; contrato |
| Q7 | Mock devolve `use_case` diferente do solicitado, ou sem campo obrigatório | `error`/`invalid_response` | contrato |
| Q8 | Mock simula falha de transporte, autenticação e limite de taxa | Cada uma é tentativa falha com a classe registrada; após a 3ª, o evento vai a `failure` | DEC-32 D3–D4 |
| Q9 | Inspecionar a requisição montada para fórum e síntese | Sem autor, ID de usuário, nome ou e-mail de estudante | DEC-34 D3 |
| Q10 | Moodle 5.2 com a variável de ambiente definida e sem configuração no banco, em execução web e por cron/CLI *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | O adapter lê a credencial; nenhuma chave em `config_plugins`; localização externa listada na Privacy API | DEC-34 D2, D3; CL-32 |

| Q11 | Adaptador HTTP com URL ou modelo ausente (resposta HTTP simulada, nenhuma real) | Nenhuma requisição; falha controlada `transport`; tentativa falha | DEC-60 D2 |
| Q12 | URL administrativa com `http://`, `localhost`, `127.0.0.1`, rede privada, `169.254.169.254`, credenciais na URL, ou redirecionamento | Recusada ao salvar e antes da chamada; credencial não enviada; redirecionamento não seguido | DEC-60 D2 |
| Q13 | Inspecionar a requisição montada (cliente HTTP simulado) *(03/11/2026, DEC-70: Chat Completions — `messages` e `choices[0].message.content` no lugar de `instructions`/`input` e `output[]`.)* *(03/11/2026, DEC-71: com `llmzdr` desligada, o corpo não tem `provider`; ligada ou nunca salva, tem `provider.zdr = true` e `provider.data_collection = "deny"`.)* | `model` e URL vindos da configuração vigente; `provider.zdr = true`, `provider.data_collection = "deny"`; chave só no cabeçalho `Authorization`; sem identificador de estudante; sem chave em log ou erro; variável ausente = `auth` sem requisição | DEC-60 D2–D4; DEC-34 D2, D3 |
| Q14 | Resposta Responses API simulada, válida, para cada um dos 5 usos *(03/11/2026, DEC-70: Chat Completions — `messages` e `choices[0].message.content` no lugar de `instructions`/`input` e `output[]`.)* | `structured_result` com `status = valid` e `data` do uso; envelope inalterado | DEC-60 D5; contrato |
| Q15 | Resposta simulada com JSON inválido, `status` diferente de `completed`, sem mensagem, só raciocínio, texto vazio, recusa, `error` não nulo, ou texto que não é objeto do uso *(03/11/2026, DEC-70: Chat Completions — `messages` e `choices[0].message.content` no lugar de `instructions`/`input` e `output[]`.)* *(03/11/2026, emenda da DEC-70: o objeto dentro de uma única cerca de Markdown é aceito; texto fora da cerca continua inválido.)* | `error`/`invalid_response`; nada persistido | DEC-60 D5 |
| Q16 | HTTP simulado 401, 402, 403, 429, 408, 400, 404, 422, 5xx e sem endpoint ZDR; sem nova tentativa sem os parâmetros ZDR *(03/11/2026, DEC-71: a regra de não relaxar o ZDR vale com `llmzdr` ligada.)* | Classe do contrato; tentativa falha; após a 3ª, `failure`; política ZDR nunca relaxada | DEC-60 D4; DEC-32 D3–D4 |
| Q17 | Validação técnica (CL-32), adapter real: ZDR efetivo e ausência de fallback fora da política, código HTTP real sem endpoint compatível, bloqueio de destinos inválidos pelo cliente do Moodle, formato real da resposta | A registrar em CL-32, sem resultado antecipado *(04/11/2026, revisão dos planos: CL-32 Fechado em 03/11/2026.)* | DEC-60; CL-32 |

*(Q6–Q10 incluídos em 25/10/2026, depois de DEC-34 e da definição do envelope.)* *(Q11–Q17 incluídos em 31/10/2026, DEC-60.)*
