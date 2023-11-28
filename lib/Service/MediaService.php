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
namespace Xibo\Service;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Mimey\MimeTypes;
use Stash\Interfaces\PoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\FontFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Environment;
use Xibo\Helper\SanitizerService;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\LibraryFullException;

/**
 * MediaService
 */
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

    /** @var EventDispatcherInterface */
    private $dispatcher;
    /**
     * @var FontFactory
     */
    private $fontFactory;

    /** @inheritDoc */
    public function __construct(
        ConfigServiceInterface $configService,
        LogServiceInterface $logService,
        StorageServiceInterface $store,
        SanitizerService $sanitizerService,
        PoolInterface $pool,
        MediaFactory $mediaFactory,
        FontFactory $fontFactory
    ) {
        $this->configService = $configService;
        $this->log = $logService;
        $this->store = $store;
        $this->sanitizerService = $sanitizerService;
        $this->pool = $pool;
        $this->mediaFactory = $mediaFactory;
        $this->fontFactory = $fontFactory;
    }

    /** @inheritDoc */
    public function setUser(User $user) : MediaServiceInterface
    {
        $this->user = $user;
        return $this;
    }

    /** @inheritDoc */
    public function getUser() : User
    {
        return $this->user;
    }

    public function getPool() : PoolInterface
    {
        return $this->pool;
    }

    /** @inheritDoc */
    public function setDispatcher(EventDispatcherInterface $dispatcher): MediaServiceInterface
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    /** @inheritDoc */
    public function libraryUsage(): int
    {
        $results = $this->store->select('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', []);

        return $this->sanitizerService->getSanitizer($results[0])->getInt('SumSize');
    }

    /** @inheritDoc */
    public function initLibrary(): MediaServiceInterface
    {
        MediaService::ensureLibraryExists($this->configService->getSetting('LIBRARY_LOCATION'));
        return $this;
    }

    /** @inheritDoc */
    public function checkLibraryOrQuotaFull($isCheckUser = false): MediaServiceInterface
    {
        // Check that we have some space in our library
        $librarySizeLimit = $this->configService->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
        $librarySizeLimitMB = round(($librarySizeLimit / 1024) / 1024, 2);

        if ($librarySizeLimit > 0 && $this->libraryUsage() > $librarySizeLimit) {
            throw new LibraryFullException(sprintf(__('Your library is full. Library Limit: %s MB'), $librarySizeLimitMB));
        }

        if ($isCheckUser) {
            $this->getUser()->isQuotaFullByUser();
        }

        return $this;
    }

    /** @inheritDoc */
    public function checkMaxUploadSize($size): MediaServiceInterface
    {
        if (ByteFormatter::toBytes(Environment::getMaxUploadSize()) < $size) {
            throw new InvalidArgumentException(
                sprintf(__('This file size exceeds your environment Max Upload Size %s'), Environment::getMaxUploadSize()),
                'size'
            );
        }
        return $this;
    }

    /** @inheritDoc */
    public function getDownloadInfo($url): array
    {
        $downloadInfo = [];
        $guzzle = new Client($this->configService->getGuzzleProxy());

        // first try to get the extension from pathinfo
        $info = pathinfo(parse_url($url, PHP_URL_PATH));
        $extension = $info['extension'] ?? '';
        $size = -1;

        try {
            $head = $guzzle->head($url);

            // First chance at getting the content length so that we can fail early.
            // Will fail for downloads with redirects.
            if ($head->hasHeader('Content-Length')) {
                $contentLength = $head->getHeader('Content-Length');

                foreach ($contentLength as $value) {
                    $size = $value;
                }
            }

            if (empty($extension)) {
                $contentType = $head->getHeaderLine('Content-Type');

                $extension = $contentType;

                if ($contentType === 'binary/octet-stream' && $head->hasHeader('x-amz-meta-filetype')) {
                    $amazonContentType = $head->getHeaderLine('x-amz-meta-filetype');
                    $extension = $amazonContentType;
                }

                // get the extension corresponding to the mime type
                $mimeTypes = new MimeTypes();
                $extension = $mimeTypes->getExtension($extension);
            }
        } catch (RequestException $e) {
            $this->log->debug('Upload from url head request failed for URL ' . $url
                . ' with following message ' . $e->getMessage());
        }

        $downloadInfo['size'] = $size;
        $downloadInfo['extension'] = $extension;
        $downloadInfo['filename'] = $info['filename'];

        return $downloadInfo;
    }

    /** @inheritDoc */
    public function updateFontsCss()
    {
        // delete local cms fonts.css from cache
        $this->pool->deleteItem('localFontCss');

        $this->log->debug('Regenerating player fonts.css file');

        // Go through all installed fonts each time and regenerate.
        $fontTemplate = '@font-face {
    font-family: \'[family]\';
    src: url(\'[url]\');
}';

        // Save a fonts.css file to the library for use as a module
        $fonts = $this->fontFactory->query();

        $css = '';

        // Check the library exists
        $libraryLocation = $this->configService->getSetting('LIBRARY_LOCATION');
        MediaService::ensureLibraryExists($this->configService->getSetting('LIBRARY_LOCATION'));

        // Build our font strings.
        foreach ($fonts as $font) {
            // Css for the player contains the actual stored as location of the font.
            $css .= str_replace('[url]', $font->fileName, str_replace('[family]', $font->familyName, $fontTemplate));
        }

        // If we're a full regenerate, we want to also update the fonts.css file.
        $existingLibraryFontsCss = '';
        if (file_exists($libraryLocation . 'fonts/fonts.css')) {
            $existingLibraryFontsCss = file_get_contents($libraryLocation . 'fonts/fonts.css');
        }

        $tempFontsCss = $libraryLocation . 'temp/fonts.css';
        file_put_contents($tempFontsCss, $css);
        // Check to see if the existing file is different from the new one
        if ($existingLibraryFontsCss == '' || md5($existingLibraryFontsCss) !== md5($tempFontsCss)) {
            $this->log->info('Detected change in fonts.css file, dropping the Display cache');
            rename($tempFontsCss, $libraryLocation . 'fonts/fonts.css');
            // Clear the display cache
            $this->pool->deleteItem('/display');
        } else {
            @unlink($tempFontsCss);
            $this->log->debug('Newly generated fonts.css is the same as the old file. Ignoring.');
        }
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

        if (!file_exists($libraryFolder . '/thumbs')) {
            mkdir($libraryFolder . '/thumbs', 0777, true);
        }

        if (!file_exists($libraryFolder . '/fonts')) {
            mkdir($libraryFolder . '/fonts', 0777, true);
        }

        if (!file_exists($libraryFolder . '/playersoftware')) {
            mkdir($libraryFolder . '/playersoftware', 0777, true);
        }

        if (!file_exists($libraryFolder . '/savedreport')) {
            mkdir($libraryFolder . '/savedreport', 0777, true);
        }

        if (!file_exists($libraryFolder . '/assets')) {
            mkdir($libraryFolder . '/assets', 0777, true);
        }

        if (!file_exists($libraryFolder . '/data_connectors')) {
            mkdir($libraryFolder . '/data_connectors', 0777, true);
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
            if ($item == '.' || $item == '..') {
                continue;
            }

            // Path
            $filePath = $libraryTemp . DIRECTORY_SEPARATOR . $item;

            if (is_dir($filePath)) {
                $this->log->debug('Skipping folder: ' . $item);
                continue;
            }

            // Has this file been written to recently?
            if (filemtime($filePath) > Carbon::now()->subSeconds(86400)->format('U')) {
                $this->log->debug('Skipping active file: ' . $item);
                continue;
            }

            $this->log->debug('Deleting temp file: ' . $item);

            unlink($filePath);
        }
    }

    /** @inheritDoc */
    public function removeExpiredFiles()
    {
        // Get a list of all expired files and delete them
        foreach ($this->mediaFactory->query(
            null,
            [
                'expires' => Carbon::now()->format('U'),
                'allModules' => 1,
                'unlinkedOnly' => 1,
                'length' => 100,
            ]
        ) as $entry) {
            // If the media type is a module, then pretend it's a generic file
            $this->log->info(sprintf('Removing Expired File %s', $entry->name));
            $this->log->audit(
                'Media',
                $entry->mediaId,
                'Removing Expired',
                [
                    'mediaId' => $entry->mediaId,
                    'name' => $entry->name,
                    'expired' => Carbon::createFromTimestamp($entry->expires)
                        ->format(DateFormatHelper::getSystemFormat())
                ]
            );
            $this->dispatcher->dispatch(new MediaDeleteEvent($entry), MediaDeleteEvent::$NAME);
            $entry->delete();
        }
    }
}
