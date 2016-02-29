<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CommandFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Command;
use Xibo\Exception\NotFoundException;

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

        if ($this->getSanitizer()->getInt('displayProfileId', $filterBy) !== null) {
            $select .= ', commandString, validationString ';
        }

        $body = ' FROM `command` ';

        if ($this->getSanitizer()->getInt('displayProfileId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkcommanddisplayprofile`
                ON `lkcommanddisplayprofile`.commandId = `command`.commandId
                    AND `lkcommanddisplayprofile`.displayProfileId = :displayProfileId
            ';

            $params['displayProfileId'] = $this->getSanitizer()->getInt('displayProfileId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';

        if ($this->getSanitizer()->getInt('commandId', $filterBy) !== null) {
            $body .= ' AND `command`.commandId = :commandId ';
            $params['commandId'] = $this->getSanitizer()->getInt('commandId', $filterBy);
        }

        if ($this->getSanitizer()->getString('command', $filterBy) != null) {
            $body .= ' AND `command`.command = :command ';
            $params['command'] = $this->getSanitizer()->getString('command', $filterBy);
        }

        if ($this->getSanitizer()->getString('code', $filterBy) != null) {
            $body .= ' AND `code`.code = :code ';
            $params['code'] = $this->getSanitizer()->getString('code', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start'), 0) . ', ' . $this->getSanitizer()->getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = (new Command())->hydrate($row)->setApp($this->getApp())->setApp($this->getApp());
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}