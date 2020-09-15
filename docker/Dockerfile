FROM 10up/phpfpm

# use "--build-arg WPSNAPSHOTS_VERSION=2.0"
ARG WPSNAPSHOTS_VERSION
ENV WPSNAPSHOTS_VERSION $WPSNAPSHOTS_VERSION

RUN useradd wpsnapshots && \
    mkdir -p /home/wpsnapshots && \
    mkdir -p /wpsnapshots && \
    chown -R wpsnapshots:wpsnapshots /home/wpsnapshots && \
    chown -R wpsnapshots:wpsnapshots /wpsnapshots && \
    su - wpsnapshots -c "composer config -g repositories.wpsnapshots vcs https://github.com/10up/wpsnapshots.git" && \
    su - wpsnapshots -c "composer global require 10up/wpsnapshots:dev-master#${WPSNAPSHOTS_VERSION}" && \
    su - wpsnapshots -c "composer clear-cache"

ENV PATH="/home/wpsnapshots/.composer/vendor/bin:${PATH}"

COPY ./snapshots.sh /
COPY entrypoint.sh /entrypoint.sh

ENTRYPOINT [ "/entrypoint.sh" ]
CMD [ "sh", "-c", "trap 'echo dying; exit' SIGINT SIGTERM; while true; do sleep 2; done" ]
