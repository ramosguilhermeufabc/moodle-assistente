# Rastreabilidade — spec-009

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-043 | Isolar chamadas de LLM atrás de evaluate(context, instruction) -> structured_result | spec-009; DEC-34 | Confirmado; estrutura substituível aprovada por DEC-34 D1 (25/10/2026) |
| AT-044 | Adapter trata autenticação, request/response, timeout, erro e validação | spec-009; DEC-34; DEC-60 | Confirmado; credencial por variável de ambiente (DEC-34 D2); falhas como tentativas da spec-007 (25/10/2026); *(31/10/2026, DEC-60: request, response e mapeamento de falhas do adapter OpenRouter no contrato)* *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* |
| AT-045 | Não escolher fornecedor/modelo sem decisão no plano | spec-009; DEC-34; DEC-60 | Confirmado; decisão no plano: não escolher agora, gate do adapter real (DEC-34 D1, CL-19) (25/10/2026). *(31/10/2026, DEC-60: decisão registrada, OpenRouter e modelo administrativo; aguarda revisão, CL-19)* |
| AT-046 | Usar LLM para resumos de materiais, cobertura/complexidade, fóruns e síntese | spec-009 | Confirmado |
| AT-047 | Não usar LLM para contagens, médias, desvio-padrão, min/max, frequência ou critérios matemáticos | spec-009 | Confirmado |
| AT-048 | Manter credenciais fora do banco funcional e do Git *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | spec-001; spec-009; DEC-34 | Confirmado; fora de dev, só variável de ambiente do processo PHP (DEC-34 D2); validação em CL-32 (25/10/2026); *(31/10/2026, DEC-60: preservado, a URL e o modelo administrativos não são credencial)* |
| AT-049 | Validar saída estruturada do LLM antes de persistir | spec-010; spec-009; DEC-60 | Confirmado; envelope e payload por uso no contrato da porta (25/10/2026); *(31/10/2026, DEC-60: conversão da resposta externa para o mesmo envelope, critérios inalterados)* |

*(25/10/2026: colunas "Fonte" e "Estado" atualizadas depois da DEC-34; textos originais: fonte "spec-009" (AT-043 a 045), "spec-001; spec-009" (AT-048) e estado "Confirmado".)*

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-33 | Materiais: tipos, alteração e entrega 008 ↔ 009 (D1, no plano) |
| DEC-34 | Integração com LLM externa (incluída em 25/10/2026) |
| DEC-60 | Integração com LLM externa: OpenRouter, URL e modelo administrativos, ZDR, formato externo (incluída em 31/10/2026; complementa DEC-34 D1; DEC-34 D2 e D3 preservadas) *(04/11/2026, revisão dos planos: DEC-69 altera a DEC-34 D2 e a DEC-60 D3; DEC-70 altera a DEC-60 D2(a) e D5; DEC-71 altera a DEC-60 D4.)* |

*(Texto original: "Nenhuma decisão tem esta spec listada na seção 3 do registro.")*

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q17). Validação técnica em `closure-register.md` CL-32. Nada foi executado.
