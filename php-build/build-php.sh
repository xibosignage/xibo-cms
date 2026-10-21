#!/bin/sh
#
# Copyright (C) 2026 Xibo Signage Ltd
#
# Xibo - Digital Signage - https://xibosignage.com
#
# This file is part of Xibo.
#
# Xibo is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# any later version.
#
# Xibo is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
#

#
# Build PHP 7.4 from source (remicollet/php-src-security, PHP-7.4-security-backports)
# so that we can ship security fixes which are no longer packaged by Alpine.
#
# The build mirrors the extension set previously provided by the Alpine 3.15 php7-* packages.
# Everything is installed under /usr/local, with configuration kept in /etc/php7 so that the
# existing entrypoint/php.ini handling continues to work unchanged.
#
# Expects the PHP source tree in /usr/src/php.
# The PECL tarballs and Alpine's zmq compile patches are downloaded at build time, each from several
# mirrors (pecl.php.net is not always available). Every download is verified against the sha512 pinned
# below: these are the exact sources Alpine 3.15 built its php7-pecl-* packages from.
#
# Outputs:
#   /usr/local                     PHP binaries, extensions and headers
#   /etc/php7                      php.ini and conf.d
#   /usr/lib/apache2/mod_php7.so   Apache module
#   /usr/local/php-run-deps        list of runtime shared library deps for apk
set -eux

apk add --no-cache \
    autoconf \
    bison \
    re2c \
    build-base \
    file \
    pkgconf \
    apache2 \
    apache2-dev \
    argon2-dev \
    curl-dev \
    freetype-dev \
    gettext-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    openssl-dev \
    pcre2-dev \
    zlib-dev \
    libmemcached-dev \
    zeromq-dev \
    snappy-dev \
    icu-dev \
    cyrus-sasl-dev \
    patch \
    curl

# Fetch a file into /usr/src/pecl, trying each mirror in turn until one serves the pinned content.
# Arguments: local file name, expected sha512, then candidate URLs.
pecl_fetch() {
    file="$1"; sha512="$2"; shift 2
    for url in "$@"; do
        if curl -fsSL --retry 3 --connect-timeout 20 --max-time 300 -o "/usr/src/pecl/$file" "$url"; then
            if echo "$sha512  /usr/src/pecl/$file" | sha512sum -c -; then
                return 0
            fi
            echo "Checksum mismatch: $url"
        else
            echo "Download failed: $url"
        fi
    done
    echo "Unable to download $file with the expected checksum from any mirror"
    return 1
}

mkdir -p /usr/src/pecl
ALPINE_DISTFILES=https://distfiles.alpinelinux.org/distfiles/v3.15

pecl_fetch igbinary-3.2.6.tgz \
    bb1a87f46b0ba2396a3d21b8cc2165498243a86182dc5814d85053bcac14fb099041ec9a67be975eb636e9fae411d2f2695848f0739ddb385c060d56c2f73f4a \
    https://pecl.php.net/get/igbinary-3.2.6.tgz \
    "$ALPINE_DISTFILES/php-pecl-igbinary-3.2.6.tgz"
pecl_fetch memcached-3.1.5.tgz \
    87d5fd079fcaad81139785d6766da71b23a1a0fe907d4b22f3a787af148a20412c11df11c4d98c165241658baf0d87c8a6b29de15db07a0f9d1fabc8aec8f0e9 \
    https://pecl.php.net/get/memcached-3.1.5.tgz \
    "$ALPINE_DISTFILES/php7-pecl-memcached-3.1.5.tgz"
pecl_fetch mongodb-1.11.1.tgz \
    4faa97a04da349b94d94b8a0ccb856c2f80ca26cf30cd001ca21293348b4a5375db074eb33594b3c4b1f8a121f21a3253b67a88c2d90421383aa5c63cfc78643 \
    https://pecl.php.net/get/mongodb-1.11.1.tgz \
    "$ALPINE_DISTFILES/php-pecl-mongodb-1.11.1.tgz"
pecl_fetch zmq-1.1.3.tgz \
    0f03016e315a6c6a1e1d6eca1e20564834d2a924cef06af1b38693933f1e5a094c37e9c432705a64f61d95e82655f61358fc7783e7a6f06c7dd9ad84fc6f7bbc \
    https://pecl.php.net/get/zmq-1.1.3.tgz \
    "$ALPINE_DISTFILES/php7-pecl-zmq-1.1.3.tgz"

# zmq 1.1.3 compile fixes, from the aports commit Alpine 3.15 built php7-pecl-zmq from
ZMQ_APORTS=5f9bc68c563fd8b2ba45d22fcad96b093158fa8e
ZMQ_APORTS_PATH=community/php7-pecl-zmq
pecl_fetch zmq-fix-php-7.3-compile.patch \
    3fda312b76106a1b78e35ffce3199bf52d040008e5fc9c134dd0b3f4a41ceef8857e8cae10b208dfd9b7394dfea652533a258f523da9b5f870d381bddd780797 \
    "https://gitlab.alpinelinux.org/alpine/aports/-/raw/$ZMQ_APORTS/$ZMQ_APORTS_PATH/fix-php-7.3-compile.patch" \
    "https://raw.githubusercontent.com/alpinelinux/aports/$ZMQ_APORTS/$ZMQ_APORTS_PATH/fix-php-7.3-compile.patch" \
    "https://git.alpinelinux.org/aports/plain/$ZMQ_APORTS_PATH/fix-php-7.3-compile.patch?id=$ZMQ_APORTS"
pecl_fetch zmq-fix-php-7.4-compile.patch \
    58288af1fd1441491c7f5cc7be967ba8e806a8d80141d4c3699af9a977ede57f3f464922bc218f3aabdce965699fea5a016954ca538f98559553625d33ff2d4f \
    "https://gitlab.alpinelinux.org/alpine/aports/-/raw/$ZMQ_APORTS/$ZMQ_APORTS_PATH/fix-php-7.4-compile.patch" \
    "https://raw.githubusercontent.com/alpinelinux/aports/$ZMQ_APORTS/$ZMQ_APORTS_PATH/fix-php-7.4-compile.patch" \
    "https://git.alpinelinux.org/aports/plain/$ZMQ_APORTS_PATH/fix-php-7.4-compile.patch?id=$ZMQ_APORTS"

export CFLAGS="-fstack-protector-strong -fpic -fpie -O2 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64"
export CPPFLAGS="$CFLAGS"
export LDFLAGS="-Wl,-O1 -pie"

cd /usr/src/php

# A git checkout has no generated configure script or parser/scanner sources
./buildconf --force

# Only the extensions the CMS loaded from Alpine packages are enabled (--disable-all), built in
# statically so that no per-extension ini files are needed.
./configure \
    --prefix=/usr/local \
    --with-config-file-path=/etc/php7 \
    --with-config-file-scan-dir=/etc/php7/conf.d \
    --with-apxs2=/usr/bin/apxs \
    --disable-all \
    --disable-cgi \
    --disable-phpdbg \
    --with-pic \
    --enable-ctype \
    --with-curl \
    --enable-dom \
    --enable-fileinfo \
    --enable-filter \
    --enable-gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
        --disable-gd-jis-conv \
    --with-gettext \
    --with-iconv \
    --enable-json \
    --with-libxml \
    --enable-mbstring \
    --enable-mysqlnd \
        --with-mysql-sock=/run/mysqld/mysqld.sock \
    --with-openssl \
        --with-system-ciphers \
    --with-password-argon2 \
    --with-external-pcre \
    --enable-pdo \
        --with-pdo-mysql=mysqlnd \
    --enable-session \
    --enable-simplexml \
    --enable-soap \
    --enable-sockets \
    --enable-tokenizer \
    --enable-xml \
    --enable-xmlwriter \
    --with-zip \
    --with-zlib

make -j"$(nproc)"
make install
make clean

mkdir -p /etc/php7/conf.d
cp php.ini-production /etc/php7/php.ini

# apxs installs the module as libphp7.so; use the name Alpine's php7-apache2 package used
mv /usr/lib/apache2/libphp7.so /usr/lib/apache2/mod_php7.so

# PECL extensions (same versions and options as the Alpine 3.15 packages)
pecl_build() {
    tarball="$1"; shift
    mkdir -p /usr/src/pecl-build
    cd /usr/src/pecl-build
    tar -xzf "/usr/src/pecl/$tarball" --strip-components=1
    phpize
    ./configure "$@"
    make -j"$(nproc)"
    make install
    cd /usr/src
    rm -rf /usr/src/pecl-build
}

# igbinary is the default memcached serializer in the Alpine build, keep it so cached data stays readable
pecl_build igbinary-3.2.6.tgz
echo "extension=igbinary.so" > /etc/php7/conf.d/10_igbinary.ini

pecl_build memcached-3.1.5.tgz \
    --enable-memcached-igbinary \
    --disable-memcached-sasl
echo "extension=memcached.so" > /etc/php7/conf.d/20_memcached.ini

pecl_build mongodb-1.11.1.tgz
echo "extension=mongodb.so" > /etc/php7/conf.d/mongodb.ini

# zmq 1.1.3 needs Alpine's patches to compile against 7.3/7.4
mkdir -p /usr/src/pecl-build
cd /usr/src/pecl-build
tar -xzf /usr/src/pecl/zmq-1.1.3.tgz --strip-components=1
patch -p1 < /usr/src/pecl/zmq-fix-php-7.3-compile.patch
patch -p1 < /usr/src/pecl/zmq-fix-php-7.4-compile.patch
phpize
./configure
make -j"$(nproc)"
make install
cd /usr/src
rm -rf /usr/src/pecl-build
echo "extension=zmq.so" > /etc/php7/conf.d/50_zmq.ini

# Strip binaries and drop things the runtime image does not need
strip --strip-all /usr/local/bin/php /usr/lib/apache2/mod_php7.so /usr/local/lib/php/extensions/*/*.so

# Record the shared libraries PHP links against, so the runtime image can apk add exactly those
scanelf --needed --nobanner --format '%n#p' --recursive /usr/local /usr/lib/apache2/mod_php7.so \
    | tr ',' '\n' \
    | sort -u \
    | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    > /usr/local/php-run-deps
cat /usr/local/php-run-deps

# Record what we built
php -v
php -m
