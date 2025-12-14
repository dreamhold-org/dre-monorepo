# EspoCRM Deployment

Production-ready Docker Compose deployment of EspoCRM with MariaDB, Traefik reverse proxy, automated backups, and real-time WebSocket support.

**Environment Support:** This deployment supports separate development and production environments with isolated databases and backups.

## Overview

This deployment provides a complete EspoCRM installation with:
- **EspoCRM** - Open-source CRM platform
- **MariaDB** - Relational database with health checks
- **Traefik** - Reverse proxy with automatic HTTPS (Let's Encrypt)
- **Automated Backups** - Database backups every hour
- **WebSocket Support** - Real-time notifications and updates
- **Custom Extensions** - Persistent customization directory
- **Environment Isolation** - Separate dev and prod databases, volumes, and backups

## Architecture

### Services

| Service | Purpose | Image | Restart Policy |
|---------|---------|-------|----------------|
| `traefik` | Reverse proxy, SSL termination | `traefik:latest` | always |
| `mariadb` | Database server | `mariadb:latest` | always |
| `mysql-backup` | Automated database backups | Custom build | unless-stopped |
| `espocrm` | Main CRM application | `espocrm/espocrm:latest` | always |
| `espocrm-daemon` | Background job processor | `espocrm/espocrm:latest` | always |
| `espocrm-websocket` | Real-time WebSocket server | `espocrm/espocrm:latest` | always |
| `whoami` | Debug service (local only) | `traefik/whoami` | - |

### Volumes

**Environment-Specific Volumes:**
- `mariadb_data_dev` / `mariadb_data_prod` - Persistent MariaDB database storage per environment
- `espocrm_data_dev` / `espocrm_data_prod` - Persistent EspoCRM application files per environment

**Shared Volumes:**
- `./custom` - Custom EspoCRM extensions and modules (mounted, shared)
- `./letsencrypt` - SSL certificates from Let's Encrypt (mounted, shared)
- `./dev-backups` - Development database backup files (mounted)
- `./prod-backups` - Production database backup files (mounted)

### Network Architecture

```
Internet → Traefik (ports 80/443)
              ↓
         EspoCRM Web
              ↓
    ┌────────┴────────┐
    ↓                 ↓
 MariaDB       EspoCRM Daemon
    ↑                 ↓
    └──── Backups  WebSocket
```

## Prerequisites

- **Docker** (20.10+)
- **Docker Compose** (v2.0+)
- **Domain name** with DNS A record pointing to your server
- **Email address** for Let's Encrypt certificate notifications
- **Open ports**: 80 (HTTP), 443 (HTTPS), 8080 (Traefik dashboard - optional)

## Environment Isolation

This deployment supports running **separate development and production environments simultaneously** on the same server. Each environment has:

- **Isolated Database**: `mariadb-dev` and `mariadb-prod` containers with separate data volumes
- **Isolated Application**: `espocrm-dev` and `espocrm-prod` containers with separate data volumes
- **Isolated Backups**: `dev-backups/` and `prod-backups/` directories
- **Separate Container Names**: All services are suffixed with environment name

### Running Multiple Environments

**Development environment:**
```bash
docker compose --env-file .env up -d
# Uses ENVIRONMENT=dev
# Creates: mariadb-dev, espocrm-dev, mysql-backup-dev, etc.
# Backups go to: ./dev-backups/
```

**Production environment:**
```bash
docker compose --env-file .env.prod up -d
# Uses ENVIRONMENT=prod
# Creates: mariadb-prod, espocrm-prod, mysql-backup-prod, etc.
# Backups go to: ./prod-backups/
```

**Both environments simultaneously:**
```bash
# Start dev
docker compose --env-file .env up -d

# Start prod
docker compose --env-file .env.prod up -d

# Both are now running with isolated data!
```

**Important:** Each environment requires a different `ESPOCRM_SITE_URL` domain to avoid Traefik routing conflicts.

## Deployment Scheme

### Step 1: Environment Configuration

Choose your environment and create the corresponding `.env` file:

#### For Local Development

Copy and configure `.env.example`:

```bash
cp .env.example .env
```

Edit `.env` with your settings:

```env
# Environment (dev or prod)
ENVIRONMENT=dev

# Database Configuration (MariaDB)
MARIADB_ROOT_PASSWORD=root_localpass
MARIADB_DATABASE=espocrm
MARIADB_USER=espocrm
MARIADB_PASSWORD=localpass

# EspoCRM Admin Credentials
ESPOCRM_ADMIN_USERNAME=admin
ESPOCRM_ADMIN_PASSWORD=adminpass

# Domain Configuration
ESPOCRM_SITE_URL=crm-dev.starosten.com

# SSL/HTTPS Configuration
YOUR_EMAIL=maksim.s@dreamhold.org

# For local development without real SSL
TRAFFIC_TLS_COMMANDS=
```

#### For Production Deployment

Copy and configure `.env.prod.example`:

```bash
cp .env.prod.example .env.prod
```

Edit `.env.prod` with your production settings:

```env
# Environment (dev or prod)
ENVIRONMENT=prod

# Database Configuration (MariaDB) - USE STRONG PASSWORDS
MARIADB_ROOT_PASSWORD=YOUR_STRONG_ROOT_PASSWORD
MARIADB_DATABASE=espocrm
MARIADB_USER=espocrm
MARIADB_PASSWORD=YOUR_STRONG_PASSWORD_HERE

# EspoCRM Admin Credentials - USE STRONG PASSWORDS
ESPOCRM_ADMIN_USERNAME=admin
ESPOCRM_ADMIN_PASSWORD=YOUR_STRONG_ADMIN_PASSWORD

# Domain Configuration - YOUR ACTUAL DOMAIN
ESPOCRM_SITE_URL=crm.starosten.com

# SSL/HTTPS Configuration
# Let's Encrypt Email - VALID EMAIL FOR CERTIFICATE EXPIRY NOTICES
YOUR_EMAIL=maksim.s@dreamhold.org

# Leave empty for automatic Let's Encrypt
TRAFFIC_TLS_COMMANDS=
```

**Important:** Use the `.env.prod` file for production by either:
- Renaming it to `.env`
- Specifying it explicitly: `docker compose --env-file .env.prod up -d`

### Step 2: DNS Configuration

Before deploying, ensure your domain points to your server:

```bash
# Test DNS resolution for production
dig +short crm.starosten.com

# Test DNS resolution for development
dig +short crm-dev.starosten.com

# Both should return: 159.198.64.202
```

If deploying locally for testing, you can add to `/etc/hosts`:
```
127.0.0.1 crm-dev.starosten.com
127.0.0.1 crm.starosten.com
```

### Step 3: Create Required Directories

```bash
# Create directories for persistent data
mkdir -p dev-backups prod-backups letsencrypt custom
```

**Note:** Backup directories are environment-specific:
- `dev-backups/` - Stores development database backups
- `prod-backups/` - Stores production database backups

### Step 4: Start the Stack

```bash
# Start all services
docker compose up -d

# Or with specific env file
docker compose --env-file .env.prod up -d

# View logs
docker compose logs -f

# Check service status
docker compose ps
```

### Step 5: Verify Deployment

1. **Check all containers are running:**
   ```bash
   docker compose ps
   ```
   All services should show "Up" status.

2. **Wait for MariaDB health check:**
   ```bash
   docker compose logs mariadb
   ```
   Look for: "ready for connections"

3. **Access EspoCRM:**
   - Development: `https://crm-dev.starosten.com`
   - Production: `https://crm.starosten.com`
   - You may need to wait 1-2 minutes for initial setup

4. **Login with admin credentials:**
   - Username: From `ESPOCRM_ADMIN_USERNAME`
   - Password: From `ESPOCRM_ADMIN_PASSWORD`

### Step 6: Verify SSL Certificate

Check that Let's Encrypt certificate was issued:

```bash
# View Traefik logs
docker compose logs traefik | grep -i certificate

# Check certificate file
ls -lh letsencrypt/acme.json
```

The `acme.json` file should contain your certificates (appears as binary data).

### Step 7: Configure Firewall (Production)

```bash
# Allow HTTP, HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Optional: Allow Traefik dashboard (secure it first!)
# sudo ufw allow 8080/tcp

# Enable firewall
sudo ufw enable
```

## Environment Configuration

### Database Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `MARIADB_ROOT_PASSWORD` | MariaDB root password | Strong password |
| `MARIADB_DATABASE` | Database name | `espocrm` |
| `MARIADB_USER` | MariaDB username | `espocrm` |
| `MARIADB_PASSWORD` | MariaDB password | Strong password |

### EspoCRM Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `ESPOCRM_ADMIN_USERNAME` | Initial admin username | `admin` |
| `ESPOCRM_ADMIN_PASSWORD` | Initial admin password | Strong password |
| `ESPOCRM_SITE_URL` | Full domain for CRM | `crm.starosten.com` or `crm-dev.starosten.com` |

### Traefik Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `YOUR_EMAIL` | Email for Let's Encrypt | `maksim.s@dreamhold.org` |
| `TRAFFIC_TLS_COMMANDS` | Optional TLS override | Leave empty for ACME |

### Environment Variables

| Variable | Description | Values |
|----------|-------------|--------|
| `ENVIRONMENT` | Environment name for isolation | `dev` or `prod` |

### Backup Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `MANUAL_BACKUP` | Trigger manual backup | `0` (automated) |

## Backup & Restore

### Automated Backups

The `mysql-backup` service automatically backs up the database every hour for each environment.

**Backup locations (environment-specific):**
- Development: `./dev-backups/`
- Production: `./prod-backups/`

**Backup format:** `db_YYYYMMDD_HHMMSS.sql.gz`

**View backup logs:**
```bash
# Development
docker compose --env-file .env logs mysql-backup-dev

# Production
docker compose --env-file .env.prod logs mysql-backup-prod
```

**List backups:**
```bash
# Development backups
ls -lh dev-backups/

# Production backups
ls -lh prod-backups/
```

### Manual Backup

Create an immediate backup before maintenance:

**Development environment:**
```bash
docker compose --env-file .env run --rm -e MANUAL_BACKUP=1 mysql-backup
```

**Production environment:**
```bash
docker compose --env-file .env.prod run --rm -e MANUAL_BACKUP=1 mysql-backup
```

Backups are created in the environment-specific directories (`dev-backups/` or `prod-backups/`).

### Restore from Backup

**Development environment:**

1. **Stop EspoCRM services:**
   ```bash
   docker compose --env-file .env stop espocrm-dev espocrm-daemon-dev espocrm-websocket-dev
   ```

2. **Extract and restore backup:**
   ```bash
   # Find your backup file
   ls dev-backups/

   # Restore (replace BACKUP_FILE with your file)
   gunzip -c dev-backups/BACKUP_FILE.sql.gz | \
     docker compose --env-file .env exec -T mariadb-dev mysql -u $MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE
   ```

3. **Restart services:**
   ```bash
   docker compose --env-file .env start espocrm-dev espocrm-daemon-dev espocrm-websocket-dev
   ```

**Production environment:**

1. **Stop EspoCRM services:**
   ```bash
   docker compose --env-file .env.prod stop espocrm-prod espocrm-daemon-prod espocrm-websocket-prod
   ```

2. **Extract and restore backup:**
   ```bash
   # Find your backup file
   ls prod-backups/

   # Restore (replace BACKUP_FILE with your file)
   gunzip -c prod-backups/BACKUP_FILE.sql.gz | \
     docker compose --env-file .env.prod exec -T mariadb-prod mysql -u $MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE
   ```

3. **Restart services:**
   ```bash
   docker compose --env-file .env.prod start espocrm-prod espocrm-daemon-prod espocrm-websocket-prod
   ```

### Backup Retention

Backups are not automatically deleted. Implement a cleanup strategy for each environment:

```bash
# Keep last 30 days of development backups (run via cron)
find dev-backups/ -name "db_*.sql.gz" -mtime +30 -delete

# Keep last 30 days of production backups (run via cron)
find prod-backups/ -name "db_*.sql.gz" -mtime +30 -delete
```

## Customization

### Custom Extensions

Place custom EspoCRM extensions in the `./custom` directory:

```
custom/
└── Espo/
    ├── Custom/        # Custom configurations
    │   ├── Controllers/
    │   ├── Entities/
    │   ├── Repositories/
    │   └── Services/
    └── Modules/       # Custom modules
        └── YourModule/
```

Changes persist across container restarts and updates.

**After adding customizations:**

1. Clear EspoCRM cache:
   ```bash
   docker compose exec espocrm rm -rf data/cache/*
   ```

2. Rebuild from EspoCRM admin panel:
   Administration → Rebuild

### Mounting Additional Volumes

Edit `docker-compose.yml` to add more persistent directories:

```yaml
espocrm:
  volumes:
    - ./custom:/var/www/html/custom
    - ./your-directory:/var/www/html/your-path
```

## Maintenance

### View Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f espocrm
docker compose logs -f mariadb
docker compose logs -f traefik

# Last 100 lines
docker compose logs --tail=100 espocrm
```

### Restart Services

```bash
# Restart single service
docker compose restart espocrm

# Restart all services
docker compose restart

# Full restart (stop + start)
docker compose down && docker compose up -d
```

### Update EspoCRM

```bash
# Pull latest images
docker compose pull

# Recreate containers with new images
docker compose up -d

# View update logs
docker compose logs -f espocrm
```

**Note:** Always backup before updating!

### Update Traefik

```bash
docker compose pull traefik
docker compose up -d traefik
```

### Database Maintenance

```bash
# Connect to MariaDB
docker compose exec mariadb mysql -u $MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE

# Optimize tables
docker compose exec mariadb mysqlcheck -u $MARIADB_USER -p$MARIADB_PASSWORD --optimize $MARIADB_DATABASE

# Check database size
docker compose exec mariadb mysql -u $MARIADB_USER -p$MARIADB_PASSWORD -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables GROUP BY table_schema;"
```

### Clear EspoCRM Cache

```bash
# Clear cache
docker compose exec espocrm rm -rf data/cache/*

# Rebuild
docker compose exec espocrm php command.php rebuild
```

## Troubleshooting

### EspoCRM Not Accessible

**Check containers are running:**
```bash
docker compose ps
```

**Check EspoCRM logs:**
```bash
docker compose logs espocrm
```

**Verify Traefik routing:**
```bash
docker compose logs traefik | grep espocrm
```

**Common issues:**
- MariaDB not healthy: Wait for health check to pass
- DNS not pointing to server: Check DNS with `dig crm.starosten.com` or `dig crm-dev.starosten.com`
- Firewall blocking ports: Check with `sudo ufw status`

### SSL Certificate Issues

**Certificate not issued:**
```bash
# Check Traefik logs
docker compose logs traefik | grep -i acme

# Common causes:
# - Port 80 not accessible from internet
# - Domain DNS not pointing to server
# - Email address invalid
```

**Force certificate renewal:**
```bash
# Stop Traefik
docker compose stop traefik

# Remove old certificates
rm letsencrypt/acme.json

# Restart Traefik
docker compose up -d traefik

# Monitor certificate generation
docker compose logs -f traefik
```

### Database Connection Issues

**Check MariaDB health:**
```bash
docker compose exec mariadb mysqladmin ping -u $MARIADB_USER -p$MARIADB_PASSWORD
```

**Verify credentials:**
```bash
# Check environment variables
docker compose exec espocrm env | grep DATABASE
```

**Reset database connection:**
```bash
docker compose restart mariadb espocrm
```

### Backup Service Not Running

**Check backup logs:**
```bash
docker compose logs mysql-backup
```

**Test manual backup:**
```bash
docker compose run --rm -e MANUAL_BACKUP=1 mysql-backup
```

**Verify backup directory permissions:**
```bash
ls -la dev-backups/
# Should be writable
```

### WebSocket Not Working

**Check WebSocket service:**
```bash
docker compose logs espocrm-websocket
```

**Verify WebSocket configuration:**
```bash
docker compose exec espocrm-websocket env | grep WEBSOCKET
```

**Restart WebSocket:**
```bash
docker compose restart espocrm-websocket
```

### Out of Disk Space

**Check disk usage:**
```bash
df -h

# Check Docker disk usage
docker system df
```

**Clean up Docker:**
```bash
# Remove unused containers, networks, images
docker system prune -a

# Remove old volumes (CAREFUL!)
docker volume prune
```

**Compress old backups:**
```bash
# Already compressed with gzip
# Consider moving old backups to archive storage
```

## Security Considerations

### 1. Strong Passwords

Always use strong passwords for:
- `MARIADB_PASSWORD` - Database access
- `MARIADB_ROOT_PASSWORD` - Database root access
- `ESPOCRM_ADMIN_PASSWORD` - CRM admin access

Generate strong passwords:
```bash
openssl rand -base64 32
```

### 2. Traefik Dashboard

The Traefik dashboard (port 8080) is exposed with `--api.insecure=true`. For production:

**Option A: Disable dashboard**
```yaml
# Remove from docker-compose.yml:
- --api.insecure=true
# Remove port mapping:
- "8080:8080"
```

**Option B: Secure with authentication**
```yaml
- --api.dashboard=true
- --api.insecure=false
# Add authentication middleware
```

### 3. Firewall Configuration

Only expose necessary ports:
```bash
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw deny 8080/tcp   # Block Traefik dashboard
```

### 4. Regular Updates

Keep software updated:
```bash
# Update Docker images regularly
docker compose pull
docker compose up -d
```

### 5. Environment Files

Protect `.env` files:
```bash
chmod 600 .env .env.prod
```

Never commit to Git (already in `.gitignore`).

### 6. SSL Certificate Monitoring

Monitor certificate expiry:
```bash
# Let's Encrypt certs expire in 90 days
# Traefik auto-renews at 30 days

# Check certificate dates
docker compose logs traefik | grep -i certificate
```

### 7. Database Access

MariaDB is not exposed externally by default. To access remotely:
- Use SSH tunnel: `ssh -L 3306:localhost:3306 user@server`
- Never expose MariaDB port directly to internet

### 8. Backup Security

Backups contain sensitive data:
```bash
# Restrict backup directory
chmod 700 dev-backups/ prod-backups/

# Consider encrypting backups
gpg --encrypt dev-backups/db_YYYYMMDD_HHMMSS.sql.gz
```

## Additional Resources

- [EspoCRM Documentation](https://docs.espocrm.com/)
- [EspoCRM Docker Hub](https://hub.docker.com/r/espocrm/espocrm)
- [Traefik Documentation](https://doc.traefik.io/traefik/)
- [MariaDB Documentation](https://mariadb.com/kb/en/documentation/)

## Support

For issues specific to this deployment:
- Check troubleshooting section above
- Review container logs: `docker compose logs [service]`
- Verify environment configuration

For EspoCRM issues:
- [EspoCRM Community Forum](https://forum.espocrm.com/)
- [EspoCRM GitHub Issues](https://github.com/espocrm/espocrm/issues)
