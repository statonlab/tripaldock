#!/usr/bin/env bash

echo "*/2 * * * * drush trp-run-jobs --username=tripal --root=/var/www/html" > crontab_entries
echo "*/5 * * * * drush cron-run queue_elasticsearch_dispatcher --root=/var/www/html" >> crontab_entries
echo "*/5 * * * * drush cron-run queue_elasticsearch_queue_1 --root=/var/www/html" >> crontab_entries
echo "*/5 * * * * drush cron-run queue_elasticsearch_queue_2 --root=/var/www/html" >> crontab_entries
echo "*/5 * * * * drush cron-run queue_elasticsearch_queue_3 --root=/var/www/html" >> crontab_entries
echo "*/5 * * * * drush cron-run queue_elasticsearch_queue_4 --root=/var/www/html" >> crontab_entries
echo "*/5 * * * * drush cron-run queue_elasticsearch_queue_5 --root=/var/www/html" >> crontab_entries

crontab crontab_entries && rm crontab_entries