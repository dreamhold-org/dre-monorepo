#!/bin/bash

# The script provides functionality to:
# - Create database backups in compressed format
# - Set up automated backups using cron
# - Store backups with timestamps in gzip format
set -e

BACKUP_DIR="/backups"
mkdir -p "$BACKUP_DIR"

if [ "$MANUAL_BACKUP" = "1" ]; then
    echo "=== Создаём ручной бэкап ==="
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    PGPASSWORD="$POSTGRES_PASSWORD" pg_dump -h "$POSTGRES_HOST" -U "$POSTGRES_USER" -d "$POSTGRES_DB" | gzip > "$BACKUP_DIR/db_$TIMESTAMP.sql.gz"
    echo "Бэкап создан: $BACKUP_DIR/db_$TIMESTAMP.sql.gz"
else
    echo "=== Настройка автоматического бэкапа через cron ==="

    # Создаём cron-задание в /etc/cron.d/postgres-backup
    echo "* */2 * * * root PGPASSWORD=$POSTGRES_PASSWORD pg_dump -h $POSTGRES_HOST -U $POSTGRES_USER -d $POSTGRES_DB | gzip > $BACKUP_DIR/db_\$(date +\%Y\%m\%d_\%H\%M\%S).sql.gz" > /etc/cron.d/postgres-backup
    chmod 0644 /etc/cron.d/postgres-backup
    crontab /etc/cron.d/postgres-backup

    echo "Запускаем cron..."
    cron -f
fi
