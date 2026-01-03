FROM php:8.0-apache

# 安装 PHP 扩展
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 启用 Apache mod_rewrite
RUN a2enmod rewrite

# 复制应用代码
COPY . /var/www/html/

# 设置 DocumentRoot 为 public 目录
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 配置 php.ini
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 20M" >> /usr/local/etc/php/php.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/php.ini

EXPOSE 80

CMD ["apache2-foreground"]