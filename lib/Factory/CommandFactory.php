<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CommandFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Command;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class CommandFactory extends BaseFactory
{
    /**
     * Get by Id
     * @param $commandId
     * @return Command
     * @throws NotFoundException
     */
    public function getById($commandId)
    {
        $commands = $this->query(null, ['commandId' => $commandId]);

        if (count($commands) <= 0)
            throw new NotFoundException();

        return $commands[0];
    }
    /**
     * Get by Display Profile Id
     * @param $displayProfileId
     * @return array[Command]
     */
    public function getByDisplayProfileId($displayProfileId)
    {
        return $this->query(null, ['displayProfileId' => $displayProfileId]);
    }

    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        if ($sortOrder == null)
            $sortOrder = ['command'];

        $params = array();
        $select = 'SELECT `command`.commandId, `command`.command, `command`.code, `command`.description, `command`.userId ';

        if (Sanitize::getInt('displayProfileId', $filterBy) !== null) {
            $select .= ', commandString, validationString ';
        }

        $body = ' FROM `command` ';

        if (Sanitize::getInt('displayProfileId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkcommanddisplayprofile`
                ON `lkcommanddisplayprofile`.commandId = `command`.commandId
                    AND `lkcommanddisplayprofile`.displayProfileId = :displayProfileId
            ';

            $params['displayProfileId'] = Sanitize::getInt('displayProfileId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';

        if (Sanitize::getInt('commandId', $filterBy) !== null) {
            $body .= ' AND `command`.commandId = :commandId ';
            $params['commandId'] = Sanitize::getInt('commandId', $filterBy);
        }

        if (Sanitize::getString('command', $filterBy) != null) {
            $body .= ' AND `command`.command = :command ';
            $params['command'] = Sanitize::getString('command', $filterBy);
        }

        if (Sanitize::getString('code', $filterBy) != null) {
            $body .= ' AND `code`.code = :code ';
            $params['code'] = Sanitize::getString('code', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;



        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Command())->hydrate($row)->setApp($this->getApp())->setApp($this->getApp());
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}