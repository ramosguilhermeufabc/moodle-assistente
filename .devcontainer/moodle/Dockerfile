# Versões fixas (T008): mudam só por decisão do responsável, num commit.
# Digest do índice multiarquitetura da tag 8.3-bookworm (consultado em 04/11/2026; tag atualizada em 22/10/2026).
FROM moodlehq/moodle-php-apache:8.3-bookworm@sha256:1db7b6ed013fcb5268095b5e28c410ba9117d38d9b6a324c100c48fcbe69745e

# Moodle 5.2.4+ (Build 20261030), commit exato.
ARG MOODLE_COMMIT="28fdef85e7043c8dd758af8c1a146da1419db0d0"

ENV MOODLE_DBTYPE=mariadb
ENV MOODLE_DBHOST=db
ENV MOODLE_DBNAME=moodle
ENV MOODLE_DBUSER=moodle
ENV MOODLE_DBPASSWORD=password
ENV MOODLE_PREFIX=mdl_
ENV MOODLE_DBPORT=3306
ENV MOODLE_WWWROOT_URL=http://localhost

ENV TZ=America/Sao_Paulo

RUN echo "${TZ}" > /etc/timezone \
    && dpkg-reconfigure -f noninteractive tzdata

COPY --from=composer:2.11.1 /usr/bin/composer /usr/bin/composer

USER www-data

RUN git init \
    && git remote add origin https://github.com/moodle/moodle.git \
    && git fetch --depth 1 origin ${MOODLE_COMMIT} \
    && git checkout FETCH_HEAD


# Sem --no-dev: PHPUnit e Behat do Moodle exigem as dependencias de desenvolvimento (DEC-49 D1).
RUN composer install \
    --classmap-authoritative \
    --prefer-dist \
    --no-interaction

USER root

# yt-dlp (Unlicense) para obter titulo, descricao e legenda de videos do YouTube sem API nem chave (DEC-67, DEC-68).
# Executavel oficial yt-dlp_linux das releases do projeto, em versao fixa, conferido pelo SHA-256 publicado em SHA2-256SUMS da mesma
# release; a construcao da imagem falha se nao conferir. Quando o YouTube rejeitar a versao, troque YTDLP_VERSION.
ARG YTDLP_VERSION="2026.10.26"
RUN cd /tmp \
    && curl -fsSL -O "https://github.com/yt-dlp/yt-dlp/releases/download/${YTDLP_VERSION}/yt-dlp_linux" \
    && curl -fsSL -O "https://github.com/yt-dlp/yt-dlp/releases/download/${YTDLP_VERSION}/SHA2-256SUMS" \
    && grep " yt-dlp_linux\$" SHA2-256SUMS | sha256sum -c - \
    && install -m 0755 yt-dlp_linux /usr/local/bin/yt-dlp \
    && rm -f yt-dlp_linux SHA2-256SUMS
