<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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


namespace Xibo\Factory;


use Xibo\Entity\DataSetRss;
use Xibo\Support\Exception\NotFoundException;

class DataSetRssFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param \Xibo\Entity\User $user
     * @param UserFactory $userFactory
     */
    public function __construct($user, $userFactory)
    {
        $this->setAclDependencies($user, $userFactory);
    }

    public function createEmpty()
    {
        return new DataSetRss($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Get DataSets by ID
     * @param $id
     * @return DataSetRss
     * @throws NotFoundException
     */
    public function getById($id)
    {
        $feeds = $this->query(null, ['disableUserCheck' => 1, 'id' => $id]);

        if (count($feeds) <= 0) {
            throw new NotFoundException();
        }

        return $feeds[0];
    }

    /**
     * Get DataSets by PSK
     * @param $psk
     * @return DataSetRss
     * @throws NotFoundException
     */
    public function getByPsk($psk)
    {
        $feeds = $this->query(null, ['disableUserCheck' => 1, 'psk' => $psk]);

        if (count($feeds) <= 0) {
            throw new NotFoundException();
        }

        return $feeds[0];
    }

    /**
     * @param $sortOrder
     * @param $filterBy
     * @return DataSetRss[]
     * @throws NotFoundException
     */
    public function query($sortOrder, $filterBy)
    {
        $entries = [];
        $params = [];

        $sanitizedFilter = $this->getSanitizer($filterBy);

        $select  = '
          SELECT `datasetrss`.id,
            `datasetrss`.dataSetId,
            `datasetrss`.psk,
            `datasetrss`.title,
            `datasetrss`.author,
            `datasetrss`.titleColumnId,
            `datasetrss`.summaryColumnId,
            `datasetrss`.contentColumnId,
            `datasetrss`.publishedDateColumnId,
            `datasetrss`.sort,
            `datasetrss`.filter
        ';

        $body = '
              FROM `datasetrss`
                INNER JOIN `dataset`
                ON `dataset`.dataSetId = `datasetrss`.dataSetId
             WHERE 1 = 1
        ';

        if ($sanitizedFilter->getInt('id') !== null) {
            $body .= ' AND `datasetrss`.id = :id ';
            $params['id'] = $sanitizedFilter->getInt('id');
        }

        if ($sanitizedFilter->getInt('dataSetId') !== null) {
            $body .= ' AND `datasetrss`.dataSetId = :dataSetId ';
            $params['dataSetId'] = $sanitizedFilter->getInt('dataSetId');
        }

        if ($sanitizedFilter->getString('psk') !== null) {
            $body .= ' AND `datasetrss`.psk = :psk ';
            $params['psk'] = $sanitizedFilter->getString('psk');
        }

        if ($sanitizedFilter->getString('title', $filterBy) != null) {
            $terms = explode(',', $sanitizedFilter->getString('title'));
            $this->nameFilter('datasetrss', 'title', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\DataSet', $body, $params, '`datasetrss`.dataSetId', '`dataset`.userId', $filterBy);

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => ['id']
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}