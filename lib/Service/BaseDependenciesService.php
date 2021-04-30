<?php


namespace Xibo\Service;

use Psr\Log\NullLogger;
use Slim\Views\Twig;
use Xibo\Entity\User;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\NullHelpService;
use Xibo\Helper\NullSanitizer;
use Xibo\Helper\NullView;
use Xibo\Helper\SanitizerService;
use Xibo\Storage\PdoStorageService;

class BaseDependenciesService
{
    /**
     * @var LogServiceInterface
     */
    private $log;

    /**
     * @var  SanitizerService
     */
    private $sanitizerService;

    /**
     * @var ApplicationState
     */
    private $state;

    /**
     * @var HelpServiceInterface
     */
    private $helpService;

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Twig
     */
    private $view;

    /**
     * @var PdoStorageService
     */
    private $storageService;

    public function setLogger(LogServiceInterface $logService)
    {
        $this->log = $logService;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        if ($this->log === null) {
            $this->log = new NullLogger();
        }

        return $this->log;
    }

    public function setSanitizer(SanitizerService $sanitizerService)
    {
        $this->sanitizerService = $sanitizerService;
    }

    public function getSanitizer(): SanitizerService
    {
        if ($this->sanitizerService === null) {
            $this->sanitizerService = new NullSanitizer();
        }

        return $this->sanitizerService;
    }

    public function setState(ApplicationState $applicationState)
    {
        $this->state = $applicationState;
    }

    public function getState(): ApplicationState
    {
        return $this->state;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setHelp(HelpService $helpService)
    {
        if ($this->helpService === null) {
            $this->helpService = new NullHelpService();
        }

        $this->helpService = $helpService;
    }

    public function getHelp() : HelpService
    {
        return $this->helpService;
    }

    public function setConfig(ConfigServiceInterface $configService)
    {
        $this->configService = $configService;
    }

    public function getConfig() : ConfigServiceInterface
    {
        return $this->configService;
    }

    public function setView(Twig $view)
    {
        if ($this->view === null) {
            $this->view = new NullView();
        }
        $this->view = $view;
    }

    public function getView() : Twig
    {
        return $this->view;
    }

    public function setStore(PdoStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function getStore()
    {
        return $this->storageService;
    }
}
