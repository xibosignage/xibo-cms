<?php

namespace Xibo\Factory;

use Xibo\Entity\Font;
use Xibo\Service\ConfigServiceInterface;
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

    public function getFontsSizeAndCount()
    {
        return $this->getStore()->select('SELECT IFNULL(SUM(size), 0) AS SumSize, COUNT(*) AS totalCount FROM `fonts`', [])[0];
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
