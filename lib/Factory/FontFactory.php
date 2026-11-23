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

namespace Xibo\Factory;

use FontLib\Exception\FontNotFoundException;
use Xibo\Entity\Font;
use Xibo\Helper\LibraryFile;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class FontFactory extends BaseFactory
{
    /** The player fonts.css, in font storage. */
    public const FONTS_CSS_KEY = 'fonts.css';

    /** Cache key of fonts.css's size and md5. */
    public const FONTS_CSS_METADATA_CACHE_KEY = '/xmds/fontsCss/metadata';

    public function __construct(private readonly LibraryStorageRegistryInterface $libraryStorageRegistry)
    {
    }

    /**
     * Where font files and the player fonts.css are stored, keyed by fileName.
     */
    public function getStorage(): LibraryStorageServiceInterface
    {
        return $this->libraryStorageRegistry->get(LibraryAsset::FONT);
    }

    /**
     * Deletes a font file once the caller's transaction commits, logging rather than throwing a storage error.
     */
    public function deleteFontFileAfterCommit(string $fileName): void
    {
        LibraryFileJournal::deleteFileAfterCommit(
            $this->getStore(),
            $this->libraryStorageRegistry,
            LibraryAsset::FONT,
            $fileName,
            $this->getLog()->getLoggerInterface()
        );
    }

    /**
     * Moves a font file into storage, journalled until the caller's transaction commits.
     * @throws GeneralException
     */
    public function storeFontFile(string $localPath, string $fileName): void
    {
        (new LibraryFileJournal($this->getStore(), $this->getStorage(), LibraryAsset::FONT))
            ->storeLocalFile($localPath, $fileName);
    }

    /**
     * The md5 of each default font Daily Maintenance installs from the project fonts folder, keyed by file name.
     * @return array<string, string>
     */
    public static function getDefaultFonts(): array
    {
        if (!defined('PROJECT_ROOT')) {
            return [];
        }

        $defaults = [];
        foreach (scandir(PROJECT_ROOT . '/fonts') ?: [] as $file) {
            if (is_file(PROJECT_ROOT . '/fonts/' . $file)) {
                $defaults[$file] = md5_file(PROJECT_ROOT . '/fonts/' . $file);
            }
        }
        return $defaults;
    }

    /**
     * @return Font
     */
    public function createEmpty()
    {
        return new Font(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this
        );
    }

    public function getValidExtensions(): array
    {
        return ['otf', 'ttf', 'eot', 'svg', 'woff'];
    }

    /**
     * @throws InvalidArgumentException
     * @throws FontNotFoundException
     */
    public function createFontFromUpload(string $file, string $name, string $fileName, $modifiedBy): Font
    {
        $font = $this->createEmpty();
        $font->fileName = preg_replace('/[^-.\w]/', '-', $fileName);
        LibraryFile::assertNoDoubleDot($font->fileName);

        $fontLib = \FontLib\Font::load($file);

        // check embed flag
        $embed = intval($fontLib->getData('OS/2', 'fsType'));

        // if it's not embeddable, throw exception
        if ($embed != 0 && $embed != 8) {
            throw new InvalidArgumentException(__('Font file is not embeddable due to its permissions'));
        }

        $name = ($name == '') ? $fontLib->getFontName() . ' ' . $fontLib->getFontSubfamily() : $name;

        $font->modifiedBy = $modifiedBy;
        $font->name = $name;
        $font->familyName = strtolower(
            preg_replace(
                '/\s+/',
                ' ',
                preg_replace(
                    '/\d+/u',
                    '',
                    $fontLib->getFontName() . ' ' . $fontLib->getFontSubfamily()
                )
            )
        );

        $font->size = filesize($file);
        $font->md5 = md5_file($file);

        return $font;
    }

    /**
     * Adds a font from a layout import, removing the local file if it can't be added.
     * Older versions allowed '..' in font file names, so runs of dots are collapsed rather than refused.
     * @throws GeneralException
     * @throws FontNotFoundException
     */
    public function importFontFile(string $localPath, string $name, string $fileName, string $modifiedBy): Font
    {
        try {
            $font = $this->createFontFromUpload(
                $localPath,
                $name,
                preg_replace('/\.{2,}/', '.', $fileName),
                $modifiedBy
            );
            $font->save();
            $this->storeFontFile($localPath, $font->fileName);
        } catch (\Throwable $throwable) {
            @unlink($localPath);
            throw $throwable;
        }

        return $font;
    }

    /**
     * @param $id
     * @return Font
     * @throws NotFoundException
     */
    public function getById($id): Font
    {
        $fonts = $this->query(null, ['id' => $id]);

        if (count($fonts) <= 0) {
            throw new NotFoundException('Font with id ' . $id . ' not found');
        }

        return $fonts[0];
    }

    /**
     * @param $name
     * @return Font[]
     */
    public function getByName($name): array
    {
        return $this->query(null, ['name' => $name]);
    }

    /**
     * @param $fileName
     * @return Font[]
     */
    public function getByFileName($fileName): array
    {
        return $this->query(null, ['fileName' => $fileName]);
    }

    /**
     * Get the number of fonts and their total size
     * @return mixed
     */
    public function getFontsSizeAndCount(): mixed
    {
        return $this->getStore()->select('
            SELECT IFNULL(SUM(size), 0) AS SumSize, COUNT(*) AS totalCount FROM `fonts`
        ', [])[0];
    }

    /**
     * @param array|null $sortOrder
     * @param array $filterBy
     * @return Font[]
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
    {
        $entries = [];
        $params = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $select = 'SELECT 
            `id`,
            `createdAt`, 
            `modifiedAt`,
            `modifiedBy`,
            `name`,
            `fileName`,
            `familyName`,
            `size`,
            `md5`
        ';

        $body = '
          FROM `fonts`
         WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('id') !== null) {
            $body .= ' AND `fonts`.id = :id ';
            $params['id'] = $sanitizedFilter->getInt('id');
        }

        if ($sanitizedFilter->getString('name') != null) {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $logicalOperator = $sanitizedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'fonts',
                'name',
                $terms,
                $body,
                $params,
                ($sanitizedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($sanitizedFilter->getString('fileName') != null) {
            $body .= ' AND `fonts`.fileName = :fileName ';
            $params['fileName'] = $sanitizedFilter->getString('fileName');
        }

        if ($sanitizedFilter->getString('md5') != null) {
            $body .= ' AND `fonts`.md5 = :md5 ';
            $params['md5'] = $sanitizedFilter->getString('md5');
        }

        // Sorting
        $allowedColumns = [
            'id',
            'name',
            'fileName',
            'familyName',
            'createdAt',
            'modifiedAt',
            'modifiedBy',
            'size',
        ];

        $sortOrder = $this->buildSortQuery(
            $sortOrder,
            $allowedColumns,
            defaultSort: ['name ASC'],
            uniqueColumn: 'id'
        );

        $order = !empty($sortOrder) ? ' ORDER BY ' . implode(', ', $sortOrder) : '';

        $limit = '';

        if ($filterBy !== null &&
            $sanitizedFilter->getInt('start') !== null &&
            $sanitizedFilter->getInt('length') !== null
        ) {
            $limit .= ' LIMIT ' . intval($sanitizedFilter->getInt('start')) . ', ' .
                $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['size']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
