<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Service;

use Psr\Log\LoggerInterface;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\FontFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\UserFactory;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Exception\ValueTooLargeException;

/**
 * Imports the stock layouts and default fonts once, on a new install.
 */
class DefaultsImporter
{
    private const DEFAULT_LAYOUT_ZIP = 'default-layout.zip';

    public function __construct(
        private readonly ConfigServiceInterface $config,
        private readonly StorageServiceInterface $store,
        private readonly LayoutFactory $layoutFactory,
        private readonly UserFactory $userFactory,
        private readonly DataSetFactory $dataSetFactory,
        private readonly FontFactory $fontFactory,
        private readonly MediaServiceInterface $mediaService,
        private readonly DefaultLayoutBuilder $defaultLayoutBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Whether the defaults still need importing.
     */
    public function isRequired(): bool
    {
        return $this->config->getSetting('DEFAULTS_IMPORTED') == 0;
    }

    /**
     * Builds the Default Layout from the brand in $brandFolder, imports the layouts in its layouts folder and the
     * default fonts, skipping unusable zips and throwing other errors.
     * @param bool $skipFailures log and skip any layout or font that fails, as the daily maintenance always has
     * @throws GeneralException|\FontLib\Exception\FontNotFoundException
     */
    public function import(string $brandFolder, bool $skipFailures = false): void
    {
        $this->mediaService->initLibrary();

        // A missing folder is a deployment fault, so the import isn't recorded as done without its layouts.
        $brandFolder = rtrim($brandFolder, '/');
        $layoutsFolder = $brandFolder . '/layouts';
        if (!is_dir($layoutsFolder)) {
            throw new GeneralException('Layout folder not found: ' . $layoutsFolder);
        }

        // The Default Layout is built from the brand, so a brand's own zip of it is ignored.
        $zipFiles = [];
        foreach (array_diff(scandir($layoutsFolder), ['..', '.']) as $file) {
            if (strcasecmp($file, self::DEFAULT_LAYOUT_ZIP) === 0) {
                $this->logger->info('Ignoring ' . $file . '; the Default Layout is built from the brand.');
            } else if (stripos($file, '.zip')) {
                $zipFiles[] = $layoutsFolder . '/' . $file;
            }
        }

        $built = $this->buildDefaultLayout($brandFolder, $zipFiles, $skipFailures);

        foreach ($zipFiles as $zipFile) {
            $this->importLayout($zipFile, $skipFailures);
        }

        $this->importFonts($skipFailures);

        // A failed build leaves the import to be retried, so the Default Layout is built once the fault is fixed.
        if (!$built) {
            $this->logger->error('The Default Layout could not be built, so the defaults import will be retried.');
            return;
        }

        $this->config->changeSetting('DEFAULTS_IMPORTED', 1);
    }

    /**
     * Stores a copy of a default font, since storing moves its source out of the project folder on Local.
     * @throws GeneralException
     */
    public function storeDefaultFont(string $file): void
    {
        $tempPath = $this->config->getSetting('LIBRARY_LOCATION') . 'temp/font_' . bin2hex(random_bytes(8)) . '_'
            . $file;

        try {
            if (!copy(PROJECT_ROOT . '/fonts/' . $file, $tempPath)) {
                throw new GeneralException('Unable to copy to ' . $tempPath);
            }
            $this->fontFactory->storeFontFile($tempPath, $file);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Builds the Default Layout from the brand, unless an earlier attempt already saved it, and returns whether it
     * exists. It becomes the default unless one is set, or the default is a stock layout from $zipFiles, which an
     * earlier attempt whose build failed made the default in its place.
     * @param string[] $zipFiles
     * @throws GeneralException
     */
    private function buildDefaultLayout(string $brandFolder, array $zipFiles, bool $skipFailures): bool
    {
        $systemUserId = $this->userFactory->getSystemUser()->getId();
        if ($this->hasLayout(DefaultLayoutBuilder::NAME, $systemUserId)) {
            $this->logger->info('Default Layout already built.');
            return true;
        }

        try {
            $layout = $this->defaultLayoutBuilder->build($brandFolder, $systemUserId);
        } catch (\Exception $exception) {
            if (!$skipFailures) {
                throw $exception;
            }
            $this->logger->error('Unable to build the Default Layout. E = ' . $exception->getMessage());
            return false;
        }

        $stockNames = array_filter(array_map(fn (string $zipFile) => $this->getLayoutName($zipFile), $zipFiles));
        $this->useAsDefaultUnlessChosen($layout->layoutId, $stockNames, $systemUserId);
        return true;
    }

    /**
     * @throws GeneralException
     */
    private function importLayout(string $zipFile, bool $skipFailures): void
    {
        $systemUserId = $this->userFactory->getSystemUser()->getId();

        // A retried import skips layouts an earlier attempt already saved.
        $name = $this->getLayoutName($zipFile);
        if ($name !== null && $this->hasLayout($name, $systemUserId)) {
            $this->logger->info('Layout already imported: ' . $zipFile);
            return;
        }

        try {
            $layout = $this->layoutFactory->createFromZip(
                $zipFile,
                null,
                $systemUserId,
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
                $layout->storeThumbnail($layout->getUnmatchedProperty('thumbnail'));
            }

            $this->useAsDefaultUnlessChosen($layout->layoutId);
        } catch (\Exception $exception) {
            if (!$skipFailures && !self::isUnusableZip($exception)) {
                throw $exception;
            }
            $this->logger->error('Unable to import layout: ' . $zipFile . '. E = ' . $exception->getMessage());
        }
    }

    /**
     * Whether $userId already owns a layout named $name.
     */
    private function hasLayout(string $name, int $userId): bool
    {
        return $this->store->exists(
            'SELECT layoutId FROM `layout` WHERE `layout` = :layout AND userId = :userId',
            ['layout' => $name, 'userId' => $userId]
        );
    }

    /**
     * Points the DEFAULT_LAYOUT setting at $layoutId, unless it already points at a layout other than one of the
     * $replaceableNames layouts owned by $systemUserId.
     * @param string[] $replaceableNames
     */
    private function useAsDefaultUnlessChosen(
        int $layoutId,
        array $replaceableNames = [],
        ?int $systemUserId = null
    ): void {
        try {
            $current = $this->layoutFactory->getById((int)$this->config->getSetting('DEFAULT_LAYOUT'));
            $replaceable = count($replaceableNames) > 0
                && (int)$current->getOwnerId() === $systemUserId
                && in_array($current->layout, $replaceableNames, true);
            if (!$replaceable) {
                return;
            }
        } catch (NotFoundException) {
            // No default is set.
        }
        $this->config->changeSetting('DEFAULT_LAYOUT', $layoutId);
    }

    /**
     * Whether a layout import failed on the zip itself, which a retry won't fix.
     */
    private static function isUnusableZip(\Exception $exception): bool
    {
        return $exception instanceof InvalidArgumentException
            || $exception instanceof DuplicateEntityException
            || $exception instanceof NotFoundException
            || $exception instanceof ValueTooLargeException;
    }

    /**
     * The name of the layout in a layout zip, or null when it can't be read.
     */
    private function getLayoutName(string $zipFile): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return null;
        }

        $details = json_decode((string)$zip->getFromName('layout.json'), true);
        $zip->close();

        $name = $details['layoutDefinitions']['layout'] ?? null;
        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Installs the fonts from the project fonts/ directory that aren't installed yet.
     * @throws GeneralException|\FontLib\Exception\FontNotFoundException
     */
    private function importFonts(bool $skipFailures): void
    {
        $fontFolder = PROJECT_ROOT . '/fonts';
        foreach (array_diff(scandir($fontFolder), ['..', '.']) as $file) {
            if (count($this->fontFactory->getByFileName($file)) > 0) {
                continue;
            }

            $filePath = $fontFolder . DIRECTORY_SEPARATOR . $file;
            $fontLib = \FontLib\Font::load($filePath);

            // check embed flag, just in case
            $embed = intval($fontLib->getData('OS/2', 'fsType'));
            // if it's not embeddable, log error and skip it
            if ($embed != 0 && $embed != 8) {
                $this->logger->error('Unable to install default Font: ' . $file
                    . ' . Font file is not embeddable due to its permissions');
                continue;
            }

            $font = $this->fontFactory->createEmpty();
            $font->modifiedBy = $this->userFactory->getSystemUser()->userName;
            $font->name = $fontLib->getFontName() . ' ' . $fontLib->getFontSubfamily();
            $font->familyName = strtolower(preg_replace('/\s+/', ' ', preg_replace('/\d+/u', '', $font->name)));
            $font->fileName = $file;
            $font->size = filesize($filePath);
            $font->md5 = md5_file($filePath);
            $font->save();

            try {
                $this->storeDefaultFont($file);
            } catch (GeneralException $exception) {
                if (!$skipFailures) {
                    throw $exception;
                }
                $this->logger->error('Unable to store default font ' . $file . ': ' . $exception->getMessage());
            }
        }
    }
}
