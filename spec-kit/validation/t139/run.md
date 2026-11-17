# T139 — execução das suítes completas (04/11/2026)

<!-- RESUMO:inicio -->
- **Data:** 04/11/2026 (PHPUnit iniciado às 02:14 e Behat às 10:06, horário do shell, UTC-3)
- **Commit testado:** `bb1f22aa5c2b35f0bdcd0b292007b2d386c859d5` (`git rev-parse HEAD`; árvore limpa, só os arquivos de `spec-kit/validation/t139/` não versionados)
- **Ambiente:** Moodle 5.2.4+ (Build: 20261030), PHP 8.3.36 (com PCOV 1.0.12), MariaDB 11.4.13, PHPUnit 11.5.55, Behat 3.29.0, Selenium 4.51.0 (Chrome), Composer 2.11.1; executado como `www-data` no contêiner `moodle`
- **PHPUnit** (`local_assistente_testsuite`, 20m35s no relógio, 20:24.560 pelo PHPUnit): 1588 testes, 10389 asserções, 0 falhas, 0 erros, 5 ignorados (skips), 0 warnings, 4 notices (`debugging()` do Moodle em `cl33_types_test`, variante "unknown seed")
- **Behat** (`--tags=@local_assistente`, 1m35s): 22 cenários (22 aprovados), 233 passos (233 aprovados)
- **Cobertura de linhas (pcov, sem limiar, só código do plugin sem `tests/`):** 91,91% (6114/6652 linhas); métodos 84,99% (719/846); classes 59,78% (107/179). Por diretório de classes (linhas): application 94,27%; domain 99,27%; infrastructure 94,53%; observer 94,85%; task 97,80%; output 92,99%; form 98,94%; privacy 98,85%; event 80,00%; hook_callbacks 86,67%. Detalhe em `coverage.txt`.
<!-- RESUMO:fim -->

## Comandos exatos

Configuração de cobertura: `phpunit-t139.xml` (nesta pasta; usado a partir de `/var/www/html/phpunit-t139.xml`, copiado para cá em 04/11/2026 para a T140 repetir a cobertura; copiá-lo de volta para `/var/www/html/` antes de usar), cópia do `phpunit.xml` gerado pelo Moodle com o bloco `<source>` substituído por `classes/`, `db/` e os arquivos da raiz do plugin (sem `tests/`, `lang/`, `templates/` e `lib/pdfparser`, terceiros). Detalhes em `coverage.txt`.

PHPUnit (como `www-data`, a variável de chave removida do ambiente do processo):

```
cd /var/www/html && env -u ASSISTENTE_LLM_API_KEY XDEBUG_MODE=off php -d pcov.enabled=1 -d pcov.directory=/var/www/html/public/local/assistente -d memory_limit=-1 vendor/bin/phpunit -c phpunit-t139.xml --testsuite local_assistente_testsuite --colors=never --testdox --display-skipped --display-warnings --display-notices --log-junit spec-kit/validation/t139/phpunit-junit.xml --coverage-text > spec-kit/validation/t139/phpunit-output.txt 2>&1
```

Observação: a opção `-c phpunit-t139.xml` foi acrescentada ao comando pedido, para o filtro de cobertura; a suíte é a mesma.

Behat (CL-22):

```
php public/admin/tool/behat/cli/init.php --no-composer-self-update
vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml --tags=@local_assistente --no-colors --format pretty > spec-kit/validation/t139/behat-output.txt 2>&1
php public/admin/tool/behat/cli/util.php --disable
```

Uma primeira execução completa do Behat com o formato padrão (progress) também deu 22 cenários e 233 passos aprovados (1m45s); foi repetida com `--format pretty` porque o formato progress não lista os cenários por arquivo, necessário para a evidência por cenário. A saída gravada é a da segunda execução. O ambiente do Behat foi desabilitado ao final ("Acceptance tests environment disabled").

## Scripts

- `coverage_summary.py` gera `coverage.txt` a partir de `phpunit-output.txt`.
- `generate_evidence.py` gera `specs/023-testes-e-qualidade-transversal/checklists/test-evidence.md` (repetido na T140).
