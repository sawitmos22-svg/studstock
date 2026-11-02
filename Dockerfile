FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /app

# The application reads Excel files and connects to Supabase Postgres.
RUN install-php-extensions pdo_pgsql zip

COPY Caddyfile /etc/caddy/Caddyfile
COPY . /app

ENV PORT=80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
