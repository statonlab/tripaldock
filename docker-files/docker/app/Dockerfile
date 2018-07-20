# Dockerfile
FROM centos:7
MAINTAINER Abdullah Almsaeed <abdullah@almsaeedstudio.com>

USER root

# Update the system
RUN set -x \
	&& yum update -y

# Install services
# (Apache2, postgresql9, php7.1, curl, git, zip, unzip, supervisor ... etc)
RUN set -x \
 	&& yum install -y wget curl git zip unzip httpd htop crontabs \
	&& wget https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm \
    && wget http://rpms.remirepo.net/enterprise/remi-release-7.rpm \
    && rpm -Uvh remi-release-7.rpm epel-release-latest-7.noarch.rpm \
    && yum install -y yum-utils \
    && yum-config-manager --enable remi-php71 \
    && yum update -y \
    && yum install -y mod_php php-mcrypt php-mbstring php-json php-pgsql \
      				  php-pdo php-xml php-curl php-gd supervisor sudo \
      				  patch which postgresql

WORKDIR /var/www/html

# Clean up the system
RUN yum clean all \
	&& rm -f epel-release-latest-7.noarch.rpm \
	&& rm -f remi-release-7.rpm

# Install composer and drush
COPY install-composer.sh /install-composer.sh
RUN chmod +x /install-composer.sh \
	&& /install-composer.sh \
	&& rm /install-composer.sh \
	&& mv composer.phar /usr/local/bin/composer \
	&& composer global require drush/drush:7.*

ENV PATH "$PATH:/root/.composer/vendor/bin"

# Download drupal and other modules
RUN cd /var/www/html \
	&& drush dl drupal-7 \
   	&& mv drupal-7*/* ./ \
   	&& mv drupal-7*/.editorconfig ./ \
   	&& mv drupal-7*/.gitignore ./ \
   	&& mv drupal-7*/.htaccess ./ \
   	&& rm -r drupal-7.* \
   	&& mkdir /var/www/html/sites/all/modules/custom \
   	&& cd /var/www/html/sites/all/modules \
   	&& drush dl -y ctools, date, devel, ds, link, entity, libraries, \
       redirect, token, uuid, jquery_update, views, webform, field_group, \
       field_group_table, field_formatter_class, field_formatter_settings,admin_menu

# Apply patches
RUN wget --no-check-certificate https://drupal.org/files/drupal.pgsql-bytea.27.patch \
    && patch -p1 < drupal.pgsql-bytea.27.patch \
    && cd /var/www/html/sites/all/modules/views \
    && wget --no-check-certificate https://raw.githubusercontent.com/tripal/tripal/7.x-3.x/tripal_chado_views/views-sql-compliant-three-tier-naming-1971160-30.patch \
    && patch -p1 < views-sql-compliant-three-tier-naming-1971160-30.patch


# Expose http port
EXPOSE 80

# Configuration files
COPY supervisord.conf /etc/supervisord.d/app.ini
COPY apache.conf /etc/httpd/conf.d/apache.conf
COPY configure-permissions.php /configure-permissions.php

# Helper scripts
COPY init-cron.sh /init-cron.sh
RUN chmod +x /init-cron.sh
COPY install-tripal-elasticsearch.sh /install-tripal-elasticsearch.sh
RUN chmod +x /install-tripal-elasticsearch.sh

# Pre-generated Files
COPY settings.php /drupal.settings.php
COPY default.settings.php /default.settings.php

# Activation scripts
COPY init.sh /usr/bin/init.sh
RUN chmod +x /usr/bin/init.sh
ENTRYPOINT ["init.sh"]
