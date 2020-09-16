FROM composer:latest

ARG WPSNAPSHOTS_ARCHIVE
ENV WPSNAPSHOTS_ARCHIVE $WPSNAPSHOTS_ARCHIVE

WORKDIR /opt/wpsnapshots

RUN wget -q -c ${WPSNAPSHOTS_ARCHIVE} -O - | tar -xz --strip 1 && \
    composer install --no-dev --no-progress && \
    composer clear-cache

WORKDIR /var/www/html

ENTRYPOINT [ "/opt/wpsnapshots/bin/wpsnapshots" ]
CMD [ "help" ]
