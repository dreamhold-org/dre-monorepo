#!/bin/bash

# The script provides functionality to:
# - Create database backups in compressed format
# - Set up automated backups using cron
# - Store backups with timestamps in gzip format
# - Environment-specific backups (dev/prod)
set -e

BACKUP_DIR="/backups"
ENVIRONMENT="${ENVIRONMENT:-dev}"
mkdir -p "$BACKUP_DIR"

echo "=== Environment: $ENVIRONMENT ==="
echo "=== Database: $MARIADB_DATABASE on $MARIADB_HOST ==="
echo "=== Backup directory: $BACKUP_DIR ==="

if [ "$MANUAL_BACKUP" = "1" ]; then
    echo "=== Создаём ручной бэкап для окружения $ENVIRONMENT ==="
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    MYSQL_PWD="$MARIADB_PASSWORD" mysqldump -h "$MARIADB_HOST" -u "$MARIADB_USER" "$MARIADB_DATABASE" | gzip > "$BACKUP_DIR/db_$TIMESTAMP.sql.gz"
    echo "Бэкап создан: $BACKUP_DIR/db_$TIMESTAMP.sql.gz"
    echo "Размер: $(du -h $BACKUP_DIR/db_$TIMESTAMP.sql.gz | cut -f1)"
else
    echo "=== Настройка автоматического бэкапа через cron для окружения $ENVIRONMENT ==="

    # Создаём cron-задание в /etc/cron.d/mysql-backup (каждый час)
    echo "0 * * * * root MYSQL_PWD=$MARIADB_PASSWORD mysqldump -h $MARIADB_HOST -u $MARIADB_USER $MARIADB_DATABASE | gzip > $BACKUP_DIR/db_\$(date +\%Y\%m\%d_\%H\%M\%S).sql.gz && echo \"\$(date): Backup completed for $ENVIRONMENT environment\" >> /var/log/cron.log" > /etc/cron.d/mysql-backup
    chmod 0644 /etc/cron.d/mysql-backup
    crontab /etc/cron.d/mysql-backup

    echo "Запускаем cron..."
    echo "Бэкапы будут создаваться каждый час в $BACKUP_DIR"
    cron -f
fi
