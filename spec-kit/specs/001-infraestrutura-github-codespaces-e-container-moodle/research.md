# Research — spec-001 (Fase 0)

> PROMPT-3: sem pesquisa externa e sem conhecimento geral. Cada item é resolvido apenas pela spec ou fica **ABERTO** para decisão humana.

## Decididos pela spec
- **Decision**: GitHub Codespaces + Docker/containers + `devcontainer.json` como configuração primária. **Rationale**: [REQ spec-001 §Objetivo, §Contratos]. **Alternatives considered**: nenhuma; fixado pela spec.
- **Decision**: Moodle `MOODLE_502_STABLE`; PHP ≥ 8.3 (8.4 suportado). **Rationale**: [REQ spec-001 §Objetivo, §Conformidade]. **Alternatives considered**: n/a.
- **Decision**: segredos da API LLM em Codespaces development secrets, nunca no Git. **Rationale**: [REQ spec-001 §Regras 5–6]. **Alternatives considered**: n/a. *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)*

## Decididos pelo responsável (25/10/2026)
- **Decision**: baseline do ambiente = configuração observada em `.devcontainer/` (plano 001, "Configuração observada do ambiente"). **Rationale**: decisão humana DEC-28, que resolve C-001-01. **Alternatives considered**: aprovar só a pilha base (Q-001-01…03), opção rejeitada pelo responsável.
- **Decision**: montagem de `assistente/` em `/var/www/html/public/local/assistente`. **Rationale**: decisão humana DEC-27. **Alternatives considered**: sincronização (descartada).

## Decididos pelo responsável (26/10/2026, DEC-49 — altera parcialmente DEC-28)
- **D1**: Composer sem `--no-dev`; Selenium com Chrome; prefixos e diretórios do PHPUnit e do Behat. **Alternatives considered**: perfil de teste separado no Compose.
- **D2**: instalação idempotente por CLI; senha do administrador por secret; fuso `America/Sao_Paulo`; commit e tags fixados na primeira validação. **Alternatives considered**: instalação manual pela web, sem fixação de versões.
- **D3**: serviço `cron` a cada minuto. **Alternatives considered**: cron manual pela CLI.
- **Tratamentos**: `forwardPorts` 8080 e 8025; `wwwroot` pelo Codespaces com `sslproxy`; secret `ASSISTENTE_LLM_API_KEY`. *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)*

## Abertos
*(Coluna "Decision" alterada em 25/10/2026 por DEC-27 e DEC-28; valor original em todas as linhas: "ABERTO", com "— sem base nas specs" em Q-001-01.)*

| Item | Decision | Rationale | Alternatives considered |
|---|---|---|---|
| Q-001-01 Dockerfile × Compose | RESOLVIDO (DEC-28): Docker Compose com Dockerfile próprio | A spec admite ambos e remete à "decisão final de ambiente", que não consta nas specs | Dockerfile; Docker Compose (as duas citadas pela spec) |
| Q-001-02 SGBD | RESOLVIDO (DEC-28): MariaDB 11.4; compatibilidade não verificada (R-G4) | A spec exige a matriz oficial do Moodle 5.2, que não foi consultada (fonte externa) | — |
| Q-001-03 Imagem/PHP/servidor web | RESOLVIDO (DEC-28): `moodlehq/moodle-php-apache:8.3`, PHP 8.3, Apache | Spec §Regras 7 | PHP 8.3 ou 8.4 (ambas citadas) |
| Q-001-04 Portas | PARCIAL (DEC-28): mapeamentos do Compose; `forwardPorts` aberto *(26/10/2026: RESOLVIDO — DEC-49)* | Spec §Regras 7 | — |
| Q-001-05 Scripts de criação | PARCIAL (DEC-28): `fixmoodleowner.sh`; instalação do Moodle e fixação de versões abertas *(26/10/2026: RESOLVIDO — DEC-49 D1/D2)* | Spec §Regras 4, 7 | — |
| Q-001-06 Serviços auxiliares / execução periódica | PARCIAL (DEC-28): `mailhog`; cron aberto *(26/10/2026: RESOLVIDO — DEC-49 D1/D3)* | Spec §Regras 7; spec-007/019 exigem execução periódica | — |
| Q-001-07 Credenciais admin Moodle (dev) | ABERTO *(26/10/2026: RESOLVIDO — DEC-49 D2, secret do Codespaces)* | Spec §Regras 7 | — |
| Q-001-08 Montagem × sincronização | PARCIAL (DEC-27): montagem; origem futura e validação pendentes *(04/11/2026, revisão dos planos: Q-001-08 Resolvida (ver o plano 001); CL-24 Fechado em 31/10/2026.)* | A spec diz "montagem/sincronização" | montagem; sincronização |
| Q-001-09 Nomes de variáveis/secrets | PARCIAL (DEC-28): variáveis observadas; secrets da LLM abertos *(26/10/2026: RESOLVIDO — `ASSISTENTE_LLM_API_KEY`, DEC-49)* *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | Depende de Q-009-01 *(26/10/2026: o nome é neutro quanto ao fornecedor; só parâmetros adicionais dependem de CL-19)* | — |
