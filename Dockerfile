FROM php:8.2-cli

# Install SQLite3 PDO extension
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

# Ensure data directory exists and has write permissions for SQLite
RUN mkdir -p /app/data && chmod -R 777 /app/data

EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} router.php"]
