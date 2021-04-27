<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

namespace Xibo\Service;


use Carbon\Carbon;
use Stash\Interfaces\PoolInterface;
use Stash\Invalidation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\ConfigurationException;

class MediaService implements MediaServiceInterface
{
    /** @var ConfigServiceInterface */
    private $configService;

    /** @var LogServiceInterface */
    private $log;

    /** @var StorageServiceInterface */
    private $store;

    /** @var SanitizerService */
    private $sanitizerService;

    /** @var PoolInterface */
    private $pool;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var User */
    private $user;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /** @inheritDoc */
    public function __construct(
        ConfigServiceInterface $configService,
        LogServiceInterface $logService,
        StorageServiceInterface $store,
        SanitizerService $sanitizerService,
        PoolInterface $pool,
        MediaFactory $mediaFactory
    ) {
        $this->configService = $configService;
        $this->log = $logService;
        $this->store = $store;
        $this->sanitizerService = $sanitizerService;
        $this->pool = $pool;
        $this->mediaFactory = $mediaFactory;
    }

    /** @inheritDoc */
    public function setUser($user) : MediaService
    {
        $this->user = $user;
        return $this;
    }

    /** @inheritDoc */
    public function getUser() : User
    {
        return $this->user;
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getDispatcher(): EventDispatcherInterface
    {
        return $this->dispatcher;
    }

    /** @inheritDoc */
    public function fontCKEditorConfig($routeParser) :string
    {
        // Regenerate the CSS for fonts
        $css = $this->installFonts($routeParser, ['invalidateCache' => false]);

        return $css['ckeditor'];
    }

    /** @inheritDoc */
    public function libraryUsage() : int
    {
        $results = $this->store->select('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', array());

        return $this->sanitizerService->getSanitizer($results[0])->getInt('SumSize');
    }

    /** @inheritDoc */
    public function installFonts($routeParser, $options = [])
    {
        $options = array_merge([
            'invalidateCache' => true
        ], $options);

        $this->log->debug('Install Fonts called with options: ' . json_encode($options));

        // Drop the entire font cache as we cannot selectively tell whether the change that caused
        // this effects all users or not.
        // Important to note, that we aren't regenerating each user at this point in time, we're only clearing the cache
        // for them all and generating the current user.
        // We then make sure that subsequent generates do not change the library fonts.css
        if ($options['invalidateCache']) {
            $this->log->debug('Dropping font cache and regenerating.');
            $this->pool->deleteItem('fontCss/');
        }

        // Each user has their own font cache (due to permissions) and the displays have their own font cache too
        // Get the item from the cache
        $cssItem = $this->pool->getItem('fontCss/' . $this->getUser()->userId);
        $cssItem->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

        // Get the CSS
        $cssDetails = $cssItem->get();

        if ($options['invalidateCache'] || $cssItem->isMiss()) {
            $this->log->debug('Regenerating font cache');

            // lock the cache
            $cssItem->lock(60);

            // Go through all installed fonts each time and regenerate.
            $fontTemplate = '@font-face {
    font-family: \'[family]\';
    src: url(\'[url]\');
}';

            // Save a fonts.css file to the library for use as a module
            $fonts = $this->mediaFactory->getByMediaType('font');

            $css = '';
            $localCss = '';
            $ckEditorString = '';
            $fontList = [];

            // Check the library exists
            $libraryLocation = $this->configService->getSetting('LIBRARY_LOCATION');
            MediaService::ensureLibraryExists($this->configService->getSetting('LIBRARY_LOCATION'));

            if (count($fonts) > 0) {
                // Build our font strings.
                foreach ($fonts as $font) {
                    // Skip unreleased fonts
                    if ($font->released == 0) {
                        continue;
                    }

                    // Separate out the display name and the referenced name (referenced name cannot contain any odd characters or numbers)
                    $displayName = $font->name;
                    $familyName = strtolower(preg_replace('/\s+/', ' ', preg_replace('/\d+/u', '', $font->name)));

                    // Css for the player contains the actual stored as location of the font.
                    $css .= str_replace('[url]', $font->storedAs, str_replace('[family]', $familyName, $fontTemplate));
                    // Test to see if this user should have access to this font
                    if ($this->getUser()->checkViewable($font)) {
                        // Css for the local CMS contains the full download path to the font
                        $url = $routeParser->urlFor('library.download', ['type' => 'font', 'id' => $font->mediaId]);
                        $localCss .= str_replace('[url]', $url, str_replace('[family]', $familyName, $fontTemplate));

                        // CKEditor string
                        $ckEditorString .= $displayName . '/' . $familyName . ';';

                        // Font list
                        $fontList[] = [
                            'displayName' => $displayName,
                            'familyName' => $familyName
                        ];
                    }
                }

                // If we're a full regenerate, we want to also update the fonts.css file.
                if ($options['invalidateCache']) {

                    // Pull out the currently stored fonts.css from the library (if it exists)
                    $existingLibraryFontsCss = '';
                    if (file_exists($libraryLocation . 'fonts.css')) {
                        $existingLibraryFontsCss = file_get_contents($libraryLocation . 'fonts.css');
                    }

                    // Put the player CSS into the temporary library location
                    $tempUrl = $this->configService->getSetting('LIBRARY_LOCATION') . 'temp/fonts.css';
                    file_put_contents($tempUrl, $css);

                    // Install it (doesn't expire, isn't a system file, force update)
                    $media = $this->mediaFactory->createModuleSystemFile('fonts.css', $tempUrl);
                    $media->expires = 0;
                    $media->moduleSystemFile = true;
                    $media->isSaveRequired = true;
                    $media->save(['saveTags' => false]);

                    // We can remove the temp file
                    @unlink($tempUrl);

                    // Check to see if the existing file is different from the new one
                    if ($existingLibraryFontsCss == '' || md5($existingLibraryFontsCss) !== $media->md5) {
                        $this->log->info('Detected change in fonts.css file, dropping the Display cache');
                        // Clear the display cache
                        $this->pool->deleteItem('/display');
                    } else {
                        $this->log->debug('Newly generated font cache is the same as the old cache. Ignoring.');
                    }
                }

                $cssDetails = [
                    'css' => $localCss,
                    'ckeditor' => $ckEditorString,
                    'list' => $fontList
                ];

                $cssItem->set($cssDetails);
                $cssItem->expiresAfter(new \DateInterval('P30D'));
                $this->pool->saveDeferred($cssItem);
            }
        } else {
            $this->log->debug('CMS font CSS returned from Cache.');
        }

        // Return a fonts css string for use locally (in the CMS)
        return $cssDetails;
    }

    /** @inheritDoc */
    public static function ensureLibraryExists($libraryFolder)
    {
        // Check that this location exists - and if not create it..
        if (!file_exists($libraryFolder)) {
            mkdir($libraryFolder, 0777, true);
        }

        if (!file_exists($libraryFolder . '/temp')) {
            mkdir($libraryFolder . '/temp', 0777, true);
        }
        if (!file_exists($libraryFolder . '/cache')) {
            mkdir($libraryFolder . '/cache', 0777, true);
        }

        if (!file_exists($libraryFolder . '/screenshots')) {
            mkdir($libraryFolder . '/screenshots', 0777, true);
        }

        if (!file_exists($libraryFolder . '/attachment')) {
            mkdir($libraryFolder . '/attachment', 0777, true);
        }

        // Check that we are now writable - if not then error
        if (!is_writable($libraryFolder)) {
            throw new ConfigurationException(__('Library not writable'));
        }
    }

    /** @inheritDoc */
    public function removeTempFiles()
    {
        $libraryTemp = $this->configService->getSetting('LIBRARY_LOCATION') . 'temp';

        if (!is_dir($libraryTemp)) {
            return;
        }

        // Dump the files in the temp folder
        foreach (scandir($libraryTemp) as $item) {
            if ($item == '.' || $item == '..')
                continue;

            // Has this file been written to recently?
            if (filemtime($libraryTemp . DIRECTORY_SEPARATOR . $item) > Carbon::now()->subSeconds(86400)->format('U')) {
                $this->log->debug('Skipping active file: ' . $item);
                continue;
            }

            $this->log->debug('Deleting temp file: ' . $item);

            unlink($libraryTemp . DIRECTORY_SEPARATOR . $item);
        }
    }

    /** @inheritDoc */
    public function removeExpiredFiles()
    {
        // Get a list of all expired files and delete them
        foreach ($this->mediaFactory->query(null, array('expires' => Carbon::now()->format('U'), 'allModules' => 1, 'length' => 100)) as $entry) {
            /* @var \Xibo\Entity\Media $entry */
            // If the media type is a module, then pretend its a generic file
            $this->log->info(sprintf('Removing Expired File %s', $entry->name));
            $this->log->audit('Media', $entry->mediaId, 'Removing Expired', ['mediaId' => $entry->mediaId, 'name' => $entry->name, 'expired' => Carbon::createFromTimestamp($entry->expires)->format(DateFormatHelper::getSystemFormat())]);
            $this->getDispatcher()->dispatch(MediaDeleteEvent::$NAME, new MediaDeleteEvent($entry));
            $entry->delete();
        }
    }
}
