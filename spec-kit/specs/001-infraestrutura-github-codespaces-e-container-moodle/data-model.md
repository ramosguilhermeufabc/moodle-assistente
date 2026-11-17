# Data Model — spec-001

N/A. A spec-001 não define entidades de dados. Os dados persistentes do plugin estão em spec-002.

Itens de configuração (não são entidades de domínio):

| Item | Natureza | Fonte | Estado |
|---|---|---|---|
| Variáveis de ambiente não secretas | configuração | spec-001 §Contratos | nomes e valores NEEDS CLARIFICATION (Q-001-09) (04/11/2026: resolvido: variáveis do baseline aprovadas por DEC-28 e DEC-49) *(26/10/2026: variáveis do baseline aprovadas por DEC-28)* |
| Secrets das credenciais da API LLM *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | Codespaces development secret | spec-001 §Contratos, §Regras 5–6 | nomes NEEDS CLARIFICATION (Q-001-09, Q-009-01) (04/11/2026: resolvido: Q-001-09 por DEC-49 (a chave da API passou à administração do Moodle por DEC-69) e Q-009-01 por CL-19 (Fechado em 03/11/2026, DEC-60)) *(26/10/2026: `ASSISTENTE_LLM_API_KEY`, DEC-49; parâmetros adicionais em CL-19)* |
| *(26/10/2026)* Senha do administrador do Moodle de desenvolvimento | Codespaces development secret | DEC-49 D2 | nome definido nas tasks; fora do Git |
| *(26/10/2026)* Prefixos e diretórios de dados do PHPUnit e do Behat | `config.php` | DEC-49 D1 | valores definidos nas tasks; volume `phpunitdata` reutilizado quando apropriado |
