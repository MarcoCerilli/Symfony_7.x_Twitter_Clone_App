# ----------------------------------------------------------------------
# 1. STAGE DI COMPOSER (BUILDER BASE)
# Utilizzato per installare le dipendenze PHP e come base per lo stage assets.
# ----------------------------------------------------------------------
FROM composer:2 AS composer

# Definisce le dipendenze PHP richieste per il progetto Symfony
# Aggiungi qui le estensioni mancanti, se necessario (es. intl, zip, gd)
ARG PHP_EXTENSIONS="pdo_mysql"

# Passa alla directory di lavoro
WORKDIR /app

# Copia solo i file di configurazione per sfruttare la cache di Docker
COPY composer.json composer.lock symfony.lock ./

# Installa le dipendenze PHP, escludendo quelle di sviluppo per la produzione
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader \
    --ignore-platform-reqs

# ----------------------------------------------------------------------
# 2. STAGE ASSETS (NODE BUILDER)
# Utilizzato per installare le dipendenze Node.js e compilare gli asset con Vite.
# ----------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

# Copia i file di configurazione per gli asset
COPY package.json package-lock.json vite.config.js ./
COPY assets ./assets/

# Installa le dipendenze Node.js
RUN npm install

# Copia le dipendenze Composer (perché la build potrebbe usare Webpack Encore/Vite Bridge)
COPY --from=composer /app/vendor /app/vendor

# Compila gli asset per la produzione
RUN npm run build

# ----------------------------------------------------------------------
# 3. STAGE DI PRODUZIONE (IMMAGINE FINALE) - CORRETTO
# ----------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS prod

# Installazione delle estensioni PHP necessarie in produzione
ARG PHP_EXTENSIONS="pdo_mysql"
RUN apk add --no-cache libzip-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install -j$(nproc) zip ${PHP_EXTENSIONS} \
    && docker-php-ext-install pdo_mysql opcache

# Configurazione di base per sicurezza e prestazioni
RUN set -ex; \
    # Abilita Opcache per le prestazioni
    echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini; \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini; \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini; \
    # Aumenta la memoria per i processi PHP (opzionale)
    echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/zz-custom.ini

# Crea l'utente non-root per sicurezza (raccomandato) - ESEGUITO COME ROOT
RUN adduser -D appuser
WORKDIR /var/www/html

# Copia il codice sorgente (eseguito come root)
COPY . .

# Copia le dipendenze Composer dallo stage composer
COPY --from=composer --chown=appuser:appuser /app/vendor /var/www/html/vendor

# Copia gli asset compilati dallo stage assets
COPY --from=assets --chown=appuser:appuser /app/public/build /var/www/html/public/build

# ----------------------------------------------------------------------
# CORREZIONE ERRORE: Gestione dei permessi eseguita come ROOT
# ----------------------------------------------------------------------
# Creiamo le cartelle se non esistono e rendiamo l'utente appuser proprietario.
# Questo risolve "Operation not permitted"
RUN set -ex; \
    mkdir -p var/cache var/log public/uploads public/uploads/avatars; \
    chown -R appuser:appuser var public/uploads; \
    # Optional: Per assicurare i permessi di scrittura per l'owner
    chmod -R u+w var public/uploads;

# CAMBIA UTENTE: Tutti i comandi successivi, incluso l'avvio di php-fpm, saranno eseguiti come appuser
USER appuser

# Espone la porta di PHP-FPM
EXPOSE 9000

# Esecuzione (PHP-FPM)
CMD ["php-fpm"]
