# 1. Sử dụng PHP 8.2 kèm máy chủ Apache
FROM php:8.2-apache

# 2. Cài extension pdo_mysql và mysqli để PHP kết nối được với MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 3. Kích hoạt mod_rewrite để phục vụ định tuyến REST API
RUN a2enmod rewrite

# 4. Thư mục chạy code web mặc định của Apache
WORKDIR /var/www/html

# 5. Sao chép toàn bộ mã nguồn của nhóm vào web root
COPY . /var/www/html/

# 6. Cấp quyền truy cập cho Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 7. Khai báo cổng 80 nội bộ container
EXPOSE 80

# 8. Lệnh chạy máy chủ Apache ở chế độ nền trước
CMD ["apache2-foreground"]