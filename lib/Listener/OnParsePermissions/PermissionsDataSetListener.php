<?php


namespace Xibo\Listener\OnParsePermissions;


use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\DataSetFactory;

class PermissionsDataSetListener
{
    /**
     * @var DataSetFactory
     */
    private $dataSetFactory;

    public function __construct(DataSetFactory $dataSetFactory)
    {
        $this->dataSetFactory = $dataSetFactory;
    }

    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->dataSetFactory->getById($event->getObjectId()));
    }
}
