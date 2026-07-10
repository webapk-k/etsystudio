# Etsy Listing Studio — runs on any Docker host (Render / Railway / Fly.io ...)
# Official PHP image already ships with the curl, mbstring & openssl extensions enabled,
# which this app needs for its outbound API calls.
FROM php:8.2-cli

WORKDIR /app
COPY . /app

# Use the environment-based config, and make the data folder writable (users / history / usage).
RUN cp /app/config.deploy.php /app/config.php \
 && mkdir -p /app/data && chmod -R 777 /app/data

# Hosts inject the port via $PORT (Render uses 10000). Default to 8080 for local `docker run`.
ENV PORT=8080
EXPOSE 8080

# The app is designed for PHP's built-in server (curl works there).
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t /app"]
