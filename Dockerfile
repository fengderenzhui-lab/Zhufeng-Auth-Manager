# 逐风授权码管理平台 - PHP-FPM 镜像
# PHP 8.2 + sodium + pdo_mysql + redis

FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
        git \
        unzip \
        curl \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
        bcmath \
        opcache \
    && php -m | grep -qi sodium \
    && echo "sodium: OK (PHP 8.2 内置)"

# opcache 生产配置
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 构建期仅安装生产依赖（等保 ZF-2026-004：携带 composer.lock 锁版安装，保证镜像依赖可复现）
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    && composer dump-autoload --no-dev --optimize \
    && composer audit --locked --no-interaction || { echo "[build] composer audit 发现已知漏洞，构建终止（可临时以 --no-audit 放行排查）" >&2; exit 1; }

COPY . .

# 启动引导脚本（密钥链初始化 -> migrate --force -> zf:init-admin -> php-fpm，见 docker/entrypoint.sh）
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache

EXPOSE 9000

CMD ["/usr/local/bin/entrypoint.sh"]
