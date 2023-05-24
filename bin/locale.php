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

use Slim\Flash\Messages;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Twig\TwigFilter;
use Xibo\Twig\ByteFormatterTwigExtension;
use Xibo\Twig\DateFormatTwigExtension;
use Xibo\Twig\TransExtension;
use Xibo\Twig\TwigMessages;
use Xibo\Factory\ContainerFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ModuleTemplateFactory;
use Monolog\Logger;
use Nyholm\Psr7\ServerRequest;
use Slim\Http\ServerRequest as Request;

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
require_once PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php')) {
    die('Not configured');
}

$view = Twig::create([
    PROJECT_ROOT . '/views',
    PROJECT_ROOT . '/modules',
    PROJECT_ROOT . '/reports'
], [
    'cache' => PROJECT_ROOT . '/cache'
]);
$view->addExtension(new TwigExtension());
$view->addExtension(new TransExtension());
$view->addExtension(new ByteFormatterTwigExtension());
$view->addExtension(new DateFormatTwigExtension());
$view->getEnvironment()->addFilter(new TwigFilter('url_decode', 'urldecode'));

// Trick the flash middleware
$storage = [];
$view->addExtension(new TwigMessages(new Messages($storage)));

foreach (glob(PROJECT_ROOT . '/views/*.twig') as $file) {
    echo var_export($file, true) . PHP_EOL;

    $view->getEnvironment()->load(str_replace(PROJECT_ROOT . '/views/', '', $file));
}

// Create the container for dependency injection.
try {
    $container = ContainerFactory::create();
} catch (Exception $e) {
    die($e->getMessage());
}

// Logger
$uidProcessor = new \Monolog\Processor\UidProcessor(7);
$container->set('logger', function () use ($uidProcessor) {
    return (new Logger('XMDS'))
        ->pushProcessor($uidProcessor)
        ->pushHandler(new \Xibo\Helper\DatabaseLogHandler());
});

// Create a Slim application
$app = \DI\Bridge\Slim\Bridge::create($container);
$app->setBasePath($container->get('basePath'));

// Mock a request
$request = new Request(new ServerRequest('GET', $app->getBasePath()));
$request = $request->withAttribute('name', 'locale');
$container->set('name', 'locale');

// Set state
\Xibo\Middleware\State::setState($app, $request);

$file = PROJECT_ROOT. '/locale/moduletranslate.php';
if (!file_exists($file)) {
    fopen($file, 'w');
}

if (is_writable($file)) {
    $content = '<?php' . PHP_EOL;

    /** @var ModuleFactory $moduleFactory */
    $moduleFactory = $container->get('moduleFactory');
    $modules = $moduleFactory->getAll();

    $content .= "// Module translation" . PHP_EOL;
    // Module translation
    foreach ($modules as $module) {
        $content .= "echo __('$module->name');" . PHP_EOL;
        $content .= "echo __('$module->description');" . PHP_EOL;

        // Settings Translation
        foreach ($module->settings as $setting) {
            if (!empty($setting->title)) {
                $content .= "echo __('$setting->title');" . PHP_EOL;
            }
            if (!empty($setting->helpText)) {
                // replaces any single quote within the value with a backslash followed by a single quote
                $helpText = str_replace("'", "\\'", $setting->helpText);
                $content .= "echo __('$helpText');" . PHP_EOL;
            }

            if (isset($setting->options) > 0) {
                foreach ($setting->options as $option) {
                    if (!empty($option->title)) {
                        $content .= "echo __('$option->title');" . PHP_EOL;
                    }
                }
            }
        }

        // Properties translation
        foreach ($module->properties as $property) {
            if (!empty($property->title)) {
                $content .= "echo __('$property->title');" . PHP_EOL;
            }
            if (!empty($property->helpText)) {
                // replaces any single quote within the value with a backslash followed by a single quote
                $helpText = str_replace("'", "\\'", $property->helpText);
                $content .= "echo __('$helpText');" . PHP_EOL;
            }

            if (isset($property->options) > 0) {
                foreach ($property->options as $option) {
                    if (!empty($option->title)) {
                        $content .= "echo __('$option->title');" . PHP_EOL;
                    }
                }
            }
        }
    }

    /** @var ModuleTemplateFactory $moduleTemplateFactory */
    $moduleTemplateFactory = $container->get('moduleTemplateFactory');
    $moduleTemplates = $moduleTemplateFactory->getAll();

    $content .= "// Module Template translation" . PHP_EOL;
    // Template Translation
    foreach ($moduleTemplates as $moduleTemplate) {
        $content .= "echo __('$moduleTemplate->title');" . PHP_EOL;

        // Properties Translation
        foreach ($moduleTemplate->properties as $property) {
            if (!empty($property->title)) {
                $content .= "echo __('$property->title');" . PHP_EOL;
            }
            if (!empty($property->helpText)) {
                // replaces any single quote within the value with a backslash followed by a single quote
                $helpText = str_replace("'", "\\'", $property->helpText);
                $content .= "echo __('$helpText');" . PHP_EOL;
            }

            if (isset($property->options) > 0) {
                foreach ($property->options as $option) {
                    if (!empty($option->title)) {
                        $content .= "echo __('$option->title');" . PHP_EOL;
                    }
                }
            }
        }
    }

    $content .= '?>';
    file_put_contents($file, $content);
    echo 'moduletranslate.file created and data written successfully.';
} else {
    echo 'Unable to write to the moduletranslate.file.';
}
