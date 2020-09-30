FROM 10up/phpfpm

ARG WPSNAPSHOTS_ARCHIVE
ENV WPSNAPSHOTS_ARCHIVE $WPSNAPSHOTS_ARCHIVE

WORKDIR /opt/wpsnapshots

RUN useradd wpsnapshots && \
    mkdir -p /home/wpsnapshots && \
    chown -R wpsnapshots:wpsnapshots /home/wpsnapshots && \
    wget -q -c ${WPSNAPSHOTS_ARCHIVE} -O - | tar -xz --strip 1 && \
    composer install --no-dev --no-progress && \
    composer clear-cache && \
    chown -R wpsnapshots:wpsnapshots /opt/wpsnapshots

COPY entrypoint.sh /entrypoint.sh

ENTRYPOINT [ "/entrypoint.sh" ]
