# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

EspoCRM Docker deployment for DreamHold real estate operations. Production-ready setup with MariaDB, Traefik reverse proxy, automated backups, and a custom Real Estate module.

## Common Commands

All commands run from `apps/crm/` directory.

### Service Management

```bash
# Start (use appropriate env file)
docker compose --env-file .env up -d          # Development
docker compose --env-file .env.prod up -d     # Production

# View logs
docker compose logs -f [service-name]         # e.g., espocrm, mariadb, traefik

# Stop
docker compose --env-file .env down           # Development
docker compose --env-file .env.prod down      # Production

# Restart single service
docker compose restart espocrm
```

### Backups

```bash
# Manual backup
docker compose --env-file .env run --rm -e MANUAL_BACKUP=1 mysql-backup       # Dev
docker compose --env-file .env.prod run --rm -e MANUAL_BACKUP=1 mysql-backup  # Prod

# List backups
ls -lh dev-backups/    # Development
ls -lh prod-backups/   # Production
```

### Cache & Rebuild

```bash
# Clear EspoCRM cache (required after custom code changes)
docker compose exec espocrm rm -rf data/cache/*

# Rebuild application
docker compose exec espocrm php command.php rebuild
```

### Database

```bash
# Connect to MariaDB
docker compose exec mariadb mysql -u $MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE

# Optimize tables
docker compose exec mariadb mysqlcheck -u $MARIADB_USER -p$MARIADB_PASSWORD --optimize $MARIADB_DATABASE
```

## Architecture

### Services

| Service | Purpose |
|---------|---------|
| `traefik` | Reverse proxy with automatic HTTPS (Let's Encrypt) |
| `mariadb` | MariaDB database with health checks |
| `mysql-backup` | Automated backup every hour (cron) |
| `espocrm` | Main CRM web application |
| `espocrm-daemon` | Background job processor |
| `espocrm-websocket` | Real-time WebSocket server |

### Environment Isolation

Single `docker-compose.yml` supports both dev and prod via `ENVIRONMENT` variable:
- Container names suffixed with `-dev` or `-prod`
- Separate volumes: `mariadb_data_dev`/`mariadb_data_prod`, `espocrm_data_dev`/`espocrm_data_prod`
- Separate backup directories: `dev-backups/`, `prod-backups/`
- Different domains required per environment

### Key Files

- `docker-compose.yml` - Service orchestration
- `traefik-tls.yml` - TLS configuration (modern ciphers, HTTP/2)
- `.env` / `.env.prod` - Environment configuration (git-ignored)
- `mysql-backup/backup.sh` - Backup automation script

## Custom Real Estate Module

Located in `custom/Espo/Modules/RealEstate/`. Changes are hot-mounted into the container.

### Module Structure

```
custom/Espo/Modules/RealEstate/
├── Controllers/        # API endpoints
├── Entities/           # Entity definitions (RealEstateProperty, RealEstateRequest)
├── Services/           # Business logic services
├── Repositories/       # Data access layer
├── Hooks/              # Entity lifecycle hooks
├── Jobs/               # Background jobs
├── Tools/              # Tool services
│   ├── Property/       # Property-related tools
│   ├── Request/        # Request-related tools
│   └── Matches/        # Property-Request matching engine
├── Classes/
│   ├── Record/Hooks/   # Before/After record operations
│   └── Select/         # Custom query filters (Actual, ActualRent, ActualSale, Completed)
└── Resources/layouts/  # UI layouts (JSON)
```

### Key Entities

- **RealEstateProperty** - Real estate listings (properties for sale/rent)
- **RealEstateRequest** - Client requests (buying/renting criteria)
- **Opportunity** - Deals connecting properties and requests
- **RealEstateLocation** - Location hierarchies

### After Modifying Custom Code

1. Clear cache: `docker compose exec espocrm rm -rf data/cache/*`
2. Rebuild: `docker compose exec espocrm php command.php rebuild`

Or rebuild from EspoCRM admin panel: Administration → Rebuild

## EspoCRM Extension Patterns

### Adding a Service

```php
namespace Espo\Modules\RealEstate\Services;

class YourService extends \Espo\Core\Templates\Services\Base
{
    // ...
}
```

### Adding an API Endpoint

```php
namespace Espo\Modules\RealEstate\Tools\YourTool\Api;

use Espo\Core\Api\Action;

class YourEndpoint implements Action
{
    // ...
}
```

### Adding a Record Hook

```php
namespace Espo\Modules\RealEstate\Classes\Record\Hooks\EntityName;

class BeforeUpdate implements \Espo\Core\Record\Hook\UpdateHook
{
    // ...
}
```

### Adding a Primary Filter

```php
namespace Espo\Modules\RealEstate\Classes\Select\EntityName\PrimaryFilters;

class FilterName extends \Espo\Core\Select\Primary\Filter
{
    // ...
}
```

## Configuration

### Debug Logging

Currently enabled in `custom/config.php`:
```php
'logger' => [
    'level' => 'DEBUG',
    'databaseHandler' => true,
    'databaseHandlerLevel' => 'DEBUG',
]
```

View logs in EspoCRM admin panel: Administration → Log

### Environment Variables

| Variable | Description |
|----------|-------------|
| `ENVIRONMENT` | `dev` or `prod` - controls service naming and volumes |
| `MARIADB_ROOT_PASSWORD` | Database root password |
| `MARIADB_DATABASE` | Database name |
| `MARIADB_USER` | Database user |
| `MARIADB_PASSWORD` | Database password |
| `ESPOCRM_ADMIN_USERNAME/PASSWORD` | Initial admin credentials |
| `ESPOCRM_SITE_URL` | Domain without protocol (e.g., `crm.example.com`) |
| `YOUR_EMAIL` | Email for Let's Encrypt certificate notifications |

## Additional Documentation

- `README.md` - Complete deployment guide with troubleshooting
- `DEPLOYMENT_EVOLUTION.md` - Strategic roadmap and planned improvements
