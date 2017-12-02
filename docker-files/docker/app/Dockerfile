# Dockerfile
FROM centos:7

USER root

# Update the system
RUN set -x \
	&& yum update -y

# Install services
# (Apache2, postgresql9, php7.1, openssh-server, curl, git, zip, unzip, supervisor)
RUN set -x \
 	&& yum install -y wget curl git zip unzip httpd openssh-server \
	&& wget https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm \
    && wget http://rpms.remirepo.net/enterprise/remi-release-7.rpm \
    && rpm -Uvh remi-release-7.rpm epel-release-latest-7.noarch.rpm \
    && yum install -y yum-utils \
    && yum-config-manager --enable remi-php71 \
    && yum update -y \
    && yum install -y mod_php php-mcrypt php-mbstring php-json php-pgsql \
      				  php-pdo php-xml php-curl php-gd supervisor

# Clean up the system
RUN set -x \
	&& yum clean all

# Prepare ssh
RUN mkdir /var/run/sshd
RUN ssh-keygen -t rsa -f /etc/ssh/ssh_host_rsa_key -N ''

# Expose http and ssh ports
EXPOSE 80
EXPOSE 22

# Configuration files
COPY supervisord.conf /etc/supervisord.d/app.ini
COPY apache.conf /etc/httpd/conf.d/apache.conf

# Activation scripts
COPY init.sh /usr/bin/init.sh
RUN chmod +x /usr/bin/init.sh
CMD ["init.sh"]