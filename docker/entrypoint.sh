#!/usr/bin/env bash

if [ -d /home/wpsnapshots/.wpsnapshots ]; then
	www_uid=`stat -c "%u" /home/wpsnapshots/.wpsnapshots`
	www_gid=`stat -c "%g" /home/wpsnapshots/.wpsnapshots`
	if [ ! $www_uid -eq 0 ]; then
		usermod -u $www_uid wpsnapshots 2> /dev/null
		groupmod -g $www_gid wpsnapshots 2> /dev/null
	fi
fi

exec su - wpsnapshots -c "cd /var/www/html; /opt/wpsnapshots/bin/wpsnapshots $*"
