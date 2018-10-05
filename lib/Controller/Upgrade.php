<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (Upgrade.php) is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Factory\UpgradeFactory;
use Xibo\Helper\Environment;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Upgrade
 * @package Xibo\Controller
 */
class Upgrade extends Base
{
    /** @var  StorageServiceInterface */
    private $store;

    /** @var  UpgradeFactory */
    private $upgradeFactory;

    /** @var  string */
    public $errorMessage;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param Store $store
     * @param UpgradeFactory $upgradeFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $upgradeFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->upgradeFactory = $upgradeFactory;
    }

    /**
     *
     */
    public function displayPage()
    {
        // Assume we will show the upgrade page
        $this->getState()->template = 'upgrade-page';

        // Is there a pending upgrade (i.e. are there any pending upgrade steps).
        $steps = $this->upgradeFactory->getIncomplete();

        if (count($steps) <= 0) {
            // No pending steps, check to see if we need to insert them
            if (!$this->getConfig()->isUpgradePending()) {
                $this->getState()->template = 'upgrade-not-required-page';
                return;
            }

            if ($this->getUser()->userTypeId != 1) {
                $this->getState()->template = 'upgrade-in-progress-page';
                return;
            }

            // Insert pending upgrade steps.
            $steps = $this->upgradeFactory->createSteps(DBVERSION, Environment::$WEBSITE_VERSION);

            foreach ($steps as $step) {
                /* @var \Xibo\Entity\Upgrade $step */
                $step->save();
            }

        }

        // We have pending steps to process, show them in a list
        $this->getState()->setData([
           'steps' => $steps
        ]);
    }

    /**
     * Do Step
     * @param int $stepId
     * @throws \Exception
     */
    public function doStep($stepId)
    {
        // Check we are a super admin
        if (!$this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        // Get upgrade step
        $upgradeStep = $this->upgradeFactory->getByStepId($stepId);

        if ($upgradeStep->complete == 1)
            throw new \InvalidArgumentException(__('Upgrade step already complete'));

        // Assume success
        $stepFailed = false;
        $stepFailedMessage = null;

        try {
            $upgradeStep->doStep();
            $upgradeStep->complete = 1;

            // Commit
            $this->store->commitIfNecessary('upgrade');

        } catch (\Exception $e) {
            // Failed to run upgrade step
            $this->getLog()->error('Unable to run upgrade stepId ' . $upgradeStep->stepId . '. Message = ' . $e->getMessage());
            $this->getLog()->error($e->getTraceAsString());
            $stepFailedMessage = $e->getMessage();

            try {
                $this->store->getConnection('upgrade')->rollBack();
            } catch (\Exception $exception) {
                $this->getLog()->error('Unable to rollback. E = ' . $e->getMessage());
            }

            $stepFailed = true;
        }

        $upgradeStep->lastTryDate = $this->getDate()->parse()->format('U');
        $upgradeStep->save();

        if ($stepFailed) {
            // Commit the default connection before we raise an error.
            // the framework won't commit if we don't.
            $this->store->commitIfNecessary();

            // Throw the exception
            throw new ConfigurationException($stepFailedMessage);
        }

        // Are we on the last step?
        if (count($this->upgradeFactory->getIncomplete()) <= 0) {
            // Clear all Task statuses
            $this->store->update('UPDATE `task` SET `status` = 0 WHERE `status` = 1;', []);

            // Install all module files if we are on the last step
            $this->getApp()->container->get('\Xibo\Controller\Library')->installAllModuleFiles();

            // Attempt to delete the install/index.php file
            if (file_exists(PROJECT_ROOT . '/web/install/index.php') && !unlink(PROJECT_ROOT . '/web/install/index.php'))
                $this->getLog()->critical('Unable to delete install.php file after upgrade');
        }


        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Complete')
        ]);
    }

    /**
     * Skips a step
     * @param $stepId
     */
    public function skipStep($stepId)
    {
        // Check we are a super admin
        if (!$this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        // Get upgrade step
        $upgradeStep = $this->upgradeFactory->getByStepId($stepId);

        if ($upgradeStep->complete == 1)
            throw new \InvalidArgumentException(__('Upgrade step already complete'));

        $this->getLog()->critical('Upgrade step skipped. id = ' . $stepId);

        $upgradeStep->complete = 2;
        $upgradeStep->save();
    }
}