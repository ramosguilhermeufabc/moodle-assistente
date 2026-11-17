#!/bin/bash
# Instalação idempotente do Moodle por CLI (DEC-49 D2).
# - Só instala quando o banco ainda não tem a tabela de configuração do Moodle.
# - Senha do administrador vinda do secret MOODLE_ADMIN_PASSWORD do Codespaces (fora do Git).
# - Fuso horário do site: America/Sao_Paulo (DEC-44 D2, DEC-49 D2).
set -euo pipefail

SITE_TIMEZONE="America/Sao_Paulo"

if [ -f /var/www/html/public/admin/cli/install_database.php ]; then
    CLI_DIR=/var/www/html/public/admin/cli
else
    CLI_DIR=/var/www/html/admin/cli
fi

run_as_www() {
    su -s /bin/bash www-data -c "$*"
}

# Aguarda o banco aceitar conexões.
db_query() {
    php -r '
        $m = @new mysqli(getenv("MOODLE_DBHOST"), getenv("MOODLE_DBUSER"), getenv("MOODLE_DBPASSWORD"),
            getenv("MOODLE_DBNAME"), (int) getenv("MOODLE_DBPORT"));
        if ($m->connect_errno) { exit(2); }
        $r = $m->query("SHOW TABLES LIKE \"" . (getenv("MOODLE_PREFIX") ?: "mdl_") . "config\"");
        exit($r && $r->num_rows > 0 ? 0 : 1);
    '
}

for attempt in $(seq 1 60); do
    set +e
    db_query
    status=$?
    set -e
    if [ "$status" -ne 2 ]; then
        break
    fi
    echo "install-moodle: aguardando o banco (${attempt}/60)..."
    sleep 2
done

if [ "$status" -eq 2 ]; then
    echo "install-moodle: banco indisponível; instalação não executada." >&2
    exit 1
fi

if [ "$status" -eq 0 ]; then
    echo "install-moodle: Moodle já instalado; nada a fazer."
    exit 0
fi

if [ -z "${MOODLE_ADMIN_PASSWORD:-}" ]; then
    echo "install-moodle: secret MOODLE_ADMIN_PASSWORD ausente; defina-o no Codespaces e recrie o ambiente." >&2
    exit 1
fi

echo "install-moodle: instalando o Moodle por CLI..."
run_as_www "php ${CLI_DIR}/install_database.php \
    --agree-license \
    --lang=pt_br \
    --adminuser=admin \
    --adminpass=\"\${MOODLE_ADMIN_PASSWORD}\" \
    --adminemail=admin@example.com \
    --fullname='Moodle Assistente (dev)' \
    --shortname='assistente-dev'"

run_as_www "php ${CLI_DIR}/cfg.php --name=timezone --set=${SITE_TIMEZONE}"

echo "install-moodle: instalação concluída (fuso do site ${SITE_TIMEZONE})."
