# Используем PostgreSQL 18 (Debian-based)
FROM postgres:18

# Устанавливаем cron и утилиты
RUN apt-get update && \
    apt-get install -y cron bash gzip && \
    rm -rf /var/lib/apt/lists/*

# Копируем скрипт бэкапа
COPY backup.sh /usr/local/bin/backup.sh
RUN chmod +x /usr/local/bin/backup.sh

# Создаём лог для cron
RUN touch /var/log/cron.log

# ENTRYPOINT запускает наш скрипт
ENTRYPOINT ["/usr/local/bin/backup.sh"]

# Логи cron на экран (если используется автоматический бэкап)
CMD tail -f /var/log/cron.log
