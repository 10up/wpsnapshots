#!/bin/bash
set -e

wpsnapshots='/home/wpsnapshots/.composer/vendor/bin/wpsnapshots'
wpdir='/var/www/html'

maybe_run_wpsnapshots() {
    if [ -e /home/wpsnapshots/.wpsnapshots/config.json ]; then

        su - wpsnapshots -c  "cd $wpdir; $wpsnapshots $*"
     else
        echo 'WP Snapshots is not configured, you must run ./wpsnapshots.<sh|bat> configure <repository> from the bin/ directory';
        exit 1;
    fi
}

case "$1" in
    configure)
        su - wpsnapshots -c "$wpsnapshots $*"
        ;;
    *)
        maybe_run_wpsnapshots "$@"
        ;;
esac
