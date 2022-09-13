# build/deploy via build/deploy via repo/deployment/scripts/circle-ci/inventcorp-base-image-build-deploy.sh , test deployment/scripts/local/test-inventcorp-base-image-build.sh
# BASE_IMAGE is set based on values in deployment/kubernetes/.env.base-image
ARG  BASE_IMAGE="please_set_BASE_IMAGE_arg"
FROM ${BASE_IMAGE} AS build

MAINTAINER devops@novapulsar.com

# install newrelic repository
RUN echo 'deb http://apt.newrelic.com/debian/ newrelic non-free' > /etc/apt/sources.list.d/newrelic.list && \
    wget -O- https://download.newrelic.com/548C16BF.gpg | apt-key add -

# packages
RUN apt-get update -y && \
    apt-install -y \
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
    ncat \
    newrelic-php5

# install newrelic
ENV NR_INSTALL_SILENT true
RUN bash newrelic-install install

RUN pecl install grpc-1.27.0 && \
    docker-php-ext-enable grpc && \
    pecl install protobuf-3.10.0 && \
    docker-php-ext-enable protobuf

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
RUN apt-install -y xfonts-75dpi xfonts-base && \
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

# this results in a single layer image
FROM scratch
COPY --from=build / /
