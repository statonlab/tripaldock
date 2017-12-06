#!/usr/bin/env bash
CWD="$(pwd)"

cd /var/www/html/sites/all/modules

if [ ! -d "tripal_elasticsearch" ]; then
	git clone https://github.com/tripal/tripal_elasticsearch.git
fi

cd /var/www/html/sites/all/libraries

if [ ! -d "elasticsearch-php" ]; then
	mkdir elasticsearch-php
	cd elasticsearch-php
	echo "{" > composer.json
	echo "}" > composer.json
	composer require "elasticsearch/elasticsearch:~5.0"
fi

cd /var/www/html
drush en -y tripal_elasticsearch

cd CWD