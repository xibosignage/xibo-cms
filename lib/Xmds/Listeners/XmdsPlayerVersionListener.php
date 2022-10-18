<?php

namespace Xibo\Xmds\Listeners;

use Xibo\Event\XmdsDependencyListEvent;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Listener\ListenerLoggerTrait;

class XmdsPlayerVersionListener
{
    use ListenerLoggerTrait;

    /**
     * @var PlayerVersionFactory
     */
    private $playerVersionFactory;

    public function __construct(PlayerVersionFactory $playerVersionFactory)
    {
        $this->playerVersionFactory = $playerVersionFactory;
    }

    public function onDependencyList(XmdsDependencyListEvent $event)
    {
        $this->getLogger()->debug('onDependencyList: XmdsPlayerVersionListener');

        if ($event->getPlayerVersion() !== null) {
            $version = $this->playerVersionFactory->getById($event->getPlayerVersion());
            $event->addDependency(
                'playersoftware',
                $version->versionId,
                'playersoftware/'.$version->fileName,
                $version->size,
                $version->md5,
                true
            );
        }
    }

    public function onDependencyRequest(XmdsDependencyRequestEvent $event)
    {
        $this->getLogger()->debug('onDependencyRequest: XmdsPlayerVersionListener');

        if ($event->getFileType() === 'playersoftware') {
            $version = $this->playerVersionFactory->getById($event->getId());
            $event->setRelativePathToLibrary('/playersoftware/' . $version->fileName);
        }
    }
}
