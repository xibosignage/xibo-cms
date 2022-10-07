<?php

namespace Xibo\Service;

use Xibo\Helper\ApplicationState;
use Xibo\Helper\UploadHandler;

class UploadService
{
    /** @var  array */
    private $settings;

    /** @var  LogServiceInterface */
    private $logger;
    /** @var ApplicationState */
    private $state;
    /**
     * AnalyticsService constructor.
     * @param array $settings
     * @param LogServiceInterface $logger
     * @param ApplicationState $state
     */
    public function __construct(
        array $settings,
        LogServiceInterface $logger,
        ApplicationState $state
    ) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->state = $state;
    }

    /**
     * Create a new upload handler
     * @param array $errors
     * @return UploadHandler
     */
    public function createUploadHandler($errors = [])
    {
        $options = array_merge([
            'download_via_php' => true,
        ], $this->settings);

        // Blue imp requires an extra /
        $handler = new UploadHandler($options, false, $errors);

        return $handler
            ->setLogger($this->logger)
            ->setState($this->state);
    }
}
