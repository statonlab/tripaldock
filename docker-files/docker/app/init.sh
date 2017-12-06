#!/usr/bin/env bash

source /root/.bashrc
cd /var/www/html

bash /init-cron.sh

supervisord -c /etc/supervisord.conf