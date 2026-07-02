FROM richarvey/nginx-php-fpm:latest

COPY . .

# Image config
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1

# Laravel config
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

# Allow composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install composer dependencies, cache config/routes, link storage, run migrations
RUN composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

# ── Fix for a known bug in richarvey/nginx-php-fpm ──────────────────────────
# The image's own start.sh is *supposed* to rewrite the default nginx vhost
# from `try_files $uri $uri/ =404;` to `try_files $uri $uri/ /index.php?$args;`
# so that any URL that isn't a real file (e.g. every Laravel route) falls
# through to index.php. That rewrite is a plain `sed` on an exact string, and
# on current image versions the string no longer matches byte-for-byte, so
# the sed silently does nothing. Result: "/" works (it hits index.php via the
# `index` directive) but every other route (e.g. /api/ai/analyze) 404s
# straight from nginx, before Laravel ever sees the request.
#
# We patch it ourselves at build time with a whitespace-tolerant regex, for
# both the HTTP and SSL vhost templates, and we FAIL THE BUILD if the patch
# didn't apply — so this breaks loudly at deploy time instead of silently
# 404ing in production.
RUN sed -i -E 's/try_files[[:space:]]+\$uri[[:space:]]+\$uri\/[[:space:]]+=404;/try_files $uri $uri\/ \/index.php?$args;/' \
        /etc/nginx/sites-available/default.conf \
        /etc/nginx/sites-available/default-ssl.conf \
    && grep -q 'index.php?\$args' /etc/nginx/sites-available/default.conf \
    && echo "✅ nginx try_files patched correctly" \
    || (echo "❌ nginx try_files patch FAILED — check the vhost template in this image version" && exit 1)

CMD ["/start.sh"]