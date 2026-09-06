# ใช้ PHP 8.2 CLI เป็นฐาน
FROM php:8.2-cli

# ติดตั้ง dependencies ทั้งหมด: SQLite, Postgres, curl, Node.js
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libpq-dev \
    libcurl4-openssl-dev \
    curl \
    ca-certificates \
    && docker-php-ext-install pdo_sqlite pdo_pgsql pgsql curl \
    && rm -rf /var/lib/apt/lists/*

# ติดตั้ง Node.js 20 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

# ติดตั้ง Node.js packages (got-scraping สำหรับ TW Bridge)
RUN npm install

# สร้างโฟลเดอร์ data สำหรับ SQLite
RUN mkdir -p /app/data && chmod -R 777 /app/data

EXPOSE 10000

# รัน Node.js Bridge ใน Background แล้วรัน PHP Server
CMD ["sh", "-c", "node tw_bridge.mjs & php -S 0.0.0.0:${PORT:-10000} router.php"]
