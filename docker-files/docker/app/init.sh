#!/usr/bin/env bash

source /root/.bashrc
cd /var/www/html
supervisord -c /etc/supervisord.conf