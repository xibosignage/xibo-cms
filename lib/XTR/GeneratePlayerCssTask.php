<?php

namespace Xibo\XTR;

use Xibo\Service\MediaServiceInterface;

class GeneratePlayerCssTask implements TaskInterface
{
    use TaskTrait;

    /**
     * @var MediaServiceInterface
     */
    private $mediaService;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->mediaService = $container->get('mediaService');
        return $this;
    }

    public function run()
    {
        $this->runMessage = '# ' . __('Generate Player font css') . PHP_EOL . PHP_EOL;

        $this->mediaService->updateFontsCss();

        $this->runMessage = '# Generated Player Font css file ' . PHP_EOL . PHP_EOL;

        // Disable the task
        $this->appendRunMessage('# Disabling task.');

        $this->getTask()->isActive = 0;
        $this->getTask()->save();

        $this->appendRunMessage(__('Done.'. PHP_EOL));
    }
}
