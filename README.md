<a href="https://xibosignage.com">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docker/brand/logo.svg">
    <source media="(prefers-color-scheme: light)" srcset="docker/brand/logo-dark.svg">
    <img alt="Xibo Logo" src="docker/brand/logo-dark.svg" width="300">
  </picture>
</a>

[![Github All Releases](https://img.shields.io/github/downloads/xibosignage/xibo-cms/total.svg)]()


## About Xibo
Xibo is a powerful Open Source Digital Signage platform with a web content management system and Windows display player
software. We have commercial player options for Android, LG webOS and Samsung Tizen, as well as CMS hosting and support.

See [https://xibosignage.com](https://xibosignage.com) for more information.

Our first open source release 1.0.0-rc1 landed in 2009, and we're committed to keeping everything you need to run a
digital signage network, or single screen, open source and free to use.

## Licence

[![Licence](https://img.shields.io/github/license/xibosignage/xibo-cms)]()

Copyright (C) 2006-2026 Xibo Signage Ltd and Contributors.

Xibo is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public
License as published by the Free Software Foundation, either version 3 of the License, or any later version.

Xibo is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with Xibo. 
If not, see <http://www.gnu.org/licenses/>.


# Installation

Xibo can be deployed on our [Cloud](https://xibosignage.com/pricing) or self-hosted. Docker is the only supported way
to self-host the CMS. Installations without Docker are community-supported only, see
[MANUAL_INSTALL.md](MANUAL_INSTALL.md) for some basic pointers.

## Prerequisites

- A Linux, Windows or macOS host with at least 2GB of RAM.
- [Docker Engine](https://docs.docker.com/engine/install/) (or Docker Desktop) with the Docker Compose plugin v2.20 or later. Check
  it is installed by running `docker compose version`.

## Install

1. Download the `xibo-docker-<version>.tar.gz` (or `.zip`) bundle from the latest
   [release](https://github.com/xibosignage/xibo-cms/releases/latest) and extract it into the folder you want to run
   the CMS from. The bundle contains `docker-compose.yml` with the CMS image pinned to that release, `.env.template`
   and a short `INSTALL.md`.

   You can use `docker-compose.yml` from this repository instead. It always tracks the latest stable release
   (`ghcr.io/xibosignage/xibo-cms:stable`), so each `docker compose pull` can upgrade you without warning. We recommend
   the release bundle.

2. Copy `.env.template` to `.env` and edit it. At a minimum set:
   - `MYSQL_PASSWORD`: a random password of around 16 letters and digits, no special characters.
   - The `CMS_SMTP_*` settings, so that the CMS can send email.

3. From the same folder, start the CMS:

   ```shell
   docker compose up -d
   ```

   The first start can take a few minutes while the database is created.

4. Open `http://localhost` (or your server's address) and log in with:

   U: `xibo_admin`
   P: `password`

   Change this password straight away.

### Custom ports

The web interface listens on port 80 and XMR (push messaging for Players) on port 9505. If either port is already in
use on the host, set `CMS_WEB_PORT` and/or `CMS_XMR_PORT` in `.env` (for example `CMS_WEB_PORT=8080`) and run
`docker compose up -d` again.

### Remote MySQL

By default the CMS runs its own MySQL container, which is the `local-db` profile enabled by `COMPOSE_PROFILES=local-db`
in `.env`. To use your own MySQL server instead:

1. Create an empty database, and a user with all privileges on it.
2. In `.env`, delete the `COMPOSE_PROFILES=local-db` line.
3. In `.env`, set `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER` and `MYSQL_PASSWORD`.
4. To connect over TLS, also set `MYSQL_ATTR_SSL_CA` (and `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT`).

### Where your data lives

Everything the CMS stores is kept in the `shared` folder next to `docker-compose.yml`. Back this folder up, and keep it
(and `.env`) when you upgrade.

- `shared/db`: the database (when using the bundled MySQL container).
- `shared/backup`: automated daily database backups.
- `shared/cms/library`: the media library.
- `shared/cms/custom`: custom modules and `settings-custom.php`.
- `shared/cms/web/userscripts`: your own PHP or resources to host on the same web server, served at
  `http://localhost/userscripts/`.
- `shared/cms/ca-certs`: additional CA certificates to trust.

## Upgrading

1. Download the bundle for the new release and extract it over the top of your existing installation. This replaces
   `docker-compose.yml` and `.env.template`; it does not touch `.env` or `shared`.
2. Compare `.env.template` with your `.env` and copy in any new settings you want to use. Do not change
   `MYSQL_PASSWORD`.
3. Pull the new images and recreate the containers:

   ```shell
   docker compose pull
   docker compose up -d
   ```

The CMS takes a database backup and then upgrades the database automatically. It is unavailable for a few minutes
while this happens.

## Migrating from xibo-docker

Older installations used the separate `xibo-docker` repository and its release archive. That repository has been
retired, and its files have been replaced by the `docker-compose.yml` and `.env.template` here. Your `shared` folder
and its data carry over.

Before you start, check that your current installation is on xibo-docker 4.5.x or later, which already runs MySQL 8.4.
The new `docker-compose.yml` uses MySQL 8.4, which can't upgrade a database last run by MySQL 5.7 directly. If you're on
an older release, upgrade it with the xibo-docker archive for 4.5.x first, then come back to this guide.

To migrate:

1. In your existing installation folder, stop the CMS **before** extracting the new files. If you started it with a
   custom file, add the same `-f` option (for example `docker compose -f cms_custom-ports.yml down`):

   ```shell
   docker compose down
   ```

   If you've already extracted the new files, stop the old containers by project name instead. That's the folder
   name in lower case, for example `docker compose -p xibo-docker down`. Run `docker ps` to check.

2. Download the bundle for the release you're upgrading to and extract it into the same folder. Keep `shared` and
   `config.env`.
3. Rename `config.env` to `.env`.
4. Edit `.env`:
   - If you used the bundled MySQL server (`docker-compose.yml` or `cms_custom-ports.yml`), add the line
     `COMPOSE_PROFILES=local-db`.
   - If you used `cms_remote-mysql.yml`, don't add it. Your `MYSQL_*` settings carry over unchanged.
   - If you used `cms_custom-ports.yml`, set `CMS_WEB_PORT` and `CMS_XMR_PORT` to the host ports from that file.
5. Delete the old `cms_custom-ports.yml`, `cms_custom-ports.yml.template`, `cms_remote-mysql.yml` and
   `config.env.template*` files. Any other changes you made to the old compose files need to be made again in the new
   `docker-compose.yml`.
6. Start the CMS:

   ```shell
   docker compose up -d
   ```

## Running without Docker Compose

If you have your own Docker environment you can run the containers without Docker Compose. You are then responsible
for pulling the images, starting them with equivalent configuration and installing Xibo. Use `docker-compose.yml` as
a reference.


# Developing

**The development environment is not suitable for a production installation.** If you intend to fork Xibo and make
code changes, see [DEVELOPING.md](DEVELOPING.md) for how to set up a development environment.


# Contributing
We would be delighted to accept contributions to the project - please refer to
[CONTRIBUTING.md](https://github.com/xibosignage/xibo/blob/master/CONTRIBUTING.md) for further information.

# Sponsorship
We've built commercial products and services on top of our open source project. If you want to support our work the
best way is to [become a customer](https://xibosignage.com/pricing). We're committed to keeping our project open
source either way!

# Reporting Problems
Support requests can be reported on the [Xibo Community Forum](https://community.xibo.org.uk/c/dev). Verified, 
re-producable bugs with this repository can be reported in 
the [Xibo parent repository](https://github.com/xibosignage/xibo/issues).
