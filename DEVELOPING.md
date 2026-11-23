# Developing Xibo

**Please only install a Development environment if you intend to fork Xibo and make code changes. The development
environment is not suitable for a production installation. To install Xibo, follow the
[installation instructions in the README](README.md#installation).**

Xibo uses Docker to ensure a repeatable development environment which is easy to get up and running. The very same
Docker containers are used in our recommended end user installation to promote consistency from development to
deployment.

This repository has two Docker Compose files:

- `docker-compose.yml` is the **production** installation. It runs the published CMS image.
- `docker-compose.dev.yml` is the **development** environment. It builds `Dockerfile.dev` and maps this repository into
  the web container. Every Compose command in this guide passes `-f docker-compose.dev.yml`.

The two files have different Compose project names (`xibo` and `xibo-dev`), so they never share containers. If your
dev environment was started before `docker-compose.dev.yml` existed, its containers belong to a project named after
your checkout folder. Stop them once with `docker compose -p <folder-name> down` (lower case, check with `docker ps`)
before starting `xibo-dev`. Your database in `containers/db` is kept.

Both files read an optional `.env` file. In development it can hold local overrides. It doesn't have to be a copy of
`.env.template`, which is for production installs.


## Prerequisites

The development Docker containers do not automatically build vendor files for PHP or JS, this is left as a developer 
responsibility. Therefore you will need the following tools:

 - Git
 - [Composer](http://getcomposer.org)
 - NodeJS version 12
 - npm
 - Docker


## Fork and clone the repository

Fork the repository on GitHub.

Create a folder in your development workspace and clone the repository. If you intend to make changes and submit
pull requests please Fork us first and create a new branch.

```shell
git clone git@github.com:<your_id>/xibo-cms.git xibo-cms
```

### Branches

We maintain the following branches. To contribute to Xibo please use the `develop` branch as your base.

#### Current

- develop: Bug fixes for 4.5.x
- master: Currently 4.5

#### Archive
- release44: Archive of 4.4
- release43: Archive of 4.3
- release42: Archive of 4.2
- release33: Archive of 3.3
- release23: Archive of 2.3
- release18: Archive of 1.8
- release17: Archive of 1.7
- release1.6.4: Archive of 1.6

## Install dependencies

Change into your new folder

```sh
cd xibo-cms
```

We recommend installing the dependencies via Docker, so that you are guarenteed consistent dependencies across 
different development machines.

### PHP dependencies

```shell
docker run --interactive --tty --volume $PWD:/app --volume ~/.composer:/tmp composer install
```

This command also mounts the Composer `/tmp` folder into your home directory so that you can take advantage of
Composer caching.

### Website dependencies (webpack)

If you have installed node locally:

```shell
npm install webpack -g
npm install
npm run build
```

Alternatively you can use a Docker container:

```shell
docker run -it --volume $PWD:/app --volume ~/.npm:/root/.npm -w /app node:22 sh -c "npm install webpack -g; npm install; npm run build;"
```

### Mapped Volumes

The development version of Xibo expects the code base to be mapped into the container such that changes on the host
are reflected in the container.

However, the container itself creates some files, such as the twig cache and library uploads. These locations will need
to be created and the container given access to them.

The easiest way to do this is to make the `cache` and `library` folders and `chmod 777` them. Obviously this is not
suitable for production, but you shouldn't be using these files for production (we have containers for that).

### API Keys
The API requires a pub/private RSA keypair and an encryption key to be provided. The docker entrypoint will create 
these in `/library/certs`.

You can override the generated keys paths and encryption key by providing an alternative in `settings-custom.php`.
For example: 

```php
$apiKeyPaths = [
    'publicKeyPath' => '/var/www/cms/custom/public.key',
    'privateKeyPath' => '/var/www/cms/custom/private.key',
    'encryptionKey' => ''
];
```

### OpenOOH specification
Xibo can present the OpenOOH venue classifications in the display edit form. For this functionality to work in 
development, it is necessary 
to [download the latest file](https://raw.githubusercontent.com/openooh/venue-taxonomy/main/specification.json) and 
place it in here: `openooh/specification.json`

The production/CI containers add this file during the build process so that it is already available in the Docker
image.


## Bring up the Containers

Use Docker Compose to bring up the containers.

```sh
docker compose -f docker-compose.dev.yml up --build -d
```

## Login
After the containers have come up you should be able to login with the details:

U: `xibo_admin`
P: `password`


## Translations
To parse the translations:

React:
```shell
cd frontend
npx i18next-cli extract
```

Then:
```shell
docker compose -f docker-compose.dev.yml exec web sh -c "cd /var/www/cms; rm -R ./cache"
docker compose -f docker-compose.dev.yml exec web sh -c "cd /var/www/cms; php bin/locale.php"
```

```shell
find ./locale ./cache ./lib ./web  -iname "*.php" -print0 | xargs -0 xgettext --from-code=UTF-8 -k_e -k_x -k__ -o locale/default.pot
```

To import translations:

```shell
bzr pull lp:~dangarner/xibo/neujmin-translations
```

Convert to `mo` format:

```shell
for i in *.po; do msgfmt "$i" -o $(echo $i | sed s/po/mo/); done
```

Move the resulting `mo` files into your `locale` folder.

The React language packs (`frontend/public/locale/langs/*.json`) are generated from the
`locale/*.mo` files and are **not** committed, they are build artifacts. `npm run dev` and
`npm run build` regenerate them automatically, so you normally don't need to do anything. To
regenerate them manually (e.g. after adding new `mo` files):

```shell
cd frontend
npm run i18n:convert
```

Note: this is also run during the build process.

## Swagger API Docs
To generate a `swagger.json` file, with the dev containers running:

```shell
docker compose -f docker-compose.dev.yml exec web sh -c "cd /var/www/cms; vendor/bin/openapi lib -o web/swagger.json"
```

## Application Structure

To find out more about the application code and how everything fits together, please refer to 
the [developer docs](https://xibosignage.com/docs/developer/extend).


