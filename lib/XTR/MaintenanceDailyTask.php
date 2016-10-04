<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (MaintenanceDailyTask.php)
 */


namespace Xibo\XTR;
use Xibo\Exception\ConfigurationException;
use Xibo\Service\ConfigService;

/**
 * Class MaintenanceDailyTask
 * @package Xibo\XTR
 */
class MaintenanceDailyTask implements TaskInterface
{
    use TaskTrait;

    /** @inheritdoc */
    public function run()
    {
        // Long running task
        set_time_limit(0);

        $this->runMessage = '# ' . __('Daily Maintenance') . PHP_EOL . PHP_EOL;

        // Upgrade
        $this->upgrade();

        // Import layouts
        $this->importLayouts();

        // Install module files
        $this->installModuleFiles();

        // Tidy logs
        $this->tidyLogs();

        // Tidy stats
        $this->tidyStats();

        // Tidy Cache
        $this->tidyCache();
    }

    /**
     * Upgrade if required
     * @throws ConfigurationException
     */
    private function upgrade()
    {
        // Is there a pending upgrade (i.e. are there any pending upgrade steps).
        if ($this->config->isUpgradePending()) {
            $this->runMessage .= '#' . __('Upgrade') . PHP_EOL;
            $steps = $this->upgradeFactory->getIncomplete();

            if (count($steps) <= 0) {

                // Insert pending upgrade steps.
                $steps = $this->upgradeFactory->createSteps(DBVERSION, ConfigService::$WEBSITE_VERSION);

                foreach ($steps as $step) {
                    /* @var \Xibo\Entity\Upgrade $step */
                    $step->save();
                }
            }

            // Cycle through the steps until done
            set_time_limit(0);

            foreach ($steps as $upgradeStep) {
                /* @var \Xibo\Entity\Upgrade $upgradeStep */
                try {
                    $upgradeStep->doStep();
                    $upgradeStep->complete = 1;
                    $upgradeStep->lastTryDate = $this->date->parse()->format('U');
                    $upgradeStep->save();
                }
                catch (\Exception $e) {
                    $upgradeStep->lastTryDate = $this->date->parse()->format('U');
                    $upgradeStep->save();
                    $this->log->error('Unable to run upgrade step. Message = %s', $e->getMessage());
                    $this->log->error($e->getTraceAsString());

                    throw new ConfigurationException($e->getMessage());
                }
            }

            $this->runMessage .= '#' . __('Upgrade Complete') . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Tidy the DB logs
     */
    private function tidyLogs()
    {
        $this->runMessage .= '## ' . __('Tidy Logs') . PHP_EOL;

        if ($this->config->GetSetting('MAINTENANCE_LOG_MAXAGE') != 0) {

            $maxage = date('Y-m-d H:i:s', time() - (86400 * intval($this->config->GetSetting('MAINTENANCE_LOG_MAXAGE'))));

            try {
                $this->store->update('DELETE FROM `log` WHERE logdate < :maxage', ['maxage' => $maxage]);

                $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
            }
            catch (\PDOException $e) {
                $this->runMessage .= ' - ' . __('Error.') . PHP_EOL . PHP_EOL;
                $this->log->error($e->getMessage());
            }
        }
        else {
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Tidy Stats
     */
    private function tidyStats()
    {
        $this->runMessage .= '## ' . __('Tidy Stats') . PHP_EOL;

        if ($this->config->GetSetting('MAINTENANCE_STAT_MAXAGE') != 0) {

            $maxage = date('Y-m-d H:i:s', time() - (86400 * intval($this->config->GetSetting('MAINTENANCE_STAT_MAXAGE'))));

            try {
                $this->store->update('DELETE FROM `stat` WHERE statDate < :maxage', ['maxage' => $maxage]);

                $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
            }
            catch (\PDOException $e) {
                $this->runMessage .= ' - ' . __('Error.') . PHP_EOL . PHP_EOL;
                $this->log->error($e->getMessage());
            }
        }
        else {
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Tidy Cache
     */
    private function tidyCache()
    {
        $this->runMessage .= '## ' . __('Tidy Cache') . PHP_EOL;
        $this->pool->purge();
        $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
    }

    /**
     * Import Layouts
     */
    private function importLayouts()
    {
        $this->runMessage .= '## ' . __('Import Layouts') . PHP_EOL;

        if (!$this->config->isUpgradePending() && $this->config->GetSetting('DEFAULTS_IMPORTED') == 0) {

            $folder = PROJECT_ROOT . '/web/' . $this->config->uri('layouts', true);

            foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
                if (stripos($file, '.zip')) {
                    $layout = $this->layoutFactory->createFromZip($folder . '/' . $file, null, 1, false, false, true, false, true, $this->app->container->get('\Xibo\Controller\Library')->setApp($this->app));
                    $layout->save([
                        'audit' => false
                    ]);
                }
            }

            // Install files
            $this->app->container->get('\Xibo\Controller\Library')->installAllModuleFiles();

            $this->config->ChangeSetting('DEFAULTS_IMPORTED', 1);

            $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
        } else {
            $this->runMessage .= ' - ' . __('Not Required.') . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Install Module Files
     */
    private function installModuleFiles()
    {
        /** @var \Xibo\Controller\Library $libraryController */
        $libraryController = $this->app->container->get('\Xibo\Controller\Library');
        $libraryController->installAllModuleFiles();
    }
}