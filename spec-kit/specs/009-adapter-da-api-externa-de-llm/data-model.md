# Data Model — spec-009

Nenhuma tabela própria. Objetos de valor da porta:

| Objeto | Conteúdo | Estado |
|---|---|---|
| `context` | dados de entrada do uso (ex.: snapshot de spec-008). Sem identificadores de estudantes (DEC-34 D3) | por uso, NEEDS CLARIFICATION (montado pelas specs consumidoras) (04/11/2026: resolvido: o contexto é montado pelas specs consumidoras e transportado por assistente/classes/domain/llm/llm_context.php, com a regra de DEC-34 D3 verificada por assistente/classes/application/llm/context_guard.php; T055 [x]) *(25/10/2026: incluída a restrição de DEC-34 D3)* |
| `instruction` | instrução do uso | Q-009-05 (nas tasks consumidoras) |
| `structured_result` | resultado validado: envelope `use_case`, `status`, `data`, `error_code` | payload mínimo por uso em [contracts/llm-port.md](contracts/llm-port.md); Q-009-04 parcial *(estado original: "esquema por uso NEEDS CLARIFICATION (Q-009-04)")* (04/11/2026: resolvido: esquema por uso implementado em assistente/classes/infrastructure/llm/result_validator.php (T053 [x]); o formato da síntese, que mantinha a Q-009-04 parcial, foi fechado por Q-016-02 e Q-016-03, Resolvidas no plano 016.) |

*(31/10/2026, DEC-60)* Ainda sem tabela própria. Itens técnicos do adaptador real, que não são objetos de valor da porta:

| Item | Onde fica | Regra |
|---|---|---|
| URL do endpoint | configuração administrativa do plugin (site) | endpoint completo, sem padrão, validada (HTTPS, destino externo); lida a cada chamada |
| Identificador do modelo | configuração administrativa do plugin (site) | sem padrão e sem modelo fixo |
| Credencial | variável de ambiente `ASSISTENTE_LLM_API_KEY` do processo PHP *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | nunca persistida nem exibida (DEC-34 D2) |
| Requisição e resposta externas | só na memória do adaptador | não persistidas; resposta inválida descartada; só o `structured_result` válido segue ao consumidor |
| Metadados do fornecedor (modelo, tokens, latência, custo) | não persistidos | sem tabela nem campo previsto |

A URL e o modelo não são credencial. Nenhuma chave de configuração do plugin pode ter nome de credencial (verificado por T056).

Restrições de saída conhecidas pelos consumidores:
- Cobertura de questionário e complexidade de questão: 0.0–1.0, passo 0.1 [spec-010 §Regras].
- Dificuldades de fórum: texto por discussão [spec-013; spec-002 `forum_difficulty.description`].
- Resumo do fórum: texto [spec-013 §Saída]. *(Incluído em 25/10/2026.)*
- Síntese: texto [spec-016; spec-002 `pedagogical_synthesis.content`].
- Resumo de material: texto [spec-002 `material_version.summary`].
