FROM 10up/phpfpm

RUN useradd wpsnapshots && \
    mkdir -p /home/wpsnapshots && \
    mkdir -p /wpsnapshots && \
    chown -R wpsnapshots:wpsnapshots /home/wpsnapshots && \
    chown -R wpsnapshots:wpsnapshots /wpsnapshots && \
    su - wpsnapshots -c "composer global require 10up/wpsnapshots" \
    && su - wpsnapshots -c "composer clear-cache"

ENV PATH="/home/wpsnapshots/.composer/vendor/bin:${PATH}"

COPY ./snapshots.sh /

COPY entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD [ "sh", "-c", "trap 'echo dying; exit' SIGINT SIGTERM; while true; do sleep 2; done"]
