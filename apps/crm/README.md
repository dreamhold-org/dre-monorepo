# EspoCRM Deployment

Production-ready Docker Compose deployment of EspoCRM with PostgreSQL, Traefik reverse proxy, automated backups, and real-time WebSocket support.

## Overview

This deployment provides a complete EspoCRM installation with:
- **EspoCRM** - Open-source CRM platform
- **PostgreSQL 18** - Relational database with health checks
- **Traefik** - Reverse proxy with automatic HTTPS (Let's Encrypt)
- **Automated Backups** - PostgreSQL backups every 2 hours
- **WebSocket Support** - Real-time notifications and updates
- **Custom Extensions** - Persistent customization directory

## Architecture

### Services

| Service | Purpose | Image | Restart Policy |
|---------|---------|-------|----------------|
| `traefik` | Reverse proxy, SSL termination | `traefik:latest` | always |
| `postgres` | Database server | `postgres:18` | always |
| `postgres-backup` | Automated database backups | Custom build | unless-stopped |
| `espocrm` | Main CRM application | `espocrm/espocrm:latest` | always |
| `espocrm-daemon` | Background job processor | `espocrm/espocrm:latest` | always |
| `espocrm-websocket` | Real-time WebSocket server | `espocrm/espocrm:latest` | always |
| `whoami` | Debug service (local only) | `traefik/whoami` | - |

### Volumes

- `postgres_data` - Persistent PostgreSQL database storage
- `espocrm_data` - Persistent EspoCRM application files
- `./custom` - Custom EspoCRM extensions and modules (mounted)
- `./letsencrypt` - SSL certificates from Let's Encrypt (mounted)
- `./backups` - Database backup files (mounted)

### Network Architecture

```
Internet → Traefik (ports 80/443)
              ↓
         EspoCRM Web
              ↓
    ┌────────┴────────┐
    ↓                 ↓
PostgreSQL     EspoCRM Daemon
    ↑                 ↓
    └──── Backups  WebSocket
```

## Prerequisites

- **Docker** (20.10+)
- **Docker Compose** (v2.0+)
- **Domain name** with DNS A record pointing to your server
- **Email address** for Let's Encrypt certificate notifications
- **Open ports**: 80 (HTTP), 443 (HTTPS), 8080 (Traefik dashboard - optional)

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
# Database Configuration
POSTGRES_USER=espocrm
POSTGRES_PASSWORD=localpass
POSTGRES_DB=espocrm

# EspoCRM Admin Credentials
ESPOCRM_ADMIN_USERNAME=admin
ESPOCRM_ADMIN_PASSWORD=adminpass

# Domain Configuration
ESPOCRM_SITE_URL=crm.localhost

# Let's Encrypt Email
YOUR_EMAIL=your-email@example.com

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
# Database Configuration - USE STRONG PASSWORDS
POSTGRES_USER=pguser
POSTGRES_PASSWORD=YOUR_STRONG_PASSWORD_HERE
POSTGRES_DB=pg_dbname

# EspoCRM Admin Credentials - USE STRONG PASSWORDS
ESPOCRM_ADMIN_USERNAME=admin
ESPOCRM_ADMIN_PASSWORD=YOUR_STRONG_ADMIN_PASSWORD

# Domain Configuration - YOUR ACTUAL DOMAIN
ESPOCRM_SITE_URL=espo.yourdomain.com

# Let's Encrypt Email - VALID EMAIL FOR CERTIFICATE EXPIRY NOTICES
YOUR_EMAIL=admin@yourdomain.com

# Leave empty for automatic Let's Encrypt
TRAFFIC_TLS_COMMANDS=
```

**Important:** Use the `.env.prod` file for production by either:
- Renaming it to `.env`
- Specifying it explicitly: `docker compose --env-file .env.prod up -d`

### Step 2: DNS Configuration

Before deploying, ensure your domain points to your server:

```bash
# Test DNS resolution
dig +short espo.yourdomain.com

# Should return your server's IP address
```

If deploying locally, add to `/etc/hosts`:
```
127.0.0.1 crm.localhost
```

### Step 3: Create Required Directories

```bash
# Create directories for persistent data
mkdir -p backups letsencrypt custom
```

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

2. **Wait for PostgreSQL health check:**
   ```bash
   docker compose logs postgres
   ```
   Look for: "database system is ready to accept connections"

3. **Access EspoCRM:**
   - Open: `https://espo.yourdomain.com` (or your configured domain)
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
| `POSTGRES_USER` | PostgreSQL username | `espocrm` or `pguser` |
| `POSTGRES_PASSWORD` | PostgreSQL password | Strong password |
| `POSTGRES_DB` | Database name | `espocrm` |

### EspoCRM Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `ESPOCRM_ADMIN_USERNAME` | Initial admin username | `admin` |
| `ESPOCRM_ADMIN_PASSWORD` | Initial admin password | Strong password |
| `ESPOCRM_SITE_URL` | Full domain for CRM | `espo.domain.com` |

### Traefik Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `YOUR_EMAIL` | Email for Let's Encrypt | `admin@domain.com` |
| `TRAFFIC_TLS_COMMANDS` | Optional TLS override | Leave empty for ACME |

### Backup Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `MANUAL_BACKUP` | Trigger manual backup | `0` (automated) |

## Backup & Restore

### Automated Backups

The `postgres-backup` service automatically backs up the database every 2 hours.

**Backup location:** `./backups/`
**Backup format:** `db_YYYYMMDD_HHMMSS.sql.gz`

**View backup logs:**
```bash
docker compose logs postgres-backup
```

**List backups:**
```bash
ls -lh backups/
```

### Manual Backup

Create an immediate backup before maintenance:

```bash
docker compose run --rm -e MANUAL_BACKUP=1 postgres-backup
```

This creates a timestamped backup in `./backups/`.

### Restore from Backup

1. **Stop EspoCRM services:**
   ```bash
   docker compose stop espocrm espocrm-daemon espocrm-websocket
   ```

2. **Extract and restore backup:**
   ```bash
   # Find your backup file
   ls backups/

   # Restore (replace BACKUP_FILE with your file)
   gunzip -c backups/BACKUP_FILE.sql.gz | \
     docker compose exec -T postgres psql -U $POSTGRES_USER -d $POSTGRES_DB
   ```

3. **Restart services:**
   ```bash
   docker compose start espocrm espocrm-daemon espocrm-websocket
   ```

### Backup Retention

Backups are not automatically deleted. Implement a cleanup strategy:

```bash
# Keep last 30 days of backups (run via cron)
find backups/ -name "db_*.sql.gz" -mtime +30 -delete
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
docker compose logs -f postgres
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
# Connect to PostgreSQL
docker compose exec postgres psql -U $POSTGRES_USER -d $POSTGRES_DB

# Run vacuum (database optimization)
docker compose exec postgres psql -U $POSTGRES_USER -d $POSTGRES_DB -c "VACUUM ANALYZE;"

# Check database size
docker compose exec postgres psql -U $POSTGRES_USER -d $POSTGRES_DB -c "SELECT pg_size_pretty(pg_database_size('$POSTGRES_DB'));"
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
- PostgreSQL not healthy: Wait for health check to pass
- DNS not pointing to server: Check DNS with `dig yourdomain.com`
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

**Check PostgreSQL health:**
```bash
docker compose exec postgres pg_isready -U $POSTGRES_USER
```

**Verify credentials:**
```bash
# Check environment variables
docker compose exec espocrm env | grep POSTGRES
```

**Reset database connection:**
```bash
docker compose restart postgres espocrm
```

### Backup Service Not Running

**Check backup logs:**
```bash
docker compose logs postgres-backup
```

**Test manual backup:**
```bash
docker compose run --rm -e MANUAL_BACKUP=1 postgres-backup
```

**Verify backup directory permissions:**
```bash
ls -la backups/
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
- `POSTGRES_PASSWORD` - Database access
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

PostgreSQL is not exposed externally by default. To access remotely:
- Use SSH tunnel: `ssh -L 5432:localhost:5432 user@server`
- Never expose PostgreSQL port directly to internet

### 8. Backup Security

Backups contain sensitive data:
```bash
# Restrict backup directory
chmod 700 backups/

# Consider encrypting backups
gpg --encrypt backups/db_YYYYMMDD_HHMMSS.sql.gz
```

## Additional Resources

- [EspoCRM Documentation](https://docs.espocrm.com/)
- [EspoCRM Docker Hub](https://hub.docker.com/r/espocrm/espocrm)
- [Traefik Documentation](https://doc.traefik.io/traefik/)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)

## Support

For issues specific to this deployment:
- Check troubleshooting section above
- Review container logs: `docker compose logs [service]`
- Verify environment configuration

For EspoCRM issues:
- [EspoCRM Community Forum](https://forum.espocrm.com/)
- [EspoCRM GitHub Issues](https://github.com/espocrm/espocrm/issues)
