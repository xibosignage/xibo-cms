# Installing the Xibo CMS with Docker

This folder contains everything needed to run the Xibo CMS with Docker Compose:

- `docker-compose.yml`: the CMS, its database, XMR (push messaging for Players), Memcached and QuickChart.
- `.env.template`: the settings template. Copy it to `.env` and edit it.

The full guide covers custom ports, remote MySQL, data layout, upgrades and migrating from the older `xibo-docker`
archive. It's at <https://github.com/xibosignage/xibo-cms#installation>.

## Install

You need Docker Engine (or Docker Desktop) with the Docker Compose plugin v2.20 or later.

1. Copy `.env.template` to `.env`.
2. Edit `.env`. At a minimum set `MYSQL_PASSWORD` (around 16 random letters and digits, no special characters) and the
   `CMS_SMTP_*` settings.
3. In this folder, run:

   ```shell
   docker compose up -d
   ```

4. Open `http://localhost` and log in as `xibo_admin` with the password `password`. Change the password straight away.

Your data is kept in the `shared` folder created next to `docker-compose.yml`. Back it up, along with `.env`.

## Upgrade

1. Download the bundle for the new release from <https://github.com/xibosignage/xibo-cms/releases> and extract it
   into this folder, replacing the existing files. Your `.env` and `shared` folder are not touched.
2. Compare `.env.template` with your `.env` and copy in any new settings you want. Do not change `MYSQL_PASSWORD`.
3. Run:

   ```shell
   docker compose pull
   docker compose up -d
   ```

## Migrating from xibo-docker

If this folder was installed from the older `xibo-docker` archive (it has a `config.env` file), don't follow the
upgrade steps above. Follow the migration steps at <https://github.com/xibosignage/xibo-cms#migrating-from-xibo-docker>
instead.
