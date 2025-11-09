# PostgreSQL Backup Script

This directory contains:
- a script for automated PostgreSQL database backups
- a Dockerfile for containerizing the backup script and running **it with cron**
- a Dockerfile for containerizing the backup script and running **it manually**

## Directory Structure

- `backup.sh` - Main backup script
- `Dockerfile` - Container configuration for running backups

## Usage with Docker Compose

### Automated Backups

The container by default sets up and runs automated backups every interval using cron via `backup.sh`.
Backups are stored in the following format:
```
/backups/db_YYYYMMDD_HHMMSS.sql.gz
```

### Ad-hoc Manual Backup

To create a one-time backup immediately:

```bash
// Run from ../docker-compose.yml
docker compose run --rm -e MANUAL_BACKUP=1 postgres-backup
```

Command breakdown:
- `docker compose run` - creates a new container based on postgres-backup service
- `--rm` - removes the container after completion to avoid system clutter
- `-e MANUAL_BACKUP=1` - sets environment variable to trigger immediate backup
- `postgres-backup` - service name from docker-compose.yml

## Backup Location

All backups are stored in the `/backups` directory inside the container. Make sure to mount this directory in your docker-compose.yml via `volumes` to persist backups on the host system.

Example docker-compose.yml configuration:

```yaml
services:
  postgres-backup:
    build: ./postgres-backup
    environment:
      - POSTGRES_DB=your_database
      - POSTGRES_USER=your_user
      - POSTGRES_PASSWORD=your_password
      - POSTGRES_HOST=your_host
    volumes:
      - /host/backup/path:/backups
```
