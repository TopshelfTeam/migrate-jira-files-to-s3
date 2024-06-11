FROM php:8.2-cli

# install OS libraries and tools
RUN apt-get update && \
    apt-get -y install git libzip-dev libtidy-dev tidy zip

# install necessary php extensions
RUN docker-php-ext-install tidy
RUN docker-php-ext-install zip
# RUN docker-php-ext-install ext-pcntl
# RUN docker-php-ext-install ext-sockets

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# PHP Configuration required for PHP SDK
RUN touch /usr/local/etc/php/conf.d/memory.ini \
    && echo "memory_limit = 2048M;" >> /usr/local/etc/php/conf.d/memory.ini

RUN touch /usr/local/etc/php/conf.d/phar.ini \
    && echo "phar.readonly = Off;" >> /usr/local/etc/php/conf.d/phar.ini

RUN touch /usr/local/etc/php/conf.d/timezone.ini \
    && echo "date.timezone ='America/Los_Angeles'" >> /usr/local/etc/php/conf.d/timezone.ini

# clone the AWS repo for the PHP AWS SDK
RUN cd / && \
    git clone https://github.com/aws/aws-sdk-php.git

# install the PHP AWS SDK
# Note: we ignore platform reqs because compose gags on some random stuff that doesn't matter
# relevant thread:  https://stackoverflow.com/questions/48577465/how-can-i-solve-laravel-horizon-v1-1-0-requires-ext-pcntl-the-requested-ph
RUN cd /aws-sdk-php && \
    composer install --ignore-platform-reqs

RUN cd /aws-sdk-php && make build

# create the folder where we expe
RUN mkdir /script

# set the working directory as /app
WORKDIR /script

