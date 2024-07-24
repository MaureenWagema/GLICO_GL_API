FROM php:8.2.4 as php

RUN apt-get update && \
    apt-get install -y wget apt-transport-https gnupg && \
    wget -qO- https://packages.microsoft.com/keys/microsoft.asc | apt-key add - && \
    echo "deb [arch=amd64] https://packages.microsoft.com/debian/11/prod bullseye main" > /etc/apt/sources.list.d/mssql-release.list && \
    apt-get update && \
    ACCEPT_EULA=Y apt-get install -y msodbcsql17 unixodbc-dev mssql-tools && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

RUN pecl install sqlsrv pdo_sqlsrv && \
    docker-php-ext-enable sqlsrv pdo_sqlsrv

RUN apt-get update && \
    apt-get install -y unzip libpq-dev libcurl4-gnutls-dev && \
    docker-php-ext-install pdo bcmath

RUN pecl install -o -f redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

WORKDIR /var/www
COPY . .

COPY --from=composer:2.3.5 /usr/bin/composer /usr/bin/composer

ENV PORT=8000

COPY docker/entrypoint.sh /docker/entrypoint.sh

RUN chmod +x ./docker/entrypoint.sh 
ENTRYPOINT [ "docker/entrypoint.sh" ]