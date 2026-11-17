# Rastreabilidade — spec-001

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-001 | Usar GitHub Codespaces como ambiente de desenvolvimento | spec-001; Etapa 3 §3.3 | Confirmado |
| AT-002 | Executar Moodle em containers Docker | spec-001; Etapa 3 §3.3 | Confirmado |
| AT-003 | Alvo Moodle 5.2 / MOODLE_502_STABLE | spec-001; Constituição §5 e §12 *(1.1.0; referência original: §4 da constituição do pacote modular, ajustada em 25/10/2026)* | Confirmado |
| AT-004 | Plugin local_assistente no caminho Moodle /var/www/html/public/local/assistente *(alterado em 25/10/2026 por DEC-27; texto original: "/var/www/html/local/assistente")* | spec-001; Constituição §12 *(1.1.0; referência original: §4 da constituição do pacote modular, ajustada em 25/10/2026)*; DEC-27 | Confirmado por decisão aprovada (DEC-27) |
| AT-005 | Preservar repositório Git e não commitar segredos | spec-001 §Validação | Confirmado |
| AT-006 | Escolher imagem, portas, banco e serviços no plan.md | spec-001 §Validação; DEC-28; DEC-49 | Confirmado no plano: baseline (DEC-28) e alterações de DEC-49; validação em CL-01 e CL-22 *(alterado em 26/10/2026; estado anterior: "Parcial: baseline aprovado (DEC-28)"; estado original: "Pendente de plano")* |
| AT-048 | Manter credenciais fora do banco funcional e do Git | spec-001; spec-009 | Confirmado |

## Decisões aprovadas (DEC)

| DEC | Área (registro §3) | Efeito nesta spec | Estado |
|---|---|---|---|
| DEC-27 | Ambiente e localização do plugin | Origem `assistente/` (raiz do repositório, mesmo nível de `.devcontainer/`); destino `/var/www/html/public/local/assistente`; montagem `../assistente:/var/www/html/public/local/assistente` | Decisão registrada e propagada em 25/10/2026; origem ausente no repositório; não validada em execução *(04/11/2026, revisão dos planos: CL-24 Fechado em 31/10/2026: o diretório `assistente/` existe; montagem observada em T013 [x].)* |
| DEC-28 | Ambiente e localização do plugin | Baseline do ambiente aprovado (C-001-01): Compose, MariaDB 11.4, PHP 8.3/Apache, portas, `postCreateCommand`, `mailhog` e variáveis observados; lacunas em Q-001-04…07 e 09 | Registrada e propagada ao plano 001 em 25/10/2026; não validada em execução *(26/10/2026: alterada parcialmente por DEC-49; texto preservado)* |
| DEC-49 | Ambiente e localização do plugin | Composer sem `--no-dev`, Selenium, PHPUnit/Behat no `config.php`, instalação por CLI, senha de admin por secret, fuso do site, versões fixadas, cron, `forwardPorts`, `wwwroot`, `ASSISTENTE_LLM_API_KEY` *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | Registrada e propagada ao plano 001 em 26/10/2026; nenhuma alteração em `.devcontainer/`; validação em CL-01 e CL-22 |
| DEC-34 | Integração com LLM externa | Credencial por variável de ambiente (D2) *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | Incluída em 26/10/2026 |
| DEC-48 | Testes | Runners PHPUnit e Behat no ambiente | Incluída em 26/10/2026 |

*(Alterado em 25/10/2026 por DEC-27; texto original: "Nenhuma decisão tem esta spec listada na seção 3 do registro.")*

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md). Nada foi executado. *(26/10/2026: Q1–Q12; Q7–Q12 são validação técnica em CL-01 e CL-22.)* *(31/10/2026: CL-01 Fechado com ressalvas. Q3, Q5, Q9 e Q10 interno APROVADOS; Q6, Q8 e Q11 APROVADOS COM RESSALVA; Q1, Q4, Q7 e Q10 externo DISPENSADOS; "Nada foi executado" vale só para a data original. Detalhes em `closure-register.md` CL-01.)*
