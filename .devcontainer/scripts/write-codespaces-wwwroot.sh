#!/bin/bash
# Grava a URL encaminhada da porta 8080 do Codespaces em moodledata (postStartCommand).
# Motivo: CODESPACE_NAME e o domínio de encaminhamento chegam ao Dev Container como remoteEnv/secrets,
# visíveis só a processos lançados pela ferramenta, e não ao Apache (PID 1) nem ao cron. O moodledata é
# compartilhado por moodle e cron, então config.php lê esta URL em Web, CLI e cron (DEC-49).
# Fora do Codespaces (sem CODESPACE_NAME) o arquivo é removido e config.php usa http://localhost:8080.
set -eu

FILE=/var/www/moodledata/codespaces-wwwroot

if [ -z "${CODESPACE_NAME:-}" ]; then
    rm -f "$FILE"
    echo "write-codespaces-wwwroot: fora do Codespaces; wwwroot local (http://localhost:8080)."
    exit 0
fi

if [ -z "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" ]; then
    echo "write-codespaces-wwwroot: domínio de encaminhamento ausente; usando app.github.dev." >&2
fi
DOMAIN="${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"

TMP="${FILE}.tmp"
printf 'https://%s-8080.%s\n' "$CODESPACE_NAME" "$DOMAIN" > "$TMP"
chmod 644 "$TMP"
mv "$TMP" "$FILE"
echo "write-codespaces-wwwroot: wwwroot do Codespaces gravado."
