FROM debian:jessie-slim@sha256:5d269f1de9ab8e7fc7ccbb00d2a1bc844626d5ffd236e7278110e5fa93209384

ENV PHP_VERSION=5.3.29
ENV PHP_TARBALL_SHA256=c4e1cf6972b2a9c7f2777a18497d83bf713cdbecabb65d3ff62ba441aebb0091

RUN set -eux; \
    printf 'deb http://archive.debian.org/debian jessie main\n' > /etc/apt/sources.list; \
    printf 'Acquire::Check-Valid-Until "false";\nAcquire::AllowInsecureRepositories "true";\nAPT::Get::AllowUnauthenticated "true";\n' > /etc/apt/apt.conf.d/99archive; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --allow-unauthenticated --no-install-recommends \
        ca-certificates apt-transport-https; \
    printf 'deb https://archive.debian.org/debian jessie main\ndeb https://archive.debian.org/debian-security jessie/updates main\n' > /etc/apt/sources.list; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --allow-unauthenticated --no-install-recommends \
        curl bzip2 build-essential libxml2-dev libssl-dev \
        libcurl4-openssl-dev libjpeg62-turbo-dev libpng12-dev libxpm-dev \
        libfreetype6-dev zlib1g-dev; \
    curl -fsSL -o /tmp/php.tar.bz2 "https://museum.php.net/php5/php-${PHP_VERSION}.tar.bz2"; \
    echo "${PHP_TARBALL_SHA256}  /tmp/php.tar.bz2" | sha256sum -c -; \
    cd /tmp; \
    tar -xjf php.tar.bz2; \
    cd "php-${PHP_VERSION}"; \
    ./configure --disable-all --enable-cli --with-config-file-path=/usr/local/etc/php; \
    make -j"$(nproc)"; \
    make install; \
    rm -rf /tmp/php.tar.bz2 "/tmp/php-${PHP_VERSION}"; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app
