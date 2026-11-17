# Implementation Plan: spec-001 — Infraestrutura & Ambiente GitHub Codespaces

**Branch**: `feature/sdd-ajustado` (feature dir `001-infraestrutura-github-codespaces-e-container-moodle`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-infraestrutura-github-codespaces-e-container-moodle/spec.md`

**Aprovação**: aprovado pelo responsável em 26/10/2026, conforme DEC-49 (que altera parcialmente o baseline de DEC-28, com o histórico preservado). As mudanças em `.devcontainer/` continuam planejadas, sem nenhum arquivo de ambiente alterado nesta etapa. Instalação, versões, cron, `wwwroot`, secrets e execução de PHPUnit/Behat seguem sujeitos às validações técnicas CL-01 e CL-22, sem resultado presumido *(31/10/2026: CL-01 Fechado com ressalvas, conforme `closure-register.md`; a validação própria do CL-22 não foi alterada por esse encerramento)*; Q-001-08 continua parcial (CL-24). A aprovação isolada não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3, que define os 23 `specs/*/spec.md` como única fonte de contexto do projeto. Nenhum teste de compatibilidade foi executado nesta fase.

**Legenda**: `[REQ spec-NNN §Seção]` = requisito explícito da spec · `[ESCOLHA]` = escolha de planejamento, que não é requisito · `NEEDS CLARIFICATION` / `Q-NNN-xx` = lacuna aberta, não resolvida por inferência.

## Summary

Disponibilizar um ambiente de desenvolvimento reprodutível em GitHub Codespaces que execute um Moodle 5.2 funcional (`MOODLE_502_STABLE`), com o plugin `local_assistente` visível em `/var/www/html/public/local/assistente` e o repositório Git preservado [REQ spec-001 §Objetivo, §Regras 1–3]. *(Alterado em 25/10/2026 por DEC-27; caminho original: `/var/www/html/local/assistente`.)* A spec atribui ao `plan.md` a escolha de imagem, portas, banco, credenciais, comandos e serviços auxiliares [REQ spec-001 §Regras 7]. Pelo PROMPT-3, essas escolhas não podem ser feitas por inferência e ficam para decisão humana (C-001-01). A configuração hoje observada em `.devcontainer/` está registrada na seção "Configuração observada do ambiente" e foi aprovada como baseline pelo responsável em 25/10/2026 (DEC-28). As lacunas que a DEC-28 deixou abertas continuam em Q-001-04…07 e 09. *(Alterado em 25/10/2026; texto original: "ficam como bloqueadores para decisão humana (C-001-01)." Texto intermediário do mesmo dia: "está registrada, sem aprovação formal, na seção".)*

## Technical Context

**Language/Version**: PHP ≥ 8.3, com suporte a 8.4 [REQ spec-001 §Conformidade]. Baseline: PHP 8.3 (`moodlehq/moodle-php-apache:8.3`, Apache) [DEC-28]. *(Alterado em 25/10/2026; texto original: "A versão exata está NEEDS CLARIFICATION (Q-001-03)." (04/11/2026: resolvido: DEC-28: PHP 8.3; PHP 8.3.36 observado no ambiente (CL-01, `closure-register.md`)))*

**Primary Dependencies**: Moodle `MOODLE_502_STABLE`, Docker/containers e dev container via `.devcontainer/devcontainer.json` [REQ spec-001 §Objetivo, §Contratos]. Baseline: Docker Compose, com Dockerfile próprio para o serviço `moodle` [DEC-28]. *(Alterado em 25/10/2026; texto original: "Dockerfile **ou** Docker Compose está NEEDS CLARIFICATION (Q-001-01)." (04/11/2026: resolvido: DEC-28: Docker Compose com Dockerfile próprio; arquivos `.devcontainer/docker-compose.yml` e `.devcontainer/moodle/Dockerfile` existem))*

**Storage**: banco "de acordo com a matriz oficial do Moodle 5.2" [REQ spec-001 §Conformidade]. Baseline: MariaDB 11.4 [DEC-28]; a compatibilidade com a matriz do Moodle 5.2 ainda não foi verificada (R-G4). *(Alterado em 25/10/2026; texto original: "SGBD e versão estão NEEDS CLARIFICATION (Q-001-02)." (04/11/2026: resolvido: DEC-28: MariaDB 11.4; MariaDB 11.4.13 observado em CL-01))*

**Testing**: os test runners são definidos em spec-023 e estão NEEDS CLARIFICATION (Q-023-01) (04/11/2026: resolvido: DEC-48: PHPUnit e Behat do Moodle; executados em T013 [x] (CL-22, `closure-register.md`)). A execução dos testes deve ocorrer neste ambiente [REQ spec-023 §Conformidade]. *(26/10/2026: Q-023-01 resolvida por DEC-48 — PHPUnit e Behat do Moodle; este ambiente os disponibiliza por DEC-49 D1 (dependências de desenvolvimento, Selenium com Chrome, prefixos e diretórios de dados no `config.php`); execução real em CL-22.)*

**Target Platform**: GitHub Codespaces [REQ spec-001 §Ambiente].

**Project Type**: ambiente de desenvolvimento (dev container) para um plugin local Moodle.

**Performance Goals**: N/A, não especificado.

**Constraints**: segredos da API externa fora do Git [REQ §Regras 5]; valores sensíveis via Codespaces development secrets [REQ §Regras 6]; `postCreateCommand` faz apenas preparação reprodutível [REQ §Regras 4]; nenhum valor de infraestrutura inventado [REQ §Objetivo, §Regras 7].

**Scale/Scope**: um ambiente de desenvolvimento. Escala não especificada.

## Constitution Check

*GATE: avaliado antes da Fase 0 e reavaliado após a Fase 1.*

| Princípio (constitution.md) | Pré-design | Pós-design | Observação |
|---|---|---|---|
| §1 Fidelidade às Specs | PASSA | PASSA | Nenhum valor de infraestrutura foi inventado; as lacunas estão em Q-001-xx. |
| §2 Rastreabilidade integral | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2: os IDs dos 66 requisitos atômicos não constam nas specs. A rastreabilidade aqui é feita por spec/seção.".)* |
| §3 Fluxo SDD | PASSA | PASSA | Somente a fase Plan foi executada. |
| §4 Separação requisitos/decisões | PASSA | PASSA | As escolhas de planejamento estão rotuladas como `[ESCOLHA]`. |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA | Matriz PHP/BD do Moodle 5.2 não verificada nesta fase (R-G4). *(26/10/2026: validação técnica que não bloqueia o planejamento — compatibilidade de MariaDB 11.4 e PHP 8.3, instalação, cron, `wwwroot`, portas e secret em CL-01; PHPUnit e Behat em CL-22.)* *(31/10/2026: a compatibilidade foi verificada no Q8 do CL-01, Fechado com ressalvas; o resultado do gate não foi reavaliado nesta correção documental.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: BLOQUEADO, a matriz PHP/BD foi verificada no Q8 do CL-01 (Fechado em 31/10/2026, com ressalvas), mas o CL-22 (runners PHPUnit e Behat), citado na mesma observação, continua Aberto (VT) no `closure-register.md`; T007 e T008 continuam [ ] em `001-*/tasks.md`)* *(Reavaliado em 04/11/2026, T140, texto anterior preservado acima: §5 Compatibilidade Moodle: **PASSA**; CL-22 Fechado (VT) em 04/11/2026 (PHPUnit e Behat executados no ambiente reproduzido; T007 e T008 [x]; T139: suítes completas aprovadas, `validation/t139/run.md`); a matriz PHP/BD foi verificada no Q8 do CL-01. O estado "BLOQUEADO" da coluna Pós vale só como histórico.)* |
| §6 Escopo de materiais | N/A | N/A | — |
| §7 Integridade de dados | N/A | N/A | — |
| §8 Idempotência/concorrência | N/A | N/A | — |
| §9 Testabilidade | PASSA | PASSA | Cenários de validação em [quickstart.md](quickstart.md), derivados de §Regras 1–6. |
| §10 Transparência | PASSA | PASSA | Nada foi executado nem testado. |
| §11 Governança | BLOQUEADO | PASSA | C-001-01 foi decidida por DEC-28 (baseline). Q-001-04…07 e 09 decididas por DEC-49, que altera explicitamente parte do baseline e preserva o histórico de DEC-28. *(Alterado em 26/10/2026; Pós anterior: "BLOQUEADO"; observação anterior: "C-001-01 foi decidida por DEC-28 (baseline). Seguem pendentes decisões humanas em Q-001-04…07 e 09."; texto original: "C-001-01 exige decisão humana sobre valores de ambiente.")* |
| §12 Plataforma e arquitetura | — | PASSA | O ambiente segue a spec-001 e as DEC-27 e DEC-28. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | Os secrets da LLM ficam fora do Git, em Codespaces secrets (spec-001 §Regras 5–6); os nomes estão em Q-001-09. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: os itens BLOQUEADO são justificados (aguardam decisão humana) e não há violação silenciosa. Cada Q-001 ainda aberta deve ser tratada individualmente na fase Tasks, como dependência das tarefas de ambiente e de validação a que se aplica. Elas não bloqueiam em bloco o planejamento (decisão do responsável de 25/10/2026, `closure-register.md` CL-01). *(Alterado em 25/10/2026; texto original: "A fase Tasks deve tratar Q-001-01…09 como dependências bloqueantes.")* *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(26/10/2026, DEC-49: princípios BLOQUEADO: §5 (validação técnica, CL-01 e CL-22; não bloqueia o planejamento). Questões Bloqueador: nenhuma; resta Q-001-08 parcial, dependente de CL-24.)* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle continua BLOQUEADO, porque o CL-22 (runners PHPUnit e Behat) continua Aberto no `closure-register.md`; a parte da matriz PHP/BD foi verificada no CL-01 (Fechado com ressalvas); demais princípios sem alteração; texto anterior preservado acima.)* *(Reavaliado em 04/11/2026, T140: §5 Compatibilidade Moodle passa a **PASSA**, porque o CL-22 está Fechado (VT) em 04/11/2026 e as suítes completas da T139 passaram; nenhum princípio BLOQUEADO resta. Os textos acima ficam como histórico.)*

## Project Structure

### Documentation (this feature)

```text
specs/001-infraestrutura-github-codespaces-e-container-moodle/
├── spec.md
├── plan.md          # este arquivo
├── research.md      # Fase 0 (lacunas registradas)
├── data-model.md    # Fase 1 (N/A: sem entidades)
└── quickstart.md    # Fase 1 (cenários de validação)
```

`contracts/` não foi gerado: a spec não define interface exposta a usuários ou sistemas, apenas artefatos de ambiente.

### Source Code (repository root)

```text
<raiz do repositório Git>          # preservado (spec-001 §Regras 3)
├── .devcontainer/
│   ├── devcontainer.json          # ponto principal de configuração (spec-001 §Contratos)
│   ├── docker-compose.yml         # baseline DEC-28 (original: [Dockerfile | docker-compose.yml] NEEDS CLARIFICATION, Q-001-01) (04/11/2026: resolvido: DEC-28: o arquivo `.devcontainer/docker-compose.yml` existe)
│   ├── moodle/                    # Dockerfile, config.php, php.ini (DEC-28)
│   └── scripts/fixmoodleowner.sh  # postCreateCommand atual (DEC-28); Q-001-05 parcial
│                                  # (26/10/2026, DEC-49: + script idempotente de instalação do Moodle por CLI;
│                                  #  Compose ganha os serviços selenium e cron; caminhos e nomes nas tasks)
└── assistente/                    # raiz de trabalho do plugin (spec-001 §Objetivo; DEC-27)
                                   # → montado em /var/www/html/public/local/assistente
                                   #   (Compose: ../assistente:/var/www/html/public/local/assistente)
```

**Structure Decision**: somente os caminhos citados na spec. Por DEC-27 (25/10/2026), `assistente/` fica na raiz do repositório, no mesmo nível de `.devcontainer/`, e é montado em `/var/www/html/public/local/assistente`. Em 25/10/2026 o diretório `assistente/` ainda não existia no repositório (Q-001-08). Ele é a origem futura prevista; o código será disponibilizado depois da geração das tasks (CL-24). *(Alterado em 25/10/2026 por DEC-27; texto original: "O diretório `assistente/` ainda não existe no repositório, e sua relação com a raiz atual (`moodle-assistente/`, que contém `spec-kit/`) está NEEDS CLARIFICATION (Q-001-08)." (04/11/2026: resolvido: DEC-27 e CL-24 (Fechado em 31/10/2026): o diretório `assistente/` existe na raiz do repositório) Destino original no diagrama: `/var/www/html/local/assistente`.)*

## Dependências entre specs

- Upstream: nenhuma [REQ spec-001 §Dependências].
- Downstream: spec-002…023 dependem deste ambiente. spec-023 exige execução dos testes neste ambiente. spec-009 depende dos secrets da API LLM definidos aqui.

## Fases de planejamento & gates de validação

1. **Decisões de ambiente**: resolver com o responsável as Q-001 ainda abertas [ESCOLHA: ordem]. Elas não bloqueiam o planejamento das demais specs; as tarefas de ambiente e de validação dependem delas individualmente. *(Alterado em 25/10/2026; texto original: "**Decisões de ambiente** (bloqueante): resolver Q-001-01…09 com o responsável [ESCOLHA: ordem].")*
2. **Definição do dev container**: `devcontainer.json`, Dockerfile/Compose, portas, variáveis não secretas e secrets [REQ §Contratos].
3. **Montagem do plugin** de `assistente/` em `/var/www/html/public/local/assistente` [REQ §Contratos, §Regras 2; DEC-27]. *(Alterado em 25/10/2026 por DEC-27; texto original: "**Montagem/sincronização do plugin** em `/var/www/html/local/assistente`".)*
4. **Gate G-001**: os cenários Q1–Q6 de [quickstart.md](quickstart.md) passam num Codespace novo [ESCOLHA: gate]. *(26/10/2026: cenários Q1–Q12; Q7–Q12 são validação técnica em CL-01/CL-22, sem resultado antecipado.)* *(31/10/2026, revisão de escopo aprovada pelo responsável: Q1, Q4, Q7 e a parte externa de Q10 estão DISPENSADOS e não contam como aprovados; o gate no "Codespace novo" fica sem comprovação, como risco aceito. Ver `quickstart.md` e T007.)*
5. *(Incluída em 26/10/2026, DEC-49.)* **Alterações do ambiente, a executar como tasks** (nenhuma feita nesta fase): `composer install` sem `--no-dev`; serviço Selenium com Chrome; prefixos e diretórios do PHPUnit e do Behat no `config.php`; script idempotente de instalação por CLI com senha do administrador vinda de secret; fuso do site `America/Sao_Paulo`; commit do Moodle e tags das imagens fixados na primeira validação; serviço `cron` a cada minuto; `forwardPorts` 8080 e 8025; `wwwroot` pelas variáveis do Codespaces com `sslproxy`; `ASSISTENTE_LLM_API_KEY` passado ao serviço `moodle`. *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)*

## Configuração observada do ambiente (25/10/2026)

Esta seção lista o que os arquivos versionados em `.devcontainer/` configuram hoje. Em 25/10/2026 o responsável aprovou essa configuração como baseline (DEC-28, que decide C-001-01). As lacunas listadas na coluna "Decisão formal" continuam abertas. Nada abaixo foi validado em execução. *(Alterado em 25/10/2026; texto original: "Configuração observada **não** é decisão formalmente aprovada: até a decisão do responsável sobre C-001-01, as Q-001 correspondentes continuam abertas.")* A working copy local e `origin/feature/sdd-ajustado` diferem apenas na linha de montagem do plugin (local: `/var/www/html/local/assistente`; origin `907234b`: `/var/www/html/public/local/assistente`, conforme DEC-27). *(04/11/2026, revisão dos planos: parte das lacunas foi decidida pela DEC-49 (26/10/2026); a fixação das versões continua pendente (T008).)*

| Questão | Configuração observada | Fonte | Decisão formal |
|---|---|---|---|
| Q-001-01 | Docker Compose (`docker-compose.yml`), com o serviço `moodle` construído por Dockerfile próprio | `.devcontainer/devcontainer.json`, `.devcontainer/docker-compose.yml`, `.devcontainer/moodle/Dockerfile` | Aprovada (DEC-28) |
| Q-001-02 | MariaDB `11.4` (imagem `mariadb:11.4`), charset `utf8mb4`. Compatibilidade com a matriz do Moodle 5.2 não verificada (R-G4) | `docker-compose.yml` | Aprovada (DEC-28); compatibilidade a verificar (R-G4) |
| Q-001-03 | Imagem `moodlehq/moodle-php-apache:8.3` (PHP 8.3, Apache); código do Moodle clonado de `MOODLE_502_STABLE` sem commit fixo; `$CFG->dirroot = '/var/www/html/public'` | `moodle/Dockerfile`, `moodle/config.php` | Aprovada (DEC-28); fixação do commit em Q-001-05 |
| Q-001-04 | Mapeamentos do Compose: `8080:80` (Moodle), `8025:8025` (mailhog), `3308:3306` (banco). `devcontainer.json` não define `forwardPorts` | `docker-compose.yml`, `devcontainer.json` | Mapeamentos aprovados (DEC-28); `forwardPorts` em aberto *(04/11/2026, revisão dos planos: `forwardPorts` (8080 e 8025) decidido pela DEC-49.)* |
| Q-001-05 | `postCreateCommand`: `sh .devcontainer/scripts/fixmoodleowner.sh` (só `chown` dos diretórios de dados). Não há passo de instalação do Moodle | `devcontainer.json`, `scripts/fixmoodleowner.sh` | Comando atual aprovado (DEC-28); instalação do Moodle e fixação de versões em aberto *(04/11/2026, revisão dos planos: a instalação do Moodle foi decidida pela DEC-49 D2; a fixação das versões continua pendente (T008).)* |
| Q-001-06 | Serviço auxiliar `mailhog`. Nenhum mecanismo de execução periódica (cron) configurado | `docker-compose.yml` | `mailhog` aprovado (DEC-28); cron em aberto (lacuna para spec-007/019) *(04/11/2026, revisão dos planos: o cron foi decidido pela DEC-49 D3 (serviço `cron` no Compose).)* |
| Q-001-07 | Credenciais do **banco** de dev em texto no Compose. Nenhuma credencial administrativa do Moodle configurada | `docker-compose.yml`, `moodle/Dockerfile` | Credenciais do banco de dev aprovadas via Q-001-09 (DEC-28); credenciais de admin do Moodle em aberto *(04/11/2026, revisão dos planos: a senha do administrador por secret foi decidida pela DEC-49 D2.)* |
| Q-001-08 | Montagem `../assistente` (ver DEC-27) | `docker-compose.yml` (origin `907234b`) | Decidida por DEC-27 |
| Q-001-09 | Variáveis de ambiente `MOODLE_DBTYPE`, `MOODLE_DBHOST`, `MOODLE_DBNAME`, `MOODLE_PREFIX`, `MOODLE_DBPORT`, `MOODLE_DBUSER`, `MOODLE_DBPASSWORD`, `MOODLE_WWWROOT_URL` e `MOODLE_SMTPHOSTS`, todas com valor em texto no Git (`MOODLE_DBPASSWORD` é credencial de banco de dev, ver Q-001-07). `MOODLE_WWWROOT_URL` não é lida pelo `config.php`. Nenhum secret da LLM previsto *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | `docker-compose.yml`, `moodle/Dockerfile` | Variáveis aprovadas (DEC-28); secrets da LLM em aberto (Q-009-01) |

*(Coluna "Decisão formal" alterada em 25/10/2026 por DEC-28; valor original em todas as linhas, exceto Q-001-08: "Não aprovada".)*

*(Incluído em 26/10/2026.)* **Alterações aprovadas sobre o baseline (DEC-49).** A tabela acima descreve o que os arquivos configuram hoje e continua válida como registro do baseline de DEC-28. DEC-49 aprova as mudanças abaixo, que ainda não foram aplicadas a `.devcontainer/`:

| Item | Baseline observado (DEC-28) | Aprovado por DEC-49 | Validação |
|---|---|---|---|
| Dependências do Composer | `composer install --no-dev` | sem `--no-dev`, para PHPUnit e Behat | CL-22 |
| Navegador para Behat | ausente | serviço Selenium com Chrome no Compose | CL-22 |
| Configuração de testes | ausente | prefixos e diretórios de dados do PHPUnit e do Behat no `config.php`; volume `phpunitdata` reutilizado quando apropriado | CL-22 |
| Instalação do Moodle (Q-001-05) | nenhuma | script idempotente por CLI na criação, só se não instalado | CL-01 |
| Credenciais de admin (Q-001-07) | nenhuma | senha do administrador de secret do Codespaces, fora do Git | CL-01 |
| Fuso do site | não configurado (o container tem `TZ=America/Sao_Paulo`) | `America/Sao_Paulo` na instalação (DEC-44) | CL-01, CL-39 |
| Versões (Q-001-05) | branch sem commit; `composer:latest` | commit do Moodle e tags de Composer, MailHog e Selenium fixados na primeira validação *(04/11/2026, T008, ordem T008 → T007: fixados a partir do que está em execução neste Codespace, a validar na T007 em Codespace novo. Moodle commit `28fdef85e7043c8dd758af8c1a146da1419db0d0` (5.2.4+, Build 20261030; `git -c safe.directory=/var/www/html -C /var/www/html log -1 --format=%H` e `public/version.php`); Composer `composer:2.11.1` (`composer --version`; tag na API do Docker Hub); MariaDB `mariadb:11.4.13` (`$DB->get_server_info()` = 11.4.13); MailHog `mailhog/mailhog:v1.0.1` (API do Docker Hub: `latest` e `v1.0.1` têm o mesmo digest `sha256:8d76a3d4ffa32a3661311944007a415332c4bb855657f4f6c57996405c009bea`); Selenium `selenium/standalone-chrome:4.51.0` (`curl http://selenium:4444/status` = 4.51.0); PHP/Apache `moodlehq/moodle-php-apache:8.3-bookworm@sha256:1db7b6ed013fcb5268095b5e28c410ba9117d38d9b6a324c100c48fcbe69745e` (digest do índice OCI da tag 8.3-bookworm, cabeçalho `Docker-Content-Digest` de `registry-1.docker.io`, tag atualizada em 22/10/2026); yt-dlp 2026.10.26 e smalot/pdfparser 2.13.1 sem mudança. A construção não foi feita; a T008 segue [ ] até a T007.)* | CL-01 |
| Cron (Q-001-06) | ausente | serviço `cron` executando `admin/cli/cron.php` a cada minuto | CL-01 |
| `forwardPorts` (Q-001-04) | ausente no `devcontainer.json` | 8080 (Moodle) e 8025 (MailHog); banco não encaminhado | CL-01 |
| `wwwroot` | `MOODLE_WWWROOT_URL` não lida pelo `config.php` | variáveis do Codespaces com `sslproxy`; `http://localhost:8080` fora dele | CL-01 |
| Secret da LLM (Q-001-09) | nenhum | `ASSISTENTE_LLM_API_KEY` como secret do Codespaces, passado ao serviço `moodle` (DEC-34 D2); outros parâmetros em CL-19 *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | CL-01, CL-32 |

## Questões abertas / Riscos / Contradições

*(Alterado em 25/10/2026: Q-001-01 a Q-001-07 passaram de "Bloqueador" para "Aberto". Continuam não decididas, mas deixam de bloquear em bloco o planejamento, por decisão do responsável (`closure-register.md` CL-01). A configuração observada de cada uma está na seção anterior.)* *(08/11/2026: CL-01 foi fechado em 31/10/2026, por decisão do responsável; a pendência afirmada acima não está mais aberta. Texto anterior preservado, conforme a constituição §11.)*

*(25/10/2026, DEC-28)* C-001-01, Q-001-01, Q-001-02 e Q-001-03 passaram a "Resolvido"; Q-001-04, Q-001-05, Q-001-06 e Q-001-09 passaram a "Parcial". Q-001-07 continua "Aberto". Estado anterior de cada uma: "Contradição" (C-001-01) ou "Aberto".

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| C-001-01 | Resolvido | A spec manda decidir os valores exatos "no `plan.md`", mas o PROMPT-3 proíbe resolver lacunas por inferência. Os valores ficam para decisão humana. **Decidido por DEC-28 (25/10/2026):** o responsável aprovou a configuração observada como baseline. *(Alterado em 25/10/2026; textos originais: "Os valores ficam como bloqueadores para decisão humana." e, no mesmo dia, "**Decisão humana pendente (25/10/2026):** aprovar formalmente, alterar ou rejeitar a configuração observada.")* | spec-001 §Regras 7; DEC-28 |
| Q-001-01 | Resolvido | Dockerfile ou Docker Compose ("conforme a decisão final de ambiente", que não consta nas specs). **DEC-28:** Docker Compose, com Dockerfile próprio para o serviço `moodle`. | §Contratos; DEC-28 |
| Q-001-02 | Resolvido | SGBD e versão compatíveis com a matriz do Moodle 5.2. **DEC-28:** MariaDB 11.4. A compatibilidade com a matriz não foi verificada (R-G4). | §Conformidade, §Regras 7; DEC-28 |
| Q-001-03 | Resolvido | Imagem base, versão exata do PHP (8.3 ou 8.4) e servidor web. **DEC-28:** `moodlehq/moodle-php-apache:8.3`, PHP 8.3, Apache. | §Conformidade, §Regras 7; DEC-28 |
| Q-001-04 | Resolvido | Portas encaminhadas (`forwardPorts`). **DEC-28:** mapeamentos do Compose `8080:80`, `8025:8025`, `3308:3306`. **Em aberto:** `forwardPorts` no `devcontainer.json`. **Resolvido por DEC-49 (26/10/2026):** `forwardPorts` 8080 e 8025, sem a porta do banco; validação em CL-01. *(Tipo anterior: "Parcial".)* | §Contratos, §Regras 7; DEC-28; DEC-49 |
| Q-001-05 | Resolvido | Comandos e scripts de criação/pós-criação; o que é "preparação reprodutível". **DEC-28:** `postCreateCommand` atual (`fixmoodleowner.sh`). **Em aberto:** instalação do Moodle na criação do ambiente e fixação de versões (commit de `MOODLE_502_STABLE`, imagens `composer` e `mailhog`). **Resolvido por DEC-49 D1/D2 (26/10/2026):** script idempotente de instalação por CLI; Composer sem `--no-dev`; commit do Moodle e tags de Composer, MailHog e Selenium fixados na primeira validação (valores registrados em CL-01, não inventados agora). *(Tipo anterior: "Parcial".)* | §Regras 4, 7; DEC-28; DEC-49 |
| Q-001-06 | Resolvido | Serviços auxiliares, incluindo o mecanismo que dispara as execuções periódicas exigidas por spec-007/spec-019 no ambiente. **DEC-28:** `mailhog`. **Em aberto:** mecanismo de execução periódica (cron). **Resolvido por DEC-49 D1/D3 (26/10/2026):** serviço `cron` executando `admin/cli/cron.php` a cada minuto e serviço Selenium com Chrome para o Behat; testes independentes do cron (DEC-48). *(Tipo anterior: "Parcial".)* | §Regras 7; spec-007; spec-019; DEC-28; DEC-49 |
| Q-001-07 | Resolvido | Credenciais administrativas do Moodle de desenvolvimento e forma de provisioná-las sem gravá-las no Git. (As credenciais do banco de dev foram aprovadas em Q-001-09 por DEC-28.) **Resolvido por DEC-49 D2 (26/10/2026):** senha do administrador vinda de secret do Codespaces, usada pelo script de instalação, fora do Git. *(Tipo anterior: "Aberto".)* | §Regras 5, 7; DEC-49 |
| Q-001-08 | Resolvida *(tipo original: "Parcial")* | Montagem **ou** sincronização do plugin, e relação entre a raiz Git e `assistente/`. **Decidido por DEC-27 (25/10/2026):** montagem de `assistente/` (raiz do repositório, mesmo nível de `.devcontainer/`) em `/var/www/html/public/local/assistente`. **Pendente:** o diretório `assistente/` com o plugin `local_assistente` ainda não existe no repositório (origem futura, a disponibilizar depois da geração das tasks; CL-24), e a montagem não foi validada em execução (CL-01). *(31/10/2026: montagem observada em execução em T013/Q2, 29/10/2026, com `local_assistente` reconhecido; CL-01 Fechado com ressalvas e CL-24 Fechado. O tipo da questão não foi alterado.)* *(04/11/2026, etapa 5a: Resolvida; evidência: DEC-27; o diretório `assistente/` com o plugin `local_assistente` existe e a montagem foi observada em execução em T013 [x] (Q2, 29/10/2026); CL-01 Fechado e CL-24 Fechado em 31/10/2026.)* | §Objetivo, §Contratos; DEC-27 |
| Q-001-09 | Resolvido | Nomes dos secrets e das variáveis não secretas. Os secrets da LLM dependem de Q-009-01. **DEC-28:** variáveis de ambiente observadas, incluindo as credenciais do banco de dev em texto, aceitas só para desenvolvimento. **Em aberto:** nomes dos secrets da LLM. **Resolvido por DEC-49 (26/10/2026):** `ASSISTENTE_LLM_API_KEY`, nome neutro quanto ao fornecedor, como secret do Codespaces passado ao serviço `moodle` (DEC-34 D2); parâmetros adicionais (endpoint, modelo) dependem de CL-19. *(Tipo anterior: "Parcial".)* *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | §Contratos; DEC-28; DEC-34; DEC-49 |
| R-G1 | Resolvido | A constituição v1.0.0 está com status "Proposta para aprovação". **Resolvido em 25/10/2026:** constituição 1.1.0 ratificada. *(Tipo original: "Risco".)* | constitution.md |
| R-G2 | Resolvido | A constituição §2 exige rastrear 66 requisitos atômicos, cujos IDs não constam nas specs. **Resolvido em 25/10/2026:** a constituição 1.1.0 §2 aceita a matriz e os `traceability.md` como instrumento. *(Tipo original: "Risco".)* | constitution.md §2 |
| R-G3 | Aceito como limitação (04/11/2026) *(tipo original: "Risco")* | A spec contém marcadores de citação não resolvíveis (`citeturn…`); as referências externas não foram verificadas. *(04/11/2026, etapa 5a: Aceito como limitação, por decisão do responsável: as citações não são resolvíveis; os marcadores (`cite` e `turn` com caracteres Unicode de uso privado U+E200, U+E202 e U+E201, que `grep citeturn` não encontra) são mantidos nos 8 `spec.md` e no `research.md` porque o texto original é preservado; as regras dessas frases foram confirmadas por decisões e validações, não pelas citações.)* | §Contratos, §Regras 6, §Conformidade |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Nenhuma compatibilidade Moodle 5.2/PHP/BD foi testada nesta fase. *(26/10/2026: verificação de MariaDB 11.4 e PHP 8.3 com os requisitos do Moodle 5.2 em CL-01, sem resultado antecipado.)* *(31/10/2026: verificado no Q8 do CL-01, Fechado com ressalvas: PHP 8.3.36 (mínimo 8.3.0) e MariaDB 11.4.13 (mínimo 10.11.0) com o Moodle 5.2.4+; tags e digests das imagens ficam na T008. O gate §5 não foi reavaliado e não é aprovado automaticamente pela validação do CL-01.)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão do plano 001 (26/10/2026)

Revisão deste plano com DEC-49. Nada foi executado nem testado, e nenhum arquivo de `.devcontainer/` foi alterado.

| Item | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Dependências de desenvolvimento, Selenium, configuração do PHPUnit e do Behat | DEC-49 D1; DEC-48 | Testing; tabela de alterações; Q-001-05/06 | Incorporada; validação em CL-22 |
| Instalação por CLI, senha de admin por secret, fuso do site, versões fixadas | DEC-49 D2; DEC-44 | Q-001-05, Q-001-07 resolvidas | Incorporada; validação em CL-01 |
| Serviço cron | DEC-49 D3; DEC-32; DEC-44 | Q-001-06 resolvida | Incorporada; validação em CL-01 |
| `forwardPorts`, `wwwroot`, secret da LLM *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | DEC-49; DEC-34 D2 | Q-001-04 e Q-001-09 resolvidas | Incorporada; validação em CL-01 |
| Baseline de DEC-28 | DEC-49 | Alterado explicitamente nos itens da tabela; histórico de DEC-28 preservado | Incorporada |
| Origem `assistente/` | DEC-27 | Q-001-08 continua parcial (CL-24) *(04/11/2026, revisão dos planos: Q-001-08 Resolvida (DEC-27); CL-24 Fechado em 31/10/2026.)* | Sem mudança |

O `spec.md` desta feature não foi alterado: DEC-49 afeta só o plano e o baseline do ambiente. A matriz do plano 023 deixa de tratar os cenários do 001 como provisórios depois da aprovação deste plano.

## Complexity Tracking

Nenhuma violação constitucional que exija justificativa. Os bloqueios estão registrados acima.
