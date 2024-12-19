<?php

/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Twig\TwigFilter;
use Xibo\Service\ConfigService;
use Xibo\Twig\ByteFormatterTwigExtension;
use Xibo\Twig\DateFormatTwigExtension;
use Xibo\Twig\TransExtension;
use Xibo\Twig\TwigMessages;

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
require_once PROJECT_ROOT . '/vendor/autoload.php';

$view = Twig::create([
    PROJECT_ROOT . '/views',
    PROJECT_ROOT . '/modules',
    PROJECT_ROOT . '/reports'
], [
    'cache' => PROJECT_ROOT . '/cache'
]);
$view->addExtension(new TransExtension());
$view->addExtension(new ByteFormatterTwigExtension());
$view->addExtension(new DateFormatTwigExtension());
$view->getEnvironment()->addFilter(new TwigFilter('url_decode', 'urldecode'));

// Trick the flash middleware
$storage = [];
$view->addExtension(new TwigMessages(new Messages($storage)));

foreach (glob(PROJECT_ROOT . '/views/*.twig') as $file) {
    $view->getEnvironment()->load(str_replace(PROJECT_ROOT . '/views/', '', $file));
}

/**
 * Mock PDO Storage Service which returns an empty array when select queried.
 */
class MockPdoStorageServiceForModuleFactory extends \Xibo\Storage\PdoStorageService
{
    public function select($sql, $params, $connection = 'default', $reconnect = false, $close = false)
    {
        return [];
    }
}

// Mock Config Service
class MockConfigService extends ConfigService
{
    public function getSetting($setting, $default = null, $full = false)
    {
        return '';
    }
}

// Translator function
function __($original)
{
    return $original;
}

// Stash
$pool = new \Stash\Pool();

// Create a new Sanitizer service
$sanitizerService = new \Xibo\Helper\SanitizerService();

// Create a new base dependency service
$baseDependencyService = new \Xibo\Service\BaseDependenciesService();
$baseDependencyService->setConfig(new MockConfigService());
$baseDependencyService->setStore(new MockPdoStorageServiceForModuleFactory());
$baseDependencyService->setSanitizer($sanitizerService);

$moduleFactory = new \Xibo\Factory\ModuleFactory(
    '',
    $pool,
    $view,
    new MockConfigService(),
);
$moduleFactory->useBaseDependenciesService($baseDependencyService);
// Get all module
$modules = $moduleFactory->getAll();

$moduleTemplateFactory = new \Xibo\Factory\ModuleTemplateFactory(
    $pool,
    $view,
);
$moduleTemplateFactory->useBaseDependenciesService($baseDependencyService);
// Get all module templates
$moduleTemplates = $moduleTemplateFactory->getAll(null, false);

// --------------
// Create translation file
// Each line contains title or description or properties of the module/templates
$file = PROJECT_ROOT. '/locale/moduletranslate.php';
$content = '<?php' . PHP_EOL;

$content .= '// Module translation' . PHP_EOL;
// Module translation
foreach ($modules as $module) {
    $content .= 'echo __(\''.$module->name.'\');' . PHP_EOL;
    $content .= 'echo __(\''.$module->description.'\');' . PHP_EOL;

    // Settings Translation
    foreach ($module->settings as $setting) {
        if (!empty($setting->title)) {
            $content .= 'echo __(\''.$setting->title.'\');' . PHP_EOL;
        }
        if (!empty($setting->helpText)) {
            // replaces any single quote within the value with a backslash followed by a single quote
            $helpText = addslashes(trim($setting->helpText));
            $content .= 'echo __(\''.$helpText.'\');' . PHP_EOL;
        }

        if (isset($setting->options) > 0) {
            foreach ($setting->options as $option) {
                if (!empty($option->title)) {
                    $content .= 'echo __(\''.$option->title.'\');' . PHP_EOL;
                }
            }
        }
    }

    // Properties translation
    foreach ($module->properties as $property) {
        if (!empty($property->title)) {
            $content .= 'echo __(\''.addslashes(trim($property->title)).'\');' . PHP_EOL;
        }
        if (!empty($property->helpText)) {
            // replaces any single quote within the value with a backslash followed by a single quote
            $helpText = addslashes($property->helpText);
            $content .= 'echo __(\''.$helpText.'\');' . PHP_EOL;
        }

        if (isset($property->validation) > 0) {
            $tests = $property->validation->tests;
            foreach ($tests as $test) {
                // Property rule test message
                $message = $test->message;
                if (!empty($message)) {
                    $content .= 'echo __(\''.addslashes(trim($message)).'\');' . PHP_EOL;
                }
            }
        }

        if (isset($property->options) > 0) {
            foreach ($property->options as $option) {
                if (!empty($option->title)) {
                    $content .= 'echo __(\''.$option->title.'\');' . PHP_EOL;
                }
            }
        }
    }
}

$content .= '// Module Template translation' . PHP_EOL;
// Template Translation
foreach ($moduleTemplates as $moduleTemplate) {
    $content .= 'echo __(\''.$moduleTemplate->title.'\');' . PHP_EOL;

    // Properties Translation
    foreach ($moduleTemplate->properties as $property) {
        if (!empty($property->title)) {
            $content .= 'echo __(\''.addslashes(trim($property->title)).'\');' . PHP_EOL;
        }
        if (!empty($property->helpText)) {
            // replaces any single quote within the value with a backslash followed by a single quote
            $helpText = addslashes(trim($property->helpText));
            $content .= 'echo __(\''.$helpText.'\');' . PHP_EOL;
        }

        if (isset($property->validation) > 0) {
            $tests = $property->validation->tests;
            foreach ($tests as $test) {
                // Property rule test message
                $message = $test->message;
                if (!empty($message)) {
                    $content .= 'echo __(\''.$message.'\');' . PHP_EOL;
                }
            }
        }

        if (isset($property->options) > 0) {
            foreach ($property->options as $option) {
                if (!empty($option->title)) {
                    $content .= 'echo __(\''.$option->title.'\');' . PHP_EOL;
                }
            }
        }
    }
}

file_put_contents($file, $content);
echo 'moduletranslate.file created and data written successfully.';
