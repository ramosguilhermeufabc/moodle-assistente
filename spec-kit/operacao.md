# Operação do plugin e do ambiente

> **`VIGENTE`** · Como subir o ambiente, rodar as suítes, atualizar o plugin num site em
> funcionamento e executar os scripts de validação. Índice dos documentos:
> [`README.md`](README.md). Este documento **não** é fonte do estado do projeto
> ([`closure-register.md`](closure-register.md)), nem das decisões
> ([`decision-register-DEC-01-25.md`](decision-register-DEC-01-25.md)), nem dos requisitos
> (os `spec.md` em [`specs/`](specs/README.md)).

## Procedência dos comandos

A constituição §10 separa o que foi executado do que foi apenas planejado. Cada comando abaixo
traz a sua origem:

| Marca | Significado |
|---|---|
| **conferido em 08/11/2026** | o comando ou o caminho foi verificado neste contêiner na data, por execução de leitura |
| **evidência T139** | comando efetivamente executado em 04/11/2026, com saída registrada em [`validation/t139/run.md`](validation/t139/run.md) |
| **evidência CL-nn / Tnnn** | comando executado e registrado no item correspondente de [`closure-register.md`](closure-register.md) |
| **não reexecutado** | documentado a partir da fonte indicada, sem execução nesta data |

Nesta sessão **não** foram executados: a inicialização do PHPUnit, a inicialização do Behat, as
suítes completas, o cron e nenhum script de validação. O que foi executado foram leituras de
versão, de caminho e de configuração.

## 1. O ambiente

Cinco serviços, todos com versão fixada (T008, DEC-28 e DEC-49), definidos em
`.devcontainer/docker-compose.yml`:

| Serviço | Imagem | Porta |
|---|---|---|
| `moodle` | `moodlehq/moodle-php-apache:8.3-bookworm@sha256:1db7b6e…`, Moodle no commit `28fdef8` | `8080:80`, encaminhada |
| `db` | `mariadb:11.4.13` | `3308:3306`, **não** encaminhada |
| `cron` | mesma imagem do `moodle`, executando `cron-loop.sh` | — |
| `selenium` | `selenium/standalone-chrome:4.51.0` | — |
| `mailhog` | `mailhog/mailhog:v1.0.1` | `8025:8025`, encaminhada |

`devcontainer.json` encaminha só `8080` e `8025`.

**Caminhos** — conferido em 08/11/2026:

- Moodle em `/var/www/html`, release `5.2.4+ (Build: 20261030)`, branch `502`, PHP 8.3.36;
- o plugin aparece em `/var/www/html/public/local/assistente` (DEC-27), que é o diretório
  `assistente/` deste repositório montado pelo compose;
- o repositório **não** é alcançável de `/var/www/html`: ele está em
  `/workspaces/moodle-assistente`. Comandos que gravam arquivos no repositório precisam de caminho
  absoluto;
- a versão do plugin registrada no banco é `2026110202`, igual à de `assistente/version.php`.

**Atenção ao layout `public/`** — conferido em 08/11/2026. Neste Moodle 5.2 os scripts CLI gerais
ficam em `admin/cli/` (fora de `public/`), e os scripts CLI das ferramentas ficam em
`public/admin/tool/<tool>/cli/`. É por isso que o `cron-loop.sh` testa os dois caminhos antes de
chamar o cron.

**Os quatro scripts de `.devcontainer/scripts/`:**

| Script | Quando roda | O que faz |
|---|---|---|
| `fixmoodleowner.sh` | `postCreateCommand`, antes da instalação | ajusta o dono dos diretórios de dados do Moodle |
| `install-moodle.sh` | `postCreateCommand` | instalação idempotente por CLI (DEC-49 D2): só instala se o banco ainda não tiver a tabela de configuração do Moodle; senha do administrador do secret `MOODLE_ADMIN_PASSWORD`; fuso `America/Sao_Paulo` |
| `write-codespaces-wwwroot.sh` | `postStartCommand`, a cada início | grava em moodledata a URL encaminhada da porta 8080. `CODESPACE_NAME` chega só aos processos lançados pela ferramenta, não ao Apache (PID 1) nem ao cron; moodledata é compartilhado pelos dois, então `config.php` lê essa URL em Web, CLI e cron. Fora do Codespaces o arquivo é removido e vale `http://localhost:8080` |
| `cron-loop.sh` | serviço `cron`, em laço | roda o cron do Moodle a cada 60 segundos como `www-data` (DEC-49 D3) |

Conferido em 08/11/2026: `wwwroot` do site é a URL encaminhada do Codespace, o que indica que o
`write-codespaces-wwwroot.sh` cumpriu o seu papel neste ambiente.

## 2. Subir o ambiente num Codespace novo

Achados da T007, 04/11/2026 — evidência CL-01 e `specs/001-*/quickstart.md`:

1. **Antes de criar o Codespace**, cadastrar o development secret `MOODLE_ADMIN_PASSWORD`
   (DEC-49 D2). Sem ele o `postCreateCommand` falha e os diretórios de dados ficam
   `root:www-data` 755. Nesse caso: rodar `.devcontainer/scripts/fixmoodleowner.sh` e instalar
   pelo instalador web nativo na porta 8080.
2. **O secret `ASSISTENTE_LLM_API_KEY` não deve existir** (DEC-69): a chave da API de LLM é
   configurada somente pela administração do Moodle, na configuração de site do plugin, e fica
   gravada criptografada (`admin_setting_encryptedpassword`). O teste
   `assistente/tests/infrastructure/llm/adapter_factory_test.php` reprova se o código do plugin
   ler variável de ambiente ou chamar `set_config`.
3. O aviso `Could not parse image name ...@sha256:...` no log de criação vem do `FROM` com tag e
   digest (T008) e **não** impede o build.
4. Antes de forçar um idioma num curso, o pacote de idioma precisa estar instalado
   (Administração do site > Idioma > Pacotes de idioma): o idioma forçado só aceita idiomas
   instalados (`PARAM_LANG`). Achado da T130, 04/11/2026.

Para conferir que o ambiente está de pé — conferido em 08/11/2026:

```
cd /var/www/html
grep -E '\$release|\$branch' public/version.php
php admin/cli/cfg.php --name=wwwroot
ls -d public/local/assistente
```

## 3. Rodar o PHPUnit

A suíte do plugin é `local_assistente_testsuite`. O `phpunit.xml` da raiz do Moodle é **gerado**
pela inicialização; conferido em 08/11/2026, ele não existe neste contêiner agora, então a
inicialização é o primeiro passo:

```
cd /var/www/html
php public/admin/tool/phpunit/cli/init.php
```

*(não reexecutado nesta sessão; caminho do script conferido em 08/11/2026. A inicialização cria as
tabelas de teste com prefixo próprio e não toca nos dados do site.)*

Suíte completa, como executada na T139 — **evidência T139**:

```
cd /var/www/html
XDEBUG_MODE=off php -d memory_limit=-1 vendor/bin/phpunit --testsuite local_assistente_testsuite
```

Resultado registrado em 04/11/2026: 1588 testes, 10389 asserções, 0 falhas, 0 erros, 5 ignorados,
0 warnings, 4 notices, em 20m35s de relógio.

Um arquivo só, para trabalhar:

```
cd /var/www/html
php vendor/bin/phpunit public/local/assistente/tests/architecture_test.php
```

**Cobertura de linhas.** A T139 usou uma configuração própria, `phpunit-t139.xml`, preservada em
`validation/t139/`, com o filtro que mede só o código do plugin, sem `tests/`, `lang/`,
`templates/` e a biblioteca de terceiros `lib/pdfparser`. Para repetir, copie esse arquivo para
`/var/www/html/` e acrescente `-c phpunit-t139.xml` e as opções do PCOV, como está em
[`validation/t139/run.md`](validation/t139/run.md). A cobertura é medida e relatada, **sem
limiar**: não é critério de aprovação (DEC-48).

**Duas observações sobre o comando registrado na T139.** Ele traz `env -u ASSISTENTE_LLM_API_KEY`,
que era necessário antes da DEC-69 e hoje é vestigial, já que a variável não existe mais. E ele
redireciona a saída para `spec-kit/validation/t139/…`, caminho relativo que **não** resolve a
partir de `/var/www/html`: o repositório não é alcançável de lá. Use caminho absoluto
(`/workspaces/moodle-assistente/spec-kit/validation/...`) ao gravar saída no repositório.

## 4. Rodar o Behat

Conferido em 08/11/2026: o Behat também não está inicializado neste contêiner
(`/var/www/behatdata/behatrun/behat` não existe). Sequência registrada na T139 — **evidência
T139** e CL-22:

```
cd /var/www/html
php public/admin/tool/behat/cli/init.php --no-composer-self-update
vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml --tags=@local_assistente --format pretty
php public/admin/tool/behat/cli/util.php --disable
```

A opção `--no-composer-self-update` é obrigatória aqui: o Composer é fixado em 2.11.1 pela T008 e
o `www-data` não grava em `/var/www`. O Selenium com Chrome é o serviço `selenium` do compose.

Resultado registrado em 04/11/2026: 22 cenários e 233 passos, todos aprovados, em 1m35s.

## 5. Atualizar o plugin num site em funcionamento

O dono deste checklist é o §4.5 de [`closure-register.md`](closure-register.md), a partir da
CL-37; a lista abaixo é a execução dele, com o caminho conferido em 08/11/2026.

1. **Purgar o cache de strings e o de observers.** Pela administração
   (Administração do site > Desenvolvimento > Limpar caches) ou por CLI:

   ```
   cd /var/www/html
   php admin/cli/purge_caches.php --lang --muc
   ```

   Conferido em 08/11/2026: as opções `--lang` (cache de strings) e `--muc` (todos os caches MUC,
   que incluem o de observers) existem neste Moodle. Sem o cache de strings aparecem `[[…]]` no
   lugar das strings novas; sem o de observers, o observer de exclusão de curso não é registrado e
   `delete_course()` deixa as linhas do plugin — achado da CL-37.
2. **Reiniciar os executores de tarefas de longa duração** (o serviço `cron` e executores ad hoc).
   Eles mantêm em memória as classes já carregadas e não enxergam código novo.
3. **Alteração de esquema exige `db/upgrade.php` com savepoint e incremento de
   `assistente/version.php`** (DEC-29). Depois da primeira instalação, nenhuma alteração de
   esquema é feita direto no `install.xml`. A fonte do esquema é `assistente/db/install.xml`
   (constituição §1).

## 6. Tarefas agendadas

Cinco tarefas, declaradas em `assistente/db/tasks.php` — conferido em 08/11/2026:
`process_queue`, `detect_quiz_close`, `detect_reference_dates`, `reconcile_material_versions` e
`report_dispatcher`. No ambiente de desenvolvimento o serviço `cron` chama o cron do Moodle a cada
60 segundos; num site real vale a cron do próprio site.

## 7. Scripts de validação em Moodle real

Ficam em [`validation/`](validation/README.md), que descreve cada pasta, o item CL ou T que ela
atende e quais scripts criam dados temporários. Regra geral: rodar de `/var/www/html` com
`php <script>`. Vários scripts **gravam no site em que são executados** — leia o README da pasta
antes, e use `php cleanup_temp.php --dry-run` para listar resíduo antes de remover.

## 8. Conferir a documentação

Antes de abrir um PR que toque documentação, rode a conferência — leitura, sem alterar nada:

```
cd /workspaces/moodle-assistente
python3 spec-kit/validation/docs-check/verificar.py
```

*(conferido em 08/11/2026: as cinco checagens aprovadas, saída 0.)* Ele acha link relativo
quebrado, caminho citado que não existe em documento `VIGENTE`, identificador `DEC`/`CL`/`T` citado
no código sem fonte no registro, codepoint de uso privado em arquivo de texto, e documento da raiz
do `spec-kit/` ou de `history/` sem rótulo de ciclo de vida ou fora do índice. Sai com código 1 se
qualquer checagem reprovar, e **não** diz se o conteúdo da documentação é verdadeiro: frase
desatualizada continua sendo trabalho de leitura (constituição §10).

A varredura de pendências é separada, com dono próprio:

```
cd /workspaces/moodle-assistente
python3 spec-kit/validation/pendencias-final/classificar.py
```

*(conferido em 08/11/2026: 357 ocorrências, (i) 305, (ii) 47, (iii) 5, (iv) 0, saída 0.)*

## 9. O que este documento não cobre

- **estado, pendências e evidências de fechamento** — [`closure-register.md`](closure-register.md);
- **por que cada regra é como é** — [`decision-register-DEC-01-25.md`](decision-register-DEC-01-25.md);
- **o que o sistema deve fazer** — os `spec.md` em [`specs/`](specs/README.md);
- **arquitetura, ciclo de processamento e vocabulário de identificadores** —
  [`onboarding.md`](onboarding.md);
- **cenários de validação do ambiente (Q1–Q12)** — `specs/001-*/quickstart.md`, que é registro de
  validação e não guia de execução.
