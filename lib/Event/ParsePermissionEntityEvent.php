<?php


namespace Xibo\Event;


class ParsePermissionEntityEvent extends Event
{
    public static $NAME = 'parse.permission.entity.event.';
    /**
     * @var string
     */
    private $entity;
    /**
     * @var int
     */
    private $objectId;
    private $object;

    public function __construct(string $entity, int $objectId)
    {
        $this->entity = $entity;
        $this->objectId = $objectId;
    }

    public function getEntity()
    {
        return $this->entity;
    }

    public function getObjectId()
    {
        return $this->objectId;
    }

    public function setObject($object)
    {
        $this->object = $object;
    }

    public function getObject()
    {
        return $this->object;
    }
}
