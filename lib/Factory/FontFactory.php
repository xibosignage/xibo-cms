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

namespace Xibo\Factory;

use FontLib\Exception\FontNotFoundException;
use Xibo\Entity\Font;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class FontFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    public function __construct(ConfigServiceInterface $configService)
    {
        $this->config = $configService;
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
            $this->config
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws FontNotFoundException
     */
    public function createFontFromUpload(string $file, string $name, string $fileName, $modifiedBy): Font
    {
        $font = $this->createEmpty();
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

        $font->fileName = preg_replace('/[^-.\w]/', '-', $fileName);
        $font->size = filesize($file);
        $font->md5 = md5_file($file);
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
    public function getByName($name)
    {
        return $this->query(null, ['name' => $name]);
    }

    /**
     * @param $fileName
     * @return Font[]
     */
    public function getByFileName($fileName)
    {
        return $this->query(null, ['fileName' => $fileName]);
    }

    /**
     * Get the number of fonts and their total size
     * @return mixed
     */
    public function getFontsSizeAndCount()
    {
        return $this->getStore()->select('
            SELECT IFNULL(SUM(size), 0) AS SumSize, COUNT(*) AS totalCount FROM `fonts`
        ', [])[0];
    }

    /**
     * @param $sortOrder
     * @param $filterBy
     * @return Font[]
     */
    public function query($sortOrder = null, $filterBy = [])
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
            $body .= ' AND `fonts`.name = :name ';
            $params['name'] = $sanitizedFilter->getString('name');
        }

        if ($sanitizedFilter->getString('fileName') != null) {
            $body .= ' AND `fonts`.fileName = :fileName ';
            $params['fileName'] = $sanitizedFilter->getString('fileName');
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit .= ' LIMIT ' . intval($sanitizedFilter->getInt('start')) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
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
