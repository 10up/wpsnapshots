FROM 10up/phpfpm

ARG WPSNAPSHOTS_ARCHIVE
ENV WPSNAPSHOTS_ARCHIVE $WPSNAPSHOTS_ARCHIVE

WORKDIR /opt/wpsnapshots

RUN useradd wpsnapshots && \
    mkdir -p /home/wpsnapshots && \
    mkdir -p /wpsnapshots && \
    chown -R wpsnapshots:wpsnapshots /home/wpsnapshots && \
    chown -R wpsnapshots:wpsnapshots /opt/wpsnapshots && \
    wget -q -c ${WPSNAPSHOTS_ARCHIVE} -O - | tar -xz --strip 1 && \
    composer install --no-dev --no-progress && \
    composer clear-cache

WORKDIR /var/www/html

ENTRYPOINT [ "/opt/wpsnapshots/bin/wpsnapshots" ]
CMD [ "help" ]
