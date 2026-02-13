FROM php:8.2-apache

RUN a2enmod rewrite headers ssl

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libmariadb-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY ./docker_apache/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /etc/apache2/ssl

RUN openssl req -x509 -nodes -days 365 \
    -newkey rsa:2048 \
    -keyout /etc/apache2/ssl/apache.key \
    -out /etc/apache2/ssl/apache.crt \
    -subj "/C=NL/ST=NL/L=Amsterdam/O=Wokki20/OU=Dev/CN=localhost"

COPY ./docker_apache/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf
RUN a2ensite default-ssl.conf
