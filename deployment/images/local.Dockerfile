# build/deploy via build/deploy via repo/deployment/scripts/circle-ci/inventcorp-base-image-build-deploy.sh , test deployment/scripts/local/test-inventcorp-base-image-build.sh
# BASE_IMAGE is set based on values in deployment/kubernetes/.env.base-image
FROM webdevops/php-nginx-dev:7.3 AS build

MAINTAINER devops@novapulsar.com

# packages
RUN apt-get update -y && \
    apt-get install -y \
    libicu-dev \
    vim \
    wget \
    unzip \
    sudo \
    python3 \
    python3-pip \
    nano \
    autoconf \
    automake \
    gettext \
    openssl \
    less \
    procps \
    zlib1g-dev \
    libc-client2007e \
    libmemcached11 \
    libmemcachedutil2 \
    binutils \
    inetutils-ping \
    inetutils-telnet \
    ncat
#    newrelic-php5

## install newrelic repository
#RUN echo 'deb http://apt.newrelic.com/debian/ newrelic non-free' > /etc/apt/sources.list.d/newrelic.list && \
#    wget -O- https://download.newrelic.com/548C16BF.gpg | apt-key add -

## install newrelic
#ENV NR_INSTALL_SILENT true
#RUN bash newrelic-install install

#RUN pecl install grpc-1.27.0 && \
#    docker-php-ext-enable grpc && \
#    pecl install protobuf-3.10.0 && \
#    docker-php-ext-enable protobuf

# pip self-upgrade
RUN pip3 install --upgrade pip

# install latest supervisor
RUN pip3 install --upgrade setuptools
RUN pip3 install 'supervisor>=3.3.0';

# enable opcache
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/zz-base-php.ini && \
    echo "opcache.memory_consumption=512" >> /usr/local/etc/php/conf.d/zz-base-php.ini && \
    echo "opcache.interned_strings_buffer=64" >> /usr/local/etc/php/conf.d/zz-base-php.ini && \
    echo "opcache.max_accelerated_files=32531" >> /usr/local/etc/php/conf.d/zz-base-php.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/zz-base-php.ini && \
    echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/zz-base-php.ini && \
    echo "opcache.dups_fix=1" >> /usr/local/etc/php/conf.d/zz-base-php.ini

EXPOSE 80

# install wkhtmltopdf
# @see https://wkhtmltopdf.org
RUN apt-get install -y xfonts-75dpi xfonts-base && \
    cd /opt && \
    wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6-1/wkhtmltox_0.12.6-1.buster_amd64.deb && \
    apt install -y ./wkhtmltox_0.12.6-1.buster_amd64.deb && \
    rm wkhtmltox_0.12.6-1.buster_amd64.deb

RUN echo "alias art=\"php artisan\"" >> /root/.bashrc

# fix fs
RUN mkdir /var/log/nginx/ && \
    mkdir -p /var/lib/nginx/tmp && \
    chmod o+rx /var/lib/nginx && \
    chmod o+rx /var/lib/nginx/tmp && \
    mkdir /var/log/supervisor/ && \
    unlink /etc/nginx/conf.d/10-docker.conf && \
    unlink /opt/docker/etc/nginx/vhost.conf && \
    unlink /opt/docker/etc/nginx/vhost.common.conf

# disable unused php extensions, but allow reenabling them if needed
RUN mv /usr/local/etc/php/conf.d/00-ioncube.ini /usr/local/etc/php/conf.d/00-ioncube.ini.disabled && \
#    mv /usr/local/etc/php/conf.d/docker-php-ext-memcached.ini /usr/local/etc/php/conf.d/docker-php-ext-memcached.ini.disabled && \
    mv /usr/local/etc/php/conf.d/docker-php-ext-pdo_pgsql.ini /usr/local/etc/php/conf.d/docker-php-ext-pdo_pgsql.ini.disabled && \
    mv /usr/local/etc/php/conf.d/docker-php-ext-pgsql.ini /usr/local/etc/php/conf.d/docker-php-ext-pgsql.ini.disabled && \
    mv /usr/local/etc/php/conf.d/mongodb.ini /usr/local/etc/php/conf.d/mongodb.ini.disabled

# slim down the container
RUN apt-get remove -y \
    blackfire-agent && \
    rm /usr/local/etc/php/conf.d/zz-blackfire.ini && \
    apt-get remove -y '.*-dev$' && \
    apt-get remove -y '.*-headers$' && \
    apt-get clean && \
    apt-get autoremove -y && \
    rm -rf /tmp/* /var/tmp/* /var/lib/apt/lists/partial/


MAINTAINER devops@novapulsar.com

COPY deployment/configs/supervisord-local.conf /etc/supervisord.conf
COPY deployment/configs/supervisord-queue-general.conf /etc/supervisord-queue-general.conf
COPY deployment/configs/supervisord-queue-notifications.conf /etc/supervisord-queue-notifications.conf

# enable revalidation of opcache for local image
RUN echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/zz-local-php.ini && \
    echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/zz-local-php.ini

# add project's xdebug config
COPY deployment/configs/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# project php.ini
COPY deployment/configs/php.ini /usr/local/etc/php/conf.d/zz-project-php.ini

COPY deployment/configs/phpx /usr/local/bin/
RUN chmod +x /usr/local/bin/phpx

# add crontab file in the cron directory
COPY deployment/configs/crontab /etc/cron.d/crontab

# give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/crontab && \
    touch /etc/cron.d/crontab

# nginx config
COPY deployment/configs/web.conf /etc/nginx/conf.d

# fix for virtualbox
RUN sed 's/#CUSTOM_PARAMS_WILL_BE_HERE/sendfile off;/' -i /etc/nginx/conf.d/web.conf ;

## disable newrelic for minikube setup
#RUN mv /usr/local/etc/php/conf.d/newrelic.ini /usr/local/etc/php/conf.d/newrelic.ini.disabled

WORKDIR /var/www/

# fix limits issue when LOCAL_MINIKUBE_DRIVER=docker
ARG DOCKER_USER_ID=0
ENV DOCKER_USER_ID ${DOCKER_USER_ID:-0}
ARG DOCKER_GROUP_ID=0
ENV DOCKER_GROUP_ID ${DOCKER_GROUP_ID:-0}

# ensure uid/gid is registered
RUN groupadd -r hostusergroup -g $DOCKER_GROUP_ID || true
RUN useradd -u $DOCKER_USER_ID -g $DOCKER_GROUP_ID hostuser || true

# increase limits for busy systems with many pods
RUN echo "hostuser               hard    nproc           16384" >> /etc/security/limits.conf && \
    echo "hostuser               soft    nproc           8192" >> /etc/security/limits.conf && \
    echo "application            hard    nproc           16384" >> /etc/security/limits.conf && \
    echo "application            soft    nproc           8192" >> /etc/security/limits.conf

# run services
CMD supervisord -c /etc/supervisord.conf

