<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CommandFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Command;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class CommandFactory
 * @package Xibo\Factory
 */
class CommandFactory extends BaseFactory
{
    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $permissionFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
        $this->permissionFactory = $permissionFactory;
    }

    /**
     * Create Command
     * @return Command
     */
    public function create()
    {
        return new Command($this->getStore(), $this->getLog(), $this->permissionFactory);
    }

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

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();

        if ($sortOrder == null)
            $sortOrder = ['command'];

        $params = array();
        $select = 'SELECT `command`.commandId, `command`.command, `command`.code, `command`.description, `command`.userId ';

        if ($this->getSanitizer()->getInt('displayProfileId', $filterBy) !== null) {
            $select .= ', commandString, validationString ';
        }

        $select .= " , (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                          FROM `permission`
                            INNER JOIN `permissionentity`
                            ON `permissionentity`.entityId = permission.entityId
                            INNER JOIN `group`
                            ON `group`.groupId = `permission`.groupId
                         WHERE entity = :permissionEntityForGroup
                            AND objectId = command.commandId
                            AND view = 1
                        ) AS groupsWithPermissions ";
        $params['permissionEntityForGroup'] = 'Xibo\\Entity\\Command';

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

        $this->viewPermissionSql('Xibo\Entity\Command', $body, $params, 'command.commandId', 'command.userId', $filterBy);

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
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = (new Command($this->getStore(), $this->getLog(), $this->displayProfileFactory))->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['permissionEntityForGroup']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}