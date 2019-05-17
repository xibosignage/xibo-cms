<?php

namespace Xibo\Report;

use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Slim\Http\Request;

trait ReportTrait
{

    /**
     * @var \Xibo\Helper\ApplicationState
     */
    private $state;

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var TimeSeriesStoreInterface
     */
    private $timeSeriesStore;

    /**
     * @var LogServiceInterface
     */
    private $logService;

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var DateServiceInterface
     */
    private $dateService;

    /**
     * @var SanitizerServiceInterface
     */
    private $sanitizerService;

    /**
     * @var Request
     */
    private $request;

    /**
     * Set common dependencies.
     * @param \Xibo\Helper\ApplicationState $state
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     * @return $this
     */
    public function setCommonDependencies($state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer)
    {
        $this->state = $state;
        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->logService = $log;
        $this->configService = $config;
        $this->dateService = $date;
        $this->sanitizerService = $sanitizer;
        return $this;
    }

    /**
     * Get the Application State
     * @return \Xibo\Helper\ApplicationState
     */
    protected function getState()
    {
        return $this->state;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->store;
    }

    /**
     * Get TimeSeriesStore
     * @return TimeSeriesStoreInterface
     */
    protected function getTimeSeriesStore()
    {
        return $this->timeSeriesStore;
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    protected function getLog()
    {
        return $this->logService;
    }

    /**
     * Get Config
     * @return ConfigServiceInterface
     */
    public function getConfig()
    {
        return $this->configService;
    }

    /**
     * Get Date
     * @return DateServiceInterface
     */
    protected function getDate()
    {
        return $this->dateService;
    }

    /**
     * Get Sanitizer
     * @return SanitizerServiceInterface
     */
    protected function getSanitizer()
    {
        return $this->sanitizerService;
    }

    /**
     * Get Request
     * @return Request
     */
    private function getRequest()
    {
        if ($this->request == null)
            throw new \RuntimeException('....... called before Request has been set');

        return $this->request;
    }
}
