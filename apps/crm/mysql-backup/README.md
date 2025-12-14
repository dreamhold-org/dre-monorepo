# MariaDB/MySQL Backup Script

This directory contains:
- a script for automated MariaDB database backups
- a Dockerfile for containerizing the backup script and running **it with cron**
- a Dockerfile for containerizing the backup script and running **it manually**

## Directory Structure

- `backup.sh` - Main backup script
- `Dockerfile` - Container configuration for running backups

## Usage with Docker Compose

### Automated Backups

The container by default sets up and runs automated backups every hour using cron via `backup.sh`.
Backups are stored in the following format:
```
/backups/db_YYYYMMDD_HHMMSS.sql.gz
```

### Ad-hoc Manual Backup

To create a one-time backup immediately:

```bash
# Run from ../docker-compose.yml
docker compose run --rm -e MANUAL_BACKUP=1 mysql-backup
```

Command breakdown:
- `docker compose run` - creates a new container based on mysql-backup service
- `--rm` - removes the container after completion to avoid system clutter
- `-e MANUAL_BACKUP=1` - sets environment variable to trigger immediate backup
- `mysql-backup` - service name from docker-compose.yml

## Backup Location

All backups are stored in the `/backups` directory inside the container. Make sure to mount this directory in your docker-compose.yml via `volumes` to persist backups on the host system.

Example docker-compose.yml configuration:

```yaml
services:
  mysql-backup:
    build: ./mysql-backup
    environment:
      - MARIADB_DATABASE=your_database
      - MARIADB_USER=your_user
      - MARIADB_PASSWORD=your_password
      - MARIADB_HOST=your_host
    volumes:
      - /host/backup/path:/backups
```

## Restoring from Backup

To restore a backup:

```bash
# Extract and restore
gunzip -c dev-backups/db_YYYYMMDD_HHMMSS.sql.gz | \
  docker compose exec -T mariadb mysql -u $MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE
```
