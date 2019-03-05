<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * This is a simple script to load all twig files recursively so that we have a complete set of twig files in the
 * /cache folder
 * we can then reliably run xgettext over them to update our POT file
 */
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
require_once PROJECT_ROOT . '/vendor/autoload.php';

$twig = new Twig_Environment(new \Twig\Loader\FilesystemLoader([PROJECT_ROOT . '/views', PROJECT_ROOT . '/modules']), [
    'cache'       => PROJECT_ROOT . '/cache',
    'auto_reload' => true
]);

$twig->addExtension(new \Slim\Views\TwigExtension());
$twig->addExtension(new \Xibo\Twig\TransExtension());
$twig->addExtension(new \Xibo\Twig\ByteFormatterTwigExtension());
$twig->addExtension(new \Xibo\Twig\UrlDecodeTwigExtension());
$twig->addExtension(new \Xibo\Twig\DateFormatTwigExtension());


foreach (glob(PROJECT_ROOT . '/views/*.twig') as $file) {
    echo var_export($file, true) . PHP_EOL;

    $twig->loadTemplate(str_replace(PROJECT_ROOT . '/views/', '', $file));
}
foreach (glob(PROJECT_ROOT . '/modules/*.twig') as $file) {
    echo var_export($file, true) . PHP_EOL;

    $twig->loadTemplate(str_replace(PROJECT_ROOT . '/modules/', '', $file));
}