# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Xibo** is an open-source Digital Signage platform (AGPL-3.0 licensed). The CMS is the web-based content management system that runs alongside display player software for managing digital signage networks.

- **Current Version**: 4.4.0-alpha (PHP 8.1+, Node 12+)
- **Repository**: https://github.com/xibosignage/xibo-cms
- **Documentation**: https://xibosignage.com/docs

## Technology Stack

- **Backend**: PHP 8.4 with Slim 4 framework (MVC)
- **Database**: MySQL 8.4 with Phinx migrations
- **Frontend (legacy)**: JavaScript/jQuery with Bootstrap 4, webpack (in `ui/`)
- **Frontend (new)**: React 18 + TypeScript + Vite + TanStack React Table + Tailwind CSS (in `frontend/`)
- **Dependency Injection**: PHP-DI 7.0
- **Testing**: PHPUnit 10.5 (currently limited test suite)
- **Logging**: Monolog 2.10
- **API**: REST API with OAuth2 Server (OpenAPI/Swagger documented)
- **Caching**: Stash with Memcached support
- **Containerization**: Docker Compose (multi-stage builds for production)

## Build & Development Setup

### Prerequisites

- Git, Docker, Composer, Node 12+, npm, Docker Compose
- For local development without Docker: PHP 8.1+, MySQL 8.0+

### Initial Setup

```bash
# Clone and enter repository
git clone <repo> xibo-cms
cd xibo-cms

# Install PHP dependencies (via Docker recommended for consistency)
docker run --interactive --tty --volume $PWD:/app --volume ~/.composer:/tmp composer install

# Install Node dependencies and build webpack assets
npm install webpack -g
npm install
npm run build

# Create writable directories (for development only, not production-safe)
mkdir -p cache library
chmod 777 cache library

# Bring up Docker containers
docker-compose up --build -d

# Login with: xibo_admin / password
```

### Key Build Commands

**PHP/Composer**:
```bash
# Install/update PHP dependencies
composer install
composer update

# Run PHP CodeSniffer linting
composer phpcs
# (Uses: vendor/xibosignage/support/src/Standards/xibo_ruleset.xml)
```

**JavaScript/Webpack**:
```bash
# Development build (unminified, with source maps)
npm run build

# Production build (minified)
npm run publish

# Local development with custom config
npm run local

# Run Cypress E2E tests
npm test
npm run cypress:open
```

**Database Migrations** (via Phinx):
```bash
# In container or local PHP environment
vendor/bin/phinx migrate -c phinx.php
vendor/bin/phinx status -c phinx.php

# Migrations located in: db/migrations/
# Config: phinx.php (reads database connection from web/settings.php)
```

**Testing**:
```bash
# Run PHPUnit (currently only XMDS test suite enabled)
vendor/bin/phpunit --configuration phpunit.xml

# Tests located in: tests/
# Bootstrap: tests/Bootstrap.php
# Currently uncommented: tests/Xmds/ (XMDS API tests)
# Commented out: tests/integration/, tests/Widget/
```

**API Documentation**:
```bash
# Generate Swagger/OpenAPI docs (with containers running)
docker-compose exec web sh -c "cd /var/www/cms; vendor/bin/swagger lib -o web/swagger.json"

# Swagger UI available at: http://localhost:8080 (in docker-compose)
```

**Translations**:
```bash
# Clear cache and regenerate locale files
docker-compose exec web sh -c "cd /var/www/cms; rm -R ./cache"
docker-compose exec web sh -c "cd /var/www/cms; php bin/locale.php"

# Extract translation strings
find ./locale ./cache ./lib ./web -iname "*.php" -print0 | xargs -0 xgettext --from-code=UTF-8 -k_e -k_x -k__ -o locale/default.pot
```

## Architecture Overview

### Directory Structure

```
lib/
  ├── Controller/         # 60+ REST API controllers (Slim 4 routes)
  ├── Entity/             # Data models with traits (EntityTrait, TagLinkTrait, etc.)
  ├── Factory/            # Data access objects, entity instantiation (BaseFactory pattern)
  ├── Service/            # Business logic services (interfaces + implementations)
  ├── Widget/             # Extensible widget/module system
  │   ├── Definition/     # Widget definitions and data types
  │   ├── Provider/       # Data providers for widgets
  │   ├── Validator/      # Widget-specific validators
  │   └── Render/         # Widget render templates
  ├── Middleware/         # Slim middleware (authentication, validation)
  ├── Helper/             # Utility classes (environment, sanitizers, image processing)
  ├── Listener/           # Event listeners (Symfony EventDispatcher)
  ├── Twig/               # Twig template extensions
  ├── Storage/            # Storage service interfaces
  ├── Connector/          # Data connector system
  ├── Validation/         # Validation rules
  ├── Event/              # Event classes
  ├── OAuth/              # OAuth2 implementation
  ├── Report/             # Reporting system
  ├── XTR/                # Xibo Task Runner integration
  ├── Xmds/               # XMDS (XML Management Distribution Service) API
  ├── routes.php          # API route definitions
  └── routes-web.php      # Web interface route definitions

web/
  ├── index.php           # Entry point (loads container via index.php in web/)
  ├── xmds.php            # XMDS API endpoint
  ├── settings.php        # Configuration (database, etc.)
  ├── api/                # API resources (minimal, mostly routed through lib/routes.php)
  ├── dist/               # Compiled webpack assets (JS/CSS bundles)
  ├── theme/              # Twig templates for web UI
  ├── install/            # Installation wizard
  └── swagger.json        # Generated API documentation

ui/
  ├── src/
  │   ├── layout-editor/  # Layout editor (Vue-like architecture)
  │   ├── playlist-editor/
  │   ├── campaign-builder/
  │   ├── pages/          # Admin pages
  │   ├── core/           # Shared frontend utilities
  │   └── helpers/
  └── bundle_*.js         # Webpack entry points for different features

modules/
  ├── assets/             # Compiled widget JavaScript/CSS
  ├── src/                # Webpack source for widget rendering
  └── vendor/             # Third-party widget modules

custom/
  ├── README.md           # Custom module development guide
  └── {namespace}/        # User/custom modules (auto-loaded as Xibo\Custom\*)

db/migrations/            # Phinx database migrations (timestamp-based naming)

tests/
  ├── Bootstrap.php       # PHPUnit bootstrap
  ├── Xmds/               # XMDS API tests (currently only active test suite)
  ├── integration/        # Integration tests (commented out)
  ├── resources/          # Test fixtures
  └── LocalWebTestCase.php

docker/                   # Docker entrypoint scripts and configurations
containers/db/            # Docker Compose MySQL data volume (DO NOT SEARCH — large binary files)

frontend/
  ├── src/
  │   ├── app/            # App root, routing config
  │   ├── components/     # Shared React components (DataTable, MediaCell, etc.)
  │   ├── context/        # React context providers
  │   ├── hooks/          # Custom hooks (useOwner, useKeydown, useTableState, etc.)
  │   ├── pages/          # Page components (Design/, Displays/, Library/)
  │   │   ├── Design/
  │   │   │   ├── Layouts/          # Layout list page + LayoutPreviewer, LayoutInfoPanel
  │   │   │   ├── Campaigns/        # Campaign list page
  │   │   │   └── Templates/        # Template list page
  │   │   ├── Displays/
  │   │   └── Library/Media/
  │   ├── services/       # RTK Query API slices (layoutsApi, etc.)
  │   ├── types/          # TypeScript interfaces (Layout, Campaign, Media, Tag, etc.)
  │   └── utils/          # Shared utilities
  └── vite.config.ts      # Vite build config; compiled output goes to web/dist/pages/
```

### Core Design Patterns

**Dependency Injection via PHP-DI**:
- Entry point loads container via `ContainerFactory::create()` in `web/index.php`
- All services registered in `lib/Dependencies/` and `lib/Factory/ContainerFactory.php`
- Controllers receive dependencies via constructor injection

**Entity + Factory Pattern**:
- Entities (in `lib/Entity/`) represent domain models
- Each entity has a corresponding Factory (in `lib/Factory/`) for CRUD operations
- Factories use `StorageServiceInterface` (database abstraction)
- Base classes: `BaseFactory` and `EntityTrait`

**Service Layer**:
- Business logic encapsulated in Services (`lib/Service/`)
- Interfaces defined alongside implementations
- Examples: `ConfigServiceInterface`, `LogServiceInterface`, `MediaServiceInterface`

**Event-Driven Architecture**:
- Symfony EventDispatcher used throughout
- Event listeners in `lib/Listener/` namespace
- Examples: `DisplayGroupLoadEvent`, `TriggerTaskEvent`

**Middleware Stack** (Slim 4):
- Authentication, authorization, feature checks, layout locking
- Located in `lib/Middleware/`
- Applied globally or per-route

**Widget/Module System**:
- Widgets are pluggable modules extending a base widget interface
- Each widget has definition, validation, provider, and render logic
- Custom modules can be placed in `custom/` folder (auto-loaded via PSR-4)
- Widget metadata in XML files in `modules/` directory

**Widget Data Sync Pipeline** (non-obvious):
- `WidgetSyncTask` (`lib/XTR/WidgetSyncTask.php`) is the background task that keeps widget data fresh
- Per-widget flow: `decorateWithCache()` → `fetchData()` → `processDownloads()` → `saveToCache()` → `finaliseCache()`
- `WidgetDataProviderCache` (`lib/Widget/Render/WidgetDataProviderCache.php`) wraps the data provider with a Stash cache and a **distributed lock** (held for the full sync cycle including downloads) to prevent concurrent regeneration of the same widget's data
- Remote images queued via `DataProvider::addImage()` → `MediaFactory::queueDownload()` are stored as `mediaType='module'` using `createModuleFile()`. Their library path is `LIBRARY_LOCATION/{name}` (not `{mediaId}.ext`), and `isSaveRequired` checks file existence and filesize at this path
- `MediaFactory::processDownloads()` uses a Guzzle Pool (concurrency 5). The `on_headers` callback rejects oversized files early (when `Content-Length` is present); the `progress` callback aborts mid-stream when `Content-Length` is absent but bytes received exceed the limit. Both throw into the `rejected` path which calls `delete(['rollback' => true])` to remove the DB record and library file

**REST API**:
- Routes defined in `lib/routes.php` (80+ endpoints)
- OpenAPI/Swagger annotations in source code
- OAuth2-protected by default
- JSON response format

### Database Schema

- Migrations use **Phinx** (PHP migration framework)
- Config in `phinx.php` (reads database settings from `web/settings.php`)
- Migrations located in `db/migrations/` (timestamp-prefixed)
- Initial schema: `20180130073838_install_migration.php` (88KB, full DB schema)
- Subsequent migrations apply incremental changes

**Key Entities**:
- `display`, `display_group`, `display_profile` — Display hardware management
- `layout`, `region`, `playlist`, `widget` — Content structure
- `media`, `media_file` — Digital assets
- `user`, `user_group`, `permission` — Access control
- `schedule`, `schedule_detail` — Display scheduling
- `dataset`, `dataset_column`, `dataset_data` — Dynamic data
- `campaign`, `action` — Campaign management
- `connector` — Data connectors (APIs, files, databases)
- `audit_log` — Activity logging
- `report_*` — Reporting tables

### Configuration

**Settings Files**:
- `web/settings.php` — Database connection, encryption keys, API keys (generated on first run)
- `web/settings-custom.php` — Optional overrides (not in repo)
- Environment variables used in Docker deployments

**Key Settings**:
- Database: `$dbhost`, `$dbname`, `$dbuser`, `$dbpass`
- Encryption: `$apiKeyPaths`, `$encryptionKey` (RSA keypair for API)
- Cache: Memcached configuration, cache drivers
- Mail: SMTP settings, authentication
- Features: Feature flags, plugin settings

### Frontend Architecture

**React Frontend** (new — `frontend/`):

The newer admin pages are built in React and served at `/prototype/*` URLs (e.g. `/prototype/design/layout`, `/prototype/design/campaign`, `/prototype/displays/displays`). These are compiled by Vite and output to `web/dist/pages/`.

Key patterns:
- Pages live in `frontend/src/pages/{Section}/{PageName}/`
- Each page has a `{Page}.tsx` (component), `{Page}Config.tsx` (column defs + actions), and a `hooks/` subfolder for data fetching
- Column definitions use TanStack React Table `ColumnDef<T>[]`
- Thumbnails are rendered via `MediaCell` (`components/ui/table/cells/MediaCell.tsx`) — pass `onPreview` to make them clickable
- Preview modals (e.g. `LayoutPreviewer`) receive the full row object and should use the `previewUrl` property returned by the API (includes a JWT token), not hardcoded URL patterns
- TypeScript types for API responses live in `frontend/src/types/`; add new API-returned fields there when the backend adds them
- API calls use RTK Query service files in `frontend/src/services/`

**Webpack Bundles** (from `webpack.config.js`):
- `vendor.bundle.js` — Third-party libraries (jQuery, Bootstrap, etc.)
- `xibo.bundle.js` — Core Xibo UI utilities
- `datatables.bundle.js` — DataTables grid library
- `layoutEditor.bundle.js` — Layout editor application
- `playlistEditor.bundle.js` — Playlist editor
- `campaignBuilder.bundle.js` — Campaign builder
- `systemTools.bundle.js` — Admin tools
- `templates.bundle.js` — Handlebars templates
- `codeEditor.bundle.js` — Code editor (CodeMirror)
- `wysiwygEditor.bundle.js` — Rich text editor (CKEditor 5)
- Individual bundles for widgets (audio, calendar, clock, countdown, dataset, etc.)

**Template Engine**:
- Twig 3.11 for server-side rendering (in `web/theme/`)
- Handlebars for client-side templates (in modules)
- CodeMirror for code editing with language support

### Testing Strategy

Currently, only **XMDS API tests** are active in `phpunit.xml`. Integration and unit test suites are commented out but available in:
- `tests/integration/` — Integration tests (requires running containers)
- `tests/Widget/` — Widget unit tests

**Test Bootstrap** (`tests/Bootstrap.php`):
- Sets up test environment
- Loads container (may use test-specific configuration)

**E2E Testing**:
- Cypress configured in `package.json`
- Tests in `cypress/` directory
- Run via `npm test` or `npm run cypress:open`

## Common Development Tasks

### Adding a New API Endpoint

1. Create a Controller method in `lib/Controller/` (extends `Base`)
2. Add route definition in `lib/routes.php` (include OpenAPI annotation for Swagger)
3. Create/use Factory and Entity classes for data access
4. Inject dependencies (logger, user, storage) via constructor
5. Return JSON response via `$this->render()` or `Response`

### Adding a Database Migration

1. Run: `vendor/bin/phinx create MigrationName -c phinx.php`
2. Edit generated file in `db/migrations/`
3. Implement `up()` and `down()` methods
4. Test: `vendor/bin/phinx migrate -c phinx.php`

### Creating a Custom Widget/Module

1. Create module class in `custom/` extending appropriate base
2. Implement `installOrUpdate()` method with widget metadata
3. Create Twig templates in `custom/{module-name}/`
4. Register in `modules.json` descriptor file
5. Module auto-loads via PSR-4 namespace `Xibo\Custom\`

### Debugging Issues

- **Logs**: Database logs written via `DatabaseLogHandler`, file logs via Monolog
- **Cache**: Clear via `rm -rf cache/` (development only)
- **Settings**: Check `web/settings.php` for configuration issues
- **Migrations**: Check `db/migrations/` for pending migrations
- **Webpack**: Run `npm run build` to recompile assets (required after UI changes)

## Important Notes for Code Changes

- **PHP Code Style**: Enforced via PHPCS with xibosignage/support ruleset; run `composer phpcs` before committing
- **Database Changes**: Always add Phinx migration, never modify tables directly
- **Frontend Changes**: Require `npm run build` (or `npm run publish` for production)
- **API Documentation**: Use OpenAPI annotations (`@SWG\*`) in controller methods
- **Event Publishing**: Use `EventDispatcherInterface` for loose coupling
- **Entity Validation**: Use `Respect\Validation` library (see `lib/Validation/`)
- **Error Handling**: Throw appropriate exceptions from `lib/Support/Exception/`
- **Testing**: Update or add tests in `tests/Xmds/` (other test suites commented out pending refactoring)

## Deployment

Production deployments use **Docker** with multi-stage builds:
- Stage 1: Composer installs PHP dependencies
- Stage 2: Webpack builds frontend assets
- Stage 3: Debian-based image with Apache, PHP, and pre-built artifacts

See `Dockerfile`, `Dockerfile.dev`, and `Dockerfile.ci` for different build targets.

Docker Compose development environment includes:
- MySQL 8.4 (port 3315)
- Memcached
- Xibo XMR (message router) on port 9505
- Swagger UI on port 8080
- Web server on port 80

## Repository Branches

- `develop` — Active development (4.4.x bug fixes)
- `master` — Current stable release (4.4)
- `release43`, `release42`, `release33` — Older release branches
- Feature/bugfix branches follow pattern: `bugfix/*`, `feature/*`

## Additional Resources

- **API Reference**: Generated Swagger at `/swagger.json` (requires running containers)
- **Developer Docs**: https://xibosignage.com/docs/developer/extend
- **Community Forum**: https://community.xibo.org.uk/c/dev
- **Contributing**: See CONTRIBUTING.md in parent repository
