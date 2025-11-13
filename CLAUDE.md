# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

This is a pnpm monorepo containing multiple applications and shared packages:

- **apps/site** - React Router v7 (Remix) web application with Forge 42 base-stack
- **apps/crm** - EspoCRM Docker deployment with PostgreSQL, Traefik, and automated backups
- **packages/ui** - Shared UI components (currently empty scaffold)
- **packages/utils** - Shared utilities (currently empty scaffold)

## Package Manager

This project uses **pnpm** (v10.18.2+) with workspace configuration. Always use `pnpm` commands, never `npm` or `yarn`.

## Working with apps/site

The site application is built on Forge 42's base-stack template and uses:
- React Router v7 (framework mode)
- React 19 with react-compiler
- TypeScript with strict configuration
- Vite for build tooling
- Hono server with custom middleware
- i18next for internationalization (server & client)
- TailwindCSS v4 with Vite plugin
- Biome for linting and formatting
- Vitest with browser testing
- Lefthook for git hooks

### Common Commands (run from apps/site/)

```bash
# Development
pnpm dev                    # Start dev server (default port 4280)

# Building
pnpm build                  # Production build
pnpm start                  # Run production build

# Testing
pnpm test                   # Run tests headless
pnpm test:ui                # Run tests with UI
pnpm test:cov               # Run tests with coverage

# Code Quality
pnpm check                  # Run Biome linter
pnpm check:fix              # Auto-fix Biome issues
pnpm typecheck              # Run TypeScript compiler
pnpm check:unused           # Check for unused code with knip
pnpm check:unused:fix       # Auto-remove unused code
pnpm validate               # Run all checks (lint + typecheck + test + unused)

# Type Generation
pnpm typegen                # Generate React Router types (runs on postinstall)

# Scripting
pnpm script <path>          # Run custom scripts with env support
pnpm cleanup                # Remove base-stack related files (first-run only)
```

### Architecture

#### Server Architecture (Hono + React Router)

The server is configured in `app/entry.server.tsx` and uses:
- **Hono** as the underlying HTTP server via `react-router-hono-server`
- **Global application context** (`app/server/context.ts`) that provides:
  - Current locale/language
  - Translation function (t)
  - Environment variables (server and client)
  - Production deployment flag
- **i18next middleware** for SSR translations
- **Caching middleware** for assets (configured via `react-router-hono-server`)
- **Custom stream timeout** of 10 seconds for pending promises

The context is injected into all loaders/actions via `context.get(globalAppContext)`.

#### Internationalization (i18n)

Fully-typed i18next implementation with split client/server strategies:

- **Server-side**: All translation resources preloaded into i18next instance (no network requests)
- **Client-side**: Lazy-loading translations via `resource.locales.ts` route (cached in production)
- **Language detection**: Via request in server, falls back to default language
- **Language switching**: Via `lng` search parameter in URL
- **Configuration**: All i18n logic in `app/localization/` directory

Translation resources are located in `resources/locales/` (not shown but referenced in READMEs).

#### Environment Variables

- `.env` files are parsed and injected into server context (`app/env.server.ts`)
- **Server**: Access via `context.get(globalAppContext).env`
- **Client**: Injected as `window.env` via script tag in `root.tsx`
- Application will fail at runtime if `.env` is not configured properly

#### Icons System

Uses `vite-plugin-icons-spritesheet` to generate SVG spritesheets:
- **Input**: `resources/icons/` directory
- **Output**: `app/library/icon/icons/icon.svg` with TypeScript types
- Icons are formatted with Biome

#### Routing

- File-based routing via `@react-router/fs-routes` in `app/routes/`
- Route configuration in `react-router.config.ts` with future flags enabled
- Built-in SEO routes: `robots[.]txt.ts`, `sitemap-index[.]xml.ts`, `sitemap.$lang[.]xml.ts`

#### Scripting System

Custom TypeScript scripting system (`scripts/` directory) with:
- ESM compatibility
- Alias imports (`~`)
- Environment variable support (`.env`, `.env.test`, `.env.stage`, `.env.prod`)
- Confirmation dialogs for destructive operations

Run scripts via: `pnpm script <script-path> [environment] [confirm]`

Example: `pnpm script scripts/your-command.ts prod confirm`

#### Git Hooks (lefthook)

Pre-commit hooks run in parallel (`lefthook.yml`):
1. Biome check with auto-fix (stages fixed files)
2. TypeScript type checking
3. Vitest tests
4. Knip unused code detection

#### Library Components

Reusable components in `app/library/`:
- `icon/` - Icon component using spritesheet system
- `language-switcher/` - Component for switching languages
- `link/` - Enhanced link component

Each has its own README with usage details.

## Working with apps/crm

**📖 For complete deployment documentation, troubleshooting, and detailed guides, see [apps/crm/README.md](apps/crm/README.md)**

This is the main production application currently in use. Docker Compose deployment of EspoCRM with PostgreSQL backend.

### Architecture

- **Traefik** - Reverse proxy with automatic HTTPS (Let's Encrypt)
- **PostgreSQL 18** - Database with health checks
- **EspoCRM** - Main CRM container with custom volume mounting
- **espocrm-daemon** - Background job processor
- **espocrm-websocket** - WebSocket server for real-time features
- **postgres-backup** - Automated backup service with cron + manual backup support
- **whoami** - Debug service (local profile only)

### Environment Files

- `.env` - Local development configuration
- `.env.prod` - Production configuration
- Use `.env.example` files as templates

### Common Commands (run from apps/crm/)

```bash
# Start services
docker compose up -d

# View logs
docker compose logs -f [service-name]

# Stop services
docker compose down

# Manual database backup
docker compose run --rm -e MANUAL_BACKUP=1 postgres-backup

# Run local debug service
docker compose --profile local up -d whoami
```

### Customization

Mount custom EspoCRM extensions/modifications via `./custom:/var/www/html/custom` volume.

### Backups

Automated PostgreSQL backups via cron are stored in `./backups/` directory with format: `db_YYYYMMDD_HHMMSS.sql.gz`

## Updating apps/site from Forge 42 Upstream

The site app is based on `forge-42/base-stack` template. To merge upstream updates:

```bash
cd apps/site
git init
git remote add upstream https://github.com/forge-42/base-stack.git
git fetch upstream
git merge upstream/main
# Review and resolve conflicts
rm -rf .git
cd ../../
# Commit merged changes to monorepo
```

This is done manually when upstream changes are needed (infrequent).

## Development Workflow

1. Use **Biome** for all code formatting/linting (not Prettier/ESLint)
2. Run validation before committing: `pnpm validate` (from apps/site)
3. Git hooks will auto-run checks on commit
4. TypeScript must pass type checking
5. All tests must pass
6. No unused code (checked by knip)

## Node Version

Requires Node.js >= 24.3.0 (specified in apps/site/package.json engines)
