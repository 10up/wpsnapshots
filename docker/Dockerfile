FROM 10up/wp-php-fpm-dev:8.0-ubuntu AS builder

ARG WPSNAPSHOTS_ARCHIVE
ENV WPSNAPSHOTS_ARCHIVE $WPSNAPSHOTS_ARCHIVE

USER root

WORKDIR /opt/wpsnapshots

RUN \
  useradd wpsnapshots && \
  mkdir -p /home/wpsnapshots && \
  chown -R wpsnapshots:wpsnapshots /home/wpsnapshots && \
  wget -q -c ${WPSNAPSHOTS_ARCHIVE} -O - | tar -xz --strip 1 && \
  composer install --no-dev --no-progress && \
  composer clear-cache && \
  chown -R wpsnapshots:wpsnapshots /opt/wpsnapshots

FROM 10up/base-php:7.4-ubuntu

USER root

WORKDIR /opt/wpsnapshots

COPY --from=builder --chown=wpsnapshots=wpsnapshots /opt/wpsnapshots /opt/wpsnapshots
RUN \
  apt-get update && apt-get install mariadb-client -y && apt-get clean all && \
  useradd wpsnapshots && \
  mkdir -p /home/wpsnapshots && \
  chown -R wpsnapshots:wpsnapshots /home/wpsnapshots && \
  chown -R wpsnapshots:wpsnapshots /opt/wpsnapshots
COPY entrypoint.sh /entrypoint.sh

ENTRYPOINT [ "/entrypoint.sh" ]