# Tasks: spec-001 — Infraestrutura & Ambiente GitHub Codespaces

**Input**: [spec.md](spec.md), [plan.md](plan.md) (aprovado em 26/10/2026, DEC-49), [research.md](research.md), [quickstart.md](quickstart.md)

**Ordem global**: entregas E0 e E1 de [`../README.md`](../README.md) (seção "Ordem de entregas das tasks"). IDs globais; os ausentes deste arquivo estão nos `tasks.md` das outras features.

**Requisitos (AT)**: AT-001, AT-002, AT-003, AT-004, AT-005, AT-006, AT-048 ([traceability.md](traceability.md)).

**Decisões**: DEC-27, DEC-28 (baseline), DEC-49 (altera parcialmente DEC-28), DEC-34 D2, DEC-44 (fuso), DEC-48.

**Validações técnicas**: CL-01, CL-22 (não bloqueiam o planejamento; nenhum resultado antecipado). **Pendência explícita**: CL-24 / Q-001-08 (origem `assistente/` ainda inexistente).

**Testes**: as tarefas desta feature alteram arquivos de ambiente, não código do plugin. Os cenários Q1–Q12 do quickstart são todos validação técnica (classificação **CL**, plano 023); não contam como teste aprovado.

## Format: `[ID] [P?] [Entrega] Descrição`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência pendente)
- **[Ek]**: entrega da ordem global (substitui o rótulo de história de usuário)

---

## Entrega E0 — Pré-condições e ambiente

**Objetivo**: aplicar ao `.devcontainer/` as alterações aprovadas por DEC-49 e validar o ambiente (CL-01), sem presumir resultado.

**Verificação independente**: cenários Q3, Q5, Q6 e Q8–Q11 do [quickstart.md](quickstart.md) (Q10 só dentro do Codespaces; Q7 dispensado), executados em T007 e registrados em `closure-register.md` CL-01. *(31/10/2026, revisão de escopo aprovada pelo responsável: Q1, Q4 e a parte externa de Q10 estão DISPENSADOS; texto original: "cenários Q1, Q3–Q11".)*

- [x] T001 [E0] Gate CL-24 (condição de entrada de todas as tarefas que criam ou alteram código em `assistente/`): obter do responsável a forma de disponibilizar a origem `assistente/` na raiz do repositório (código existente a trazer ou criação do esqueleto por T009) e registrar a decisão em `spec-kit/closure-register.md` (CL-24); não criar pasta vazia nem copiar código antes da decisão — decidido em 26/10/2026: esqueleto em `assistente/` criado por T009; ver CL-24
  - Refs: plano 001 Q-001-08; DEC-27; CL-24
- [x] T002 [P] [E0] Remover `--no-dev` do `composer install` em `.devcontainer/moodle/Dockerfile`, para disponibilizar PHPUnit e Behat
  - Refs: plano 001 "Alterações aprovadas sobre o baseline (DEC-49)", linha "Dependências do Composer"; DEC-49 D1; DEC-48 D1; CL-22
- [x] T003 [P] [E0] Ajustar `.devcontainer/moodle/config.php`: `wwwroot` a partir das variáveis do Codespaces com `sslproxy`, e `http://localhost:8080` fora dele; `phpunit_prefix` e `phpunit_dataroot` (reutilizando o volume `phpunitdata` quando apropriado); `behat_prefix`, `behat_dataroot`, `behat_wwwroot` e perfil do Selenium com Chrome
  - Refs: plano 001 linhas "Configuração de testes" e "`wwwroot`"; DEC-49 D1 e tratamentos; CL-01, CL-22
- [x] T004 [E0] Alterar `.devcontainer/docker-compose.yml`: (a) serviço Selenium com Chrome; (b) serviço `cron` executando `admin/cli/cron.php` a cada minuto; (c) variável `ASSISTENTE_LLM_API_KEY` passada do secret do Codespaces ao serviço `moodle`, sem valor no Git; manter os demais itens do baseline (DEC-28) e a montagem `../assistente:/var/www/html/public/local/assistente` (DEC-27) *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)*
  - Refs: plano 001 linhas "Navegador para Behat", "Cron", "Secret da LLM"; DEC-49 D1, D3; DEC-34 D2; DEC-27
- [x] T005 [E0] Incluir `forwardPorts` 8080 (Moodle) e 8025 (MailHog), sem a porta do banco, em `.devcontainer/devcontainer.json`
  - Refs: plano 001 Q-001-04; DEC-49
- [x] T006 [E0] Criar `.devcontainer/scripts/install-moodle.sh`, idempotente: instala o Moodle por CLI só quando ainda não instalado, com a senha do administrador vinda de secret do Codespaces (nome do secret definido nesta tarefa e registrado em CL-01, fora do Git) e fuso do site `America/Sao_Paulo`; chamar o script em `.devcontainer/devcontainer.json` depois de `fixmoodleowner.sh` (depende de T005, mesmo arquivo)
  - Refs: plano 001 Q-001-05, Q-001-07; DEC-49 D2; DEC-44 D2; CL-01
- [x] T007 [E0] VT CL-01: executar os cenários Q3, Q5, Q6, Q8, Q9, Q10 (somente dentro do Codespaces) e Q11 do `quickstart.md` desta feature (compatibilidade de MariaDB 11.4 e PHP 8.3 com o Moodle 5.2; cron; `wwwroot` dentro do Codespaces; secret `ASSISTENTE_LLM_API_KEY` no processo PHP e ausente do Git e do banco; busca de credenciais nos arquivos versionados) e registrar comandos, saídas, commit do Moodle e tags das imagens em `spec-kit/closure-register.md` CL-01, sem resultado antecipado (depende de T002–T006) **[31/10/2026, revisão de escopo aprovada pelo responsável: DISPENSADOS Q1 (Codespace novo), Q4 (recriação do Codespace), Q7 (instalação idempotente; classificação corrigida em 31/10/2026 a pedido do responsável, pois a decisão formal o dispensa) e a parte externa de Q10 (`localhost:8080` fora do Codespaces) — não executar nem registrar como aprovados, executados ou validados; justificativas no `quickstart.md`. Texto original da tarefa: "executar os cenários Q1, Q3, Q4, Q5, Q6, Q7, Q8, Q9, Q10 e Q11 … (Codespace novo e recriado; … `wwwroot` dentro e fora do Codespaces; …)". Comandos, saídas e resultados reais seguem obrigatórios. A T007 continua ABERTA e a dependência T008 → T007 foi mantida: os valores a fixar vêm de Q8, que está mantido]** **[31/10/2026, 21:2x (-03), EXECUÇÃO no contêiner `moodle` ativo (host `a2b4480cbaf4`), sem Docker CLI. Estados: Q3 APROVADO; Q5 APROVADO; Q6 BLOQUEADO (secret `ASSISTENTE_LLM_API_KEY` não configurado); Q7 DISPENSADO (corrigido; a execução parcial do ramo "já instalado" é só histórico e não aprova o cenário; sem exigência de `MOODLE_ADMIN_PASSWORD` nem de banco descartável); Q8 BLOQUEADO (compatibilidade e versões aprovadas; MailHog e digests das imagens não observáveis sem Docker); Q9 APROVADO; Q10 APROVADO (só endereço interno, sem abrir a URL encaminhada no navegador); Q11 BLOQUEADO (depende do Q6). Q1, Q4 e Q10 externo DISPENSADOS, não executados; Q7 DISPENSADO. Detalhes e saídas em `closure-register.md` CL-01. T007 permanece ABERTA e T008 não foi executada]** **[31/10/2026, CL-01 Fechado com ressalvas por decisão do responsável (`closure-register.md` CL-01): Q3, Q5, Q9 e Q10 interno APROVADOS; Q6, Q8 e Q11 APROVADOS COM RESSALVA; Q1, Q4, Q7 e Q10 externo DISPENSADOS. A T007 NÃO foi marcada como concluída: o texto da tarefa exige registrar "commit do Moodle e tags das imagens", e as tags efetivas de Composer, MailHog e Selenium e os digests não foram obtidos (sem Docker Engine); o commit do Moodle e as versões observadas estão registrados. A conclusão da T007 aguarda decisão expressa do responsável. T008 NÃO executada; a dependência T008 → T007 foi mantida]** *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* *(04/11/2026, CONCLUÍDA no Codespace novo, `closure-register.md` CL-01 "Execução de 04/11/2026": Q1, Q2, Q3, Q5, Q8, Q9, Q10 interno e Q12 aprovados; Q6 e Q11 substituídos pela DEC-69; Q4 e Q7 (b) dispensados; Q7 (a) = instalação pelo instalador web nativo, feita pelo responsável, pois `MOODLE_ADMIN_PASSWORD` não foi usado (decisão de 04/11/2026). Divergências registradas na CL-01.)*
  - Refs: quickstart 001 Q3, Q5, Q6, Q8–Q11 (Q10 só dentro do Codespaces); Q1, Q4, Q7 e Q10 externo dispensados em 31/10/2026; CL-01; R-G4
- [x] T008 [E0] Fixar em `.devcontainer/moodle/Dockerfile` o commit do Moodle e em `.devcontainer/docker-compose.yml` as tags de Composer, MailHog e Selenium registrados em T007 (depende de T007; nenhum valor inventado antes da validação) *(03/11/2026, DEC-67: a T008 também fixa a versão do yt-dlp instalado pelo apt no Dockerfile, hoje 2023.03.04-1 (Debian 12), e a versão da biblioteca smalot/pdfparser incluída no plugin, 2.13.1.)* *(03/11/2026, DEC-68: a referência ao pacote apt acima foi substituída; o yt-dlp é o executável `yt-dlp_linux` 2026.10.26, em versão fixa (`ARG YTDLP_VERSION` do Dockerfile), conferido por SHA-256; a T008 mantém essa versão fixada e a da biblioteca smalot/pdfparser, 2.13.1.)* *(04/11/2026, decisão do responsável: a ordem passa a **T008 → T007**. As versões foram fixadas em 04/11/2026 a partir do que está em execução neste Codespace e serão validadas na T007, num Codespace novo criado a partir do repositório remoto. Valores: Moodle commit `28fdef85e7043c8dd758af8c1a146da1419db0d0` (5.2.4+, Build 20261030; `git -c safe.directory=/var/www/html -C /var/www/html log -1 --format=%H` e `public/version.php`); Composer `composer:2.11.1` (`composer --version`; tag na API do Docker Hub); MariaDB `mariadb:11.4.13` (`$DB->get_server_info()` = 11.4.13); MailHog `mailhog/mailhog:v1.0.1` (API do Docker Hub: `latest` e `v1.0.1` têm o mesmo digest `sha256:8d76a3d4ffa32a3661311944007a415332c4bb855657f4f6c57996405c009bea`); Selenium `selenium/standalone-chrome:4.51.0` (`curl http://selenium:4444/status` = 4.51.0); PHP/Apache `moodlehq/moodle-php-apache:8.3-bookworm@sha256:1db7b6ed013fcb5268095b5e28c410ba9117d38d9b6a324c100c48fcbe69745e` (digest do índice OCI da tag 8.3-bookworm, cabeçalho `Docker-Content-Digest` de `registry-1.docker.io`, tag atualizada em 22/10/2026); yt-dlp 2026.10.26 e smalot/pdfparser 2.13.1 sem mudança. Digests consultados no Docker Hub em 04/11/2026 (evidência): composer:2.11.1 `sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c`; mariadb:11.4.13 `sha256:d1891ede5a559f1086f55053bb8cc1569b0298338c4d72d71725a7f74f6ba620`; selenium/standalone-chrome:4.51.0 `sha256:d6843a37cd2852fac7f33099709a203769f864dcf260e740a34e9605055c1dd5`. Só o digest da imagem de PHP/Apache foi gravado no Dockerfile; as demais ficam fixadas por tag de versão. Alterados `.devcontainer/docker-compose.yml` (tags e `MOODLE_COMMIT` nos serviços `moodle` e `cron`) e `.devcontainer/moodle/Dockerfile` (`FROM` com digest, `COPY --from=composer:2.11.1`, `ARG MOODLE_COMMIT` e busca rasa do commit exato). Nada foi construído (sem Docker neste contêiner). **A T008 continua [ ] até a construção ser validada na T007, no Codespace novo.** Ressalva: a correspondência do digest da imagem de PHP com a imagem em execução neste Codespace não pôde ser verificada sem Docker; a versão do PHP (8.3.36) confere.)* *(04/11/2026: **[x]**: a construção com as versões fixadas foi validada na T007 (Q8: versões em execução iguais às fixadas; build concluído apesar do aviso de digest), registro na CL-01. Ressalva: o digest da imagem de PHP em execução não foi comparado (sem Docker). O texto "continua [ ] até a construção ser validada" valia até esta data.)*
  - Refs: plano 001 linha "Versões"; DEC-49 D2; CL-01

**Checkpoint E0**: ambiente alterado conforme DEC-49 e CL-01 com registro de execução (aprovado ou divergência). A divergência é tratada antes de E1, por tarefa própria.

---

## Entrega E1 — Validação do plugin e dos runners no ambiente

- [x] T013 [E1] VT CL-01 (Q2) e CL-22 (Q12): com o esqueleto do plugin (T009, T011) e o primeiro teste (T012), verificar `/var/www/html/public/local/assistente` refletindo `assistente/` e o reconhecimento de `local_assistente`; inicializar PHPUnit e Behat do Moodle e executar um teste de cada runner (T012 e uma feature Behat mínima); registrar comandos e saídas em `spec-kit/closure-register.md` CL-01 e CL-22, sem resultado antecipado (depende de T007, T009, T011, T012) **[29/10/2026, evidência: suíte `local_assistente_testsuite` EXECUTADA no Moodle 5.2.4+, PHPUnit 11.5.55, MariaDB 11.4.13: OK, 1175 testes, 6070 asserções, 0 falhas, 5 skipped (travas reentrantes no mesmo processo), 4 notices]** **[29/10/2026: PHPUnit (suíte completa, OK) e Behat (`assistente/tests/behat/plugin_installed.feature`, 1 cenário e 3 passos aprovados, Selenium/Chrome; `admin/tool/behat/cli/init.php` com prefixo `beh_`) EXECUTADOS; `/var/www/html/public/local/assistente` é link simbólico para `assistente/` e `local_assistente` aparece instalado. Registro em CL-01/CL-22 do `closure-register.md`. A dependência de sequência T007 (Codespace novo/recriado, Q1/Q4/Q6/Q7/Q11) continua ABERTA e não foi presumida]** *(08/11/2026: CL-01 foi fechado em 31/10/2026, por decisão do responsável, e CL-22 em 04/11/2026, validação técnica; as pendências afirmadas acima não estão mais abertas. Texto anterior preservado, conforme a constituição §11.)*
  - Refs: quickstart 001 Q2, Q12; DEC-27; DEC-49 D1; CL-01, CL-22
  - Efeito: a execução dos testes automatizados das tarefas seguintes depende desta VT; divergências são registradas e tratadas antes

---

## Dependencies & Execution Order

- T001 é condição de entrada para T009 em diante (todas as tarefas de código).
- T002, T003 em paralelo; T004 → T005 → T006 (arquivos compartilhados entre T005 e T006); T007 depende de T002–T006; T008 depende de T007. *(04/11/2026: a ordem passa a T008 → T007, por decisão do responsável; a T007 valida as versões fixadas pela T008.)*
- T013 depende de T007, T009, T011 e T012 (002).

## Parallel Example

```text
T002 .devcontainer/moodle/Dockerfile
T003 .devcontainer/moodle/config.php
T004 .devcontainer/docker-compose.yml
```

## Implementation Strategy

E0 completo antes de qualquer código. Nenhum valor de versão é fixado antes de T007. *(04/11/2026: os valores foram fixados antes da T007, por decisão do responsável; ver a T008.)*

## Notes

- Nada foi executado. Nenhum arquivo de `.devcontainer/` foi alterado nesta etapa.
- Q-001-08 permanece parcial até T001 e T013.
