<?php


namespace Xibo\Listener;


use Xibo\Event\CommandDeleteEvent;
use Xibo\Factory\DisplayProfileFactory;

class OnCommandDelete
{
    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    public function __construct(DisplayProfileFactory $displayProfileFactory)
    {
        $this->displayProfileFactory = $displayProfileFactory;
    }

    /**
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function __invoke(CommandDeleteEvent $event)
    {
        $command = $event->getCommand();

        foreach ($this->displayProfileFactory->getByCommandId($command->commandId) as $displayProfile) {
            $displayProfile->unassignCommand($command);
            $displayProfile->save(['validate' => false]);
        }
    }
}
