# Contract — Porta LLM (spec-009)

```text
evaluate(context, instruction) -> structured_result
```

| Aspecto | Contrato | Estado |
|---|---|---|
| Usos permitidos | resumo de materiais; cobertura de questionário; complexidade de questão; resumo/dificuldades de fórum; síntese pedagógica [REQ §Regras] | fechado |
| Usos proibidos | contagens, médias, desvio-padrão, mín/máx, frequência, aplicação de critérios [REQ §Regras] | fechado |
| Autenticação | credenciais fora do banco funcional e do Git [REQ]. Fora de dev, exclusivamente variável de ambiente do processo PHP; nunca configuração do plugin no banco; em dev, secret do ambiente [spec-001] (DEC-34 D2) *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | decidido; validação em CL-32 *(estado original: "Q-009-02")* |
| Timeout/erro | retorna falha classificada para spec-007. Toda falha é uma tentativa falha (limite de 3, DEC-32); classe registrada na tentativa: `timeout`, `transport`, `auth`, `rate_limit`, `invalid_response`. Valor do timeout fixado nas tasks, menor que o intervalo do ciclo (DEC-32 D5) | parcial (Q-009-03) *(estado original: "Q-009-03")* |
| Validação | resposta inválida ⇒ falha, nada persistido. Esquema na seção abaixo | parcial (Q-009-04) *(estado original: "esquema Q-009-04")* |
| Dados enviados | nenhum identificador de estudante (autor, ID de usuário, nome, e-mail) no `context` nem na `instruction`; síntese só com agregados e textos já produzidos; localização externa declarada na Privacy API (DEC-34 D3) | decidido; validação em CL-32; termos do fornecedor em CL-19 *(linha incluída em 25/10/2026)* |
| Implementações | adapter externo (fornecedor OpenRouter, DEC-60; Q-009-01 parcial); mock para testes [spec-023]. Estrutura substituível: o adapter real é gate de CL-19 e a última entrega; porta e mock vêm antes da spec-008 (DEC-33 D1, DEC-34 D1) | decidido *(estado original: "—")* |

## `structured_result` *(incluído em 25/10/2026; Q-009-04 parcial)*

Envelope comum:

| Campo | Regra |
|---|---|
| `use_case` | um dos 5 usos abaixo; igual ao uso solicitado |
| `status` | `valid` ou `error` |
| `data` | payload do uso; obrigatório quando `status = valid` |
| `error_code` | classe de falha (lista acima); obrigatório quando `status = error` |

Payload mínimo por uso, derivado das saídas das specs consumidoras:

| `use_case` | `data` | Validação | Fonte |
|---|---|---|---|
| `material_summary` | `summary`: texto | não vazio | spec-008; spec-002 `material_version.summary` |
| `quiz_coverage` | `coverage`: decimal | 0.0 ≤ valor ≤ 1.0, múltiplo de 0.1 | spec-010 §Saída, §Regras (cobertura é do questionário) |
| `question_complexity` | `complexity`: decimal | 0.0 ≤ valor ≤ 1.0, múltiplo de 0.1 | spec-010 §Saída, §Regras (complexidade é por questão) |
| `forum_analysis` | `forum_summary`: texto; `difficulties`: lista de {`discussion_id`, `description`} | `forum_summary` não vazio; cada `discussion_id` pertence ao `context`; `description` não vazia | spec-013 §Saída; spec-002 `forum_difficulty.description` |
| `pedagogical_synthesis` | `content`: texto | não vazio; formato detalhado em Q-016-02/03 | spec-016; spec-002 `pedagogical_synthesis.content` |

Resposta que não decodifica, sem campo obrigatório, com `use_case` diferente do solicitado ou fora de faixa/passo é `status = error` com `error_code = invalid_response`. A porta não arredonda nem corrige valores.

## Adapter externo: OpenRouter *(incluído em 31/10/2026, DEC-60; documental, nada validado)*

O adaptador real é uma implementação da mesma porta. Ele converte a resposta externa para o `structured_result` acima e não cria um segundo envelope. Os campos externos foram conferidos na documentação do fornecedor em 31/10/2026 (ver [research.md](../research.md)); a compatibilidade real é validada em CL-32.

### Configuração administrativa

| Campo | Regra |
|---|---|
| URL da API | Endpoint **completo**, usado exatamente como configurado; o código não acrescenta caminho. Referência inicial para o OpenRouter: `https://openrouter.ai/api/v1/responses`, digitada pelo administrador (não embutida). Sem valor padrão | *(03/11/2026, DEC-70: o adaptador usa a Chat Completions API: URL de referência `https://openrouter.ai/api/v1/chat/completions`; requisição com `messages` (system e user) no lugar de `instructions`/`input`; resposta lida em `choices[0].message.content` (uma escolha, `finish_reason = "stop"`, sem `refusal`) no lugar de `output[]`. Mantêm-se o envelope, as cinco classes, o mapeamento HTTP, o ZDR e o timeout. O texto acima foi preservado.)*
| Modelo | Identificador de texto, sem valor padrão e sem modelo fixo. `openai/o4-mini` é só exemplo |
| Acesso | Restrito a administradores autorizados (capacidade de configuração do site); os dois campos são independentes e alteráveis sem mudar código nem reinstalar |
| Leitura | Os valores vigentes são lidos a cada chamada |
| Ausência | URL ou modelo ausente: nenhuma requisição; falha controlada (`transport`) |
| Validação da URL | Só HTTPS; sem credenciais na URL; destino externo. Bloqueados: loopback, redes privadas, link-local, endereços de metadados de nuvem e outros destinos de SSRF. Validada ao salvar e antes de cada chamada, sobre o endereço efetivamente resolvido; redirecionamentos não são seguidos. URL recusada: a credencial não é enviada. O mecanismo do Moodle para isso é verificado em T134 e CL-32 |
| Credencial | Nunca nestes campos nem em `config_plugins`; só `ASSISTENTE_LLM_API_KEY` (DEC-34 D2). Variável ausente ou vazia: falha controlada (`auth`), sem requisição *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* |

Risco residual registrado: um administrador autorizado pode configurar qualquer destino externo seguro que passe na validação, e a chave seria enviada a ele. Restringir o destino ao domínio do fornecedor não foi decidido (ver o relatório de revisão).

### Requisição externa

| Item | Regra |
|---|---|
| Método e destino | `POST` na URL configurada, `Authorization: Bearer` com a chave da variável de ambiente (a chave só viaja no cabeçalho) *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* |
| Corpo | `model` = modelo configurado; `instructions` = `instruction` da porta; `input` = `context` da porta serializado, **sem identificadores de estudantes** (DEC-34 D3, guarda de T055); sem `previous_response_id`, sem `stream`, sem ferramentas. O formato do `input` e limites como `max_output_tokens` ficam em T134 *(03/11/2026, DEC-71: o objeto `provider` só é enviado com a configuração `llmzdr` ligada, que é o padrão.)* | *(03/11/2026, DEC-70: o adaptador usa a Chat Completions API: URL de referência `https://openrouter.ai/api/v1/chat/completions`; requisição com `messages` (system e user) no lugar de `instructions`/`input`; resposta lida em `choices[0].message.content` (uma escolha, `finish_reason = "stop"`, sem `refusal`) no lugar de `output[]`. Mantêm-se o envelope, as cinco classes, o mapeamento HTTP, o ZDR e o timeout. O texto acima foi preservado.)*
| Política ZDR | `provider.zdr = true` e `provider.data_collection = "deny"` em toda requisição. Nunca omitidos, nunca relaxados, sem repetição sem eles. Fallback só entre endpoints que atendam à política | *(03/11/2026, DEC-71: a exigência de ZDR é a configuração de site `llmzdr` (caixa de seleção), ligada por padrão. Ligada, vale esta regra; desligada, o objeto `provider` não é enviado. O texto acima foi preservado.)*
| Sem endpoint compatível *(04/11/2026, revisão dos planos: DEC-71: a falha por ausência de endpoint compatível vale com a configuração `llmzdr` ligada (padrão); desligada, o objeto `provider` não é enviado.)* | O fornecedor devolve erro (a documentação não informa o código para ZDR); falha controlada, sem flexibilizar a política |
| Condições de conta | Registro privado de entrada e saída desativado; uso de entradas e saídas pelo fornecedor desativado; ZDR na conta ou guardrail como reforço. Não verificáveis pelo código |
| Log | Pode registrar uso, classe de falha, status HTTP, modelo, tokens e latência. Nunca prompt, resposta completa, chave ou dado pessoal |

### Resposta externa e conversão

> *(03/11/2026, DEC-70: o adaptador usa a Chat Completions API: URL de referência `https://openrouter.ai/api/v1/chat/completions`; requisição com `messages` (system e user) no lugar de `instructions`/`input`; resposta lida em `choices[0].message.content` (uma escolha, `finish_reason = "stop"`, sem `refusal`) no lugar de `output[]`. Mantêm-se o envelope, as cinco classes, o mapeamento HTTP, o ZDR e o timeout. Os passos 2 a 6 da tabela abaixo (e a referência da resposta) são substituídos por: JSON sem `error`; uma única escolha com `finish_reason = "stop"`; `message.content` texto não vazio; os passos 7 a 9 continuam. O texto abaixo foi preservado.)* *(03/11/2026, emenda da DEC-70: no passo 7, uma única cerca de Markdown (```` ```json … ``` ````) em volta do objeto é removida antes de decodificar; qualquer outro texto em volta continua `invalid_response`.)*

Referência da resposta (o modelo e os valores do exemplo são ilustrativos): `id`, `object = response`, `created_at`, `model`, `output`, `usage` (`input_tokens`, `output_tokens`, `total_tokens`), `status`.

| Passo | Regra | Se falhar |
|---|---|---|
| 1 | Resposta HTTP 2xx; corpo decodifica como JSON | `invalid_response` |
| 2 | `status = completed`. Outro valor (incompleto, falho, em andamento, ausente) não é aceito. A lista completa de valores não foi conferida | `invalid_response` |
| 3 | `error` ausente ou nulo | `invalid_response` |
| 4 | Em `output`, uma mensagem do assistente (`type = message`, `role = assistant`, `status = completed`); itens de raciocínio são ignorados; nenhuma mensagem, ou mais de uma, é estrutura inesperada | `invalid_response` |
| 5 | No conteúdo da mensagem, partes `output_text`; recusa ou parte sem `output_text` não vale | `invalid_response` |
| 6 | O campo `text` das partes `output_text`, em ordem, concatenado, não vazio. `annotations` e o campo de conveniência `output_text` do nível superior não são usados | `invalid_response` |
| 7 | O texto decodifica como objeto JSON cujos campos são exatamente o `data` do uso solicitado (tabela acima); `use_case` vem da requisição, nunca da resposta | `invalid_response` |
| 8 | O `result_validator` de T053 valida o resultado; seus critérios (faixa, passo 0.1 de `coverage` e `complexity`, campos obrigatórios) não mudam | `invalid_response` |
| 9 | `status = valid` e `data` entregues; o JSON externo é descartado; `usage` não é persistido | — |

Resposta inválida nunca é persistida. A conversão é técnica: não cria regra funcional. Os passos 4 a 7 são o desenho adotado para o formato; os formatos reais devem ser confirmados em T134 e CL-32.

### Mapeamento de falhas

Cinco classes, sem classe nova e sem estado novo na fila. Toda falha é tentativa falha, com o limite de 3 (DEC-32). A tabela é documental; os códigos HTTP vêm da referência da Responses API e não foram exercitados. *(04/11/2026, revisão dos planos: DEC-70: a API adotada é a Chat Completions API, que usa os mesmos códigos HTTP; a referência à Responses API é anterior.)*

| Situação | Classe |
|---|---|
| URL ou modelo ausente; URL recusada pela validação (nenhuma requisição enviada) | `transport` |
| Variável `ASSISTENTE_LLM_API_KEY` ausente ou vazia (nenhuma requisição enviada); HTTP 401, 402, 403 (credencial inválida, créditos insuficientes, bloqueio por guardrail) *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | `auth` |
| HTTP 429 | `rate_limit` |
| Estouro do timeout do cliente; HTTP 408, 524 | `timeout` |
| Falha de rede, TLS ou DNS; redirecionamento (não seguido); HTTP 5xx (500, 502, 503, 529) | `transport` |
| HTTP 400, 404, 413, 422: modelo inexistente ou indisponível; endpoint sem suporte ao formato solicitado; **sem endpoint compatível com ZDR ou `data_collection`**; requisição rejeitada *(04/11/2026, revisão dos planos: DEC-71: vale com a configuração `llmzdr` ligada (padrão); desligada, o objeto `provider` não é enviado.)* | `transport` |
| HTTP 2xx com JSON inválido, resposta incompleta, sem mensagem, texto vazio, estrutura inesperada ou rejeitada pelo validador | `invalid_response` |

A causa fina (por exemplo, "sem endpoint ZDR") não vira classe nem estado: aparece só no log, como status HTTP, sem conteúdo. Ver as pendências sobre o enquadramento de configuração ausente e de ausência de endpoint ZDR no relatório de revisão.
