FROM php:8.1-cli

# ----------------------------------------------------------------------------------------------------------------------
# Basic arguments and variables
# ----------------------------------------------------------------------------------------------------------------------
ARG DOCKER_USER
ARG DOCKER_USER_ID
ARG DOCKER_GROUP_ID
# Versions
ENV COMPOSER_VERSION=2.4.4 \
    PECL_MONGODB_VERSION=1.15.0 \
    PECL_PCOV_VERSION=1.0.11 \
    PECL_REDIS_VERSION=5.3.7 \
    PECL_XDEBUG_VERSION=3.1.6 \
    PHP_SECURITY_CHECKER_VERSION=2.0.5 \
    REDIS_VERSION=6.2.7
# Composer
ENV COMPOSER_HOME="/composer" \
    PATH="/composer/vendor/bin:$PATH"

# ----------------------------------------------------------------------------------------------------------------------
# Initialization with PHP extensions
# ----------------------------------------------------------------------------------------------------------------------
RUN apt-get update && \
    apt-get install -y \
        ca-certificates \
        curl \
        gcc \
        libssl-dev \
        libzip-dev \
        wget \
        zip && \
    docker-php-ext-configure opcache && \
    docker-php-ext-configure pdo_mysql && \
    docker-php-ext-configure zip && \
    docker-php-ext-install -j$(nproc) \
        opcache \
        pdo_mysql \
        zip && \
    yes '' | pecl install mongodb-${PECL_MONGODB_VERSION} && \
    yes '' | pecl install pcov-${PECL_PCOV_VERSION} && \
    yes '' | pecl install redis-${PECL_REDIS_VERSION} && \
    yes '' | pecl install xdebug-${PECL_XDEBUG_VERSION} && \
    docker-php-ext-enable \
        mongodb \
        pcov \
        redis \
        xdebug && \
    pecl clear-cache && \
    rm -rf /tmp/pear && \
    apt-get clean && \
    rm -r /var/lib/apt/lists/*

# ----------------------------------------------------------------------------------------------------------------------
# Php Security Checker binary package setup
# ----------------------------------------------------------------------------------------------------------------------
RUN wget -q \
        https://github.com/fabpot/local-php-security-checker/releases/download/v${PHP_SECURITY_CHECKER_VERSION}/local-php-security-checker_${PHP_SECURITY_CHECKER_VERSION}_linux_amd64 \
        -O /usr/local/bin/local-php-security-checker && \
    chmod +x /usr/local/bin/local-php-security-checker

# ----------------------------------------------------------------------------------------------------------------------
# Composer
# ----------------------------------------------------------------------------------------------------------------------
RUN curl -sS https://getcomposer.org/installer | \
    php -- \
        --install-dir=/usr/local/bin \
        --filename=composer \
        --version=${COMPOSER_VERSION}

# ----------------------------------------------------------------------------------------------------------------------
# Redis-cli
# ----------------------------------------------------------------------------------------------------------------------
RUN wget https://download.redis.io/releases/redis-${REDIS_VERSION}.tar.gz && \
    tar xvzf redis-${REDIS_VERSION}.tar.gz && \
    rm -f redis-${REDIS_VERSION}.tar.gz && \
    cd redis-${REDIS_VERSION}/deps && \
    make && \
    cd .. && \
    make && \
    cp src/redis-cli /usr/bin/ && \
    cd .. && \
    rm -rf redis-${REDIS_VERSION}

# ----------------------------------------------------------------------------------------------------------------------
# User
# ----------------------------------------------------------------------------------------------------------------------
RUN addgroup \
        --gid ${DOCKER_GROUP_ID} \
        user && \
    useradd \
        --uid ${DOCKER_USER_ID} \
        --gid user \
        --home-dir /home/user \
        --create-home \
        --shell /bin/bash \
        user && \
    sed -i 's/^#alias l/alias l/g' /home/user/.bashrc && \
    mkdir -p \
        ${COMPOSER_HOME}/cache \
        /usr/local/log \
        /var/log/php \
        /var/run/php && \
    chown user:user -R \
        ${COMPOSER_HOME} \
        /var/log/php \
        /var/run/php \
        /var/www/html

# ----------------------------------------------------------------------------------------------------------------------
# Run Configuration
# ----------------------------------------------------------------------------------------------------------------------
COPY --chown=user:user ./docker/app/usr /usr
WORKDIR /var/www/html
USER user
