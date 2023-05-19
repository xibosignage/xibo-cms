<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\XTR;

use Carbon\Carbon;
use Xibo\Controller\Module;
use Xibo\Entity\Font;
use Xibo\Event\MaintenanceDailyEvent;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\FontFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\DatabaseLogHandler;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\MediaServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class MaintenanceDailyTask
 * @package Xibo\XTR
 */
class MaintenanceDailyTask implements TaskInterface
{
    use TaskTrait;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @var Module */
    private $moduleController;

    /** @var MediaServiceInterface */
    private $mediaService;

    /** @var DataSetFactory */
    private $dataSetFactory;
    /**
     * @var FontFactory
     */
    private $fontFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->moduleController = $container->get('\Xibo\Controller\Module');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->userFactory = $container->get('userFactory');
        $this->dataSetFactory = $container->get('dataSetFactory');
        $this->mediaService = $container->get('mediaService');
        $this->fontFactory = $container->get('fontFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Daily Maintenance') . PHP_EOL . PHP_EOL;

        // Long running task
        set_time_limit(0);

        // Import layouts
        $this->importLayouts();

        // Tidy logs
        $this->tidyLogs();

        // Tidy Cache
        $this->tidyCache();

        // Dispatch an event so that consumers can hook into daily maintenance.
        $event = new MaintenanceDailyEvent();
        $this->getDispatcher()->dispatch($event, MaintenanceDailyEvent::$NAME);
        foreach ($event->getMessages() as $message) {
            $this->appendRunMessage($message);
        }
    }

    /**
     * Tidy the DB logs
     */
    private function tidyLogs()
    {
        $this->runMessage .= '## ' . __('Tidy Logs') . PHP_EOL;

        $maxage = $this->config->getSetting('MAINTENANCE_LOG_MAXAGE');
        if ($maxage != 0) {
            // Run this in the log handler so that we share the same connection and don't deadlock.
            DatabaseLogHandler::tidyLogs(
                Carbon::now()
                    ->subDays(intval($maxage))
                    ->format(DateFormatHelper::getSystemFormat())
            );

            $this->runMessage .= ' - ' . __('Done') . PHP_EOL . PHP_EOL;
        } else {
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Tidy Cache
     */
    private function tidyCache()
    {
        $this->runMessage .= '## ' . __('Tidy Cache') . PHP_EOL;
        $this->pool->purge();
        $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
    }

    /**
     * Import Layouts
     * @throws GeneralException
     */
    private function importLayouts()
    {
        $this->runMessage .= '## ' . __('Import Layouts and Fonts') . PHP_EOL;

        if ($this->config->getSetting('DEFAULTS_IMPORTED') == 0) {
            // Make sure the library exists
            $this->mediaService->initLibrary();

            // Import any layouts
            $folder = $this->config->uri('layouts', true);

            foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
                if (stripos($file, '.zip')) {
                    try {
                        $layout = $this->layoutFactory->createFromZip(
                            $folder . '/' . $file,
                            null,
                            $this->userFactory->getSystemUser()->getId(),
                            false,
                            false,
                            true,
                            false,
                            true,
                            $this->dataSetFactory,
                            null,
                            $this->mediaService,
                            1
                        );

                        $layout->save([
                            'audit' => false,
                            'import' => true
                        ]);

                        if (!empty($layout->getUnmatchedProperty('thumbnail'))) {
                            rename($layout->getUnmatchedProperty('thumbnail'), $layout->getThumbnailUri());
                        }

                        try {
                            $this->layoutFactory->getById($this->config->getSetting('DEFAULT_LAYOUT'));
                        } catch (NotFoundException $exception) {
                            $this->config->changeSetting('DEFAULT_LAYOUT', $layout->layoutId);
                        }
                    } catch (\Exception $exception) {
                        $this->log->error('Unable to import layout: ' . $file . '. E = ' . $exception->getMessage());
                        $this->log->debug($exception->getTraceAsString());
                    }
                }
            }

            // install fonts from the theme folder
            $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');
            $fontFolder =  $this->config->uri('fonts', true);
            $fontsAdded = false;
            foreach (array_diff(scandir($fontFolder), array('..', '.')) as $file) {
                // check if we already have this font file
                if (count($this->fontFactory->getByFileName($file)) <= 0) {
                    // if we don't add it
                    $filePath = $fontFolder . DIRECTORY_SEPARATOR . $file;
                    $fontLib = \FontLib\Font::load($filePath);

                    // check embed flag, just in case
                    $embed = intval($fontLib->getData('OS/2', 'fsType'));
                    // if it's not embeddable, log error and skip it
                    if ($embed != 0 && $embed != 8) {
                        $this->log->error('Unable to install default Font: ' . $file
                            . ' . Font file is not embeddable due to its permissions');
                        continue;
                    }

                    /** @var Font $font */
                    $font = $this->fontFactory->createEmpty();
                    $font->modifiedBy = $this->userFactory->getSystemUser()->userName;
                    $font->name = $fontLib->getFontName() . ' ' . $fontLib->getFontSubfamily();
                    $font->familyName = strtolower(preg_replace('/\s+/', ' ', preg_replace('/\d+/u', '', $font->name)));
                    $font->fileName = $file;
                    $font->size = filesize($filePath);
                    $font->md5 = md5_file($filePath);
                    $font->save();

                    $fontsAdded = true;
                    $copied = copy($filePath, $libraryLocation . 'fonts/' . $file);
                    if (!$copied) {
                        $this->getLogger()->error('importLayouts: Unable to copy fonts to ' . $libraryLocation);
                    }
                }
            }

            if ($fontsAdded) {
                // if we added any fonts here fonts.css file
                $this->mediaService->setUser($this->userFactory->getSystemUser())->updateFontsCss();
            }

            $this->config->changeSetting('DEFAULTS_IMPORTED', 1);

            $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
        } else {
            $this->runMessage .= ' - ' . __('Not Required.') . PHP_EOL . PHP_EOL;
        }
    }
}
