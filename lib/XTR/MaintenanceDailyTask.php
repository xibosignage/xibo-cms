<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (MaintenanceDailyTask.php)
 */


namespace Xibo\XTR;
use Xibo\Exception\ConfigurationException;
use Xibo\Helper\Environment;

/**
 * Class MaintenanceDailyTask
 * @package Xibo\XTR
 */
class MaintenanceDailyTask implements TaskInterface
{
    use TaskTrait;

    private $hasUpgradeRun = false;

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Daily Maintenance') . PHP_EOL . PHP_EOL;

        // Upgrade
        $this->upgrade();

        if ($this->hasUpgradeRun)
            return;

        // Long running task
        set_time_limit(0);

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

        // API tokens
        $this->purgeExpiredApiTokens();
    }

    /**
     * Upgrade if required
     * @throws ConfigurationException
     */
    private function upgrade()
    {
        // Is there a pending upgrade (i.e. are there any pending upgrade steps).
        if ($this->config->isUpgradePending()) {
            // Flag to indicate we've run upgrade steps
            $this->hasUpgradeRun = true;

            $this->runMessage .= '#' . __('Upgrade') . PHP_EOL;
            $steps = $this->upgradeFactory->getIncomplete();

            if (count($steps) <= 0) {

                // Insert pending upgrade steps.
                $steps = $this->upgradeFactory->createSteps(DBVERSION, Environment::$WEBSITE_VERSION);

                foreach ($steps as $step) {
                    /* @var \Xibo\Entity\Upgrade $step */
                    $step->save();
                }
            }

            // Cycle through the steps until done
            set_time_limit(0);

            $previousStepSetsDbVersion = false;

            foreach ($steps as $upgradeStep) {
                /* @var \Xibo\Entity\Upgrade $upgradeStep */
                if ($previousStepSetsDbVersion) {
                    $this->log->notice('Pausing upgrade to reset version');
                    $this->runMessage .= '#' . __('Upgrade Paused') . PHP_EOL . PHP_EOL;
                    return;
                }

                // Assume success
                $stepFailed = false;

                try {
                    $upgradeStep->doStep();
                    $upgradeStep->complete = 1;

                    // Commit
                    $this->store->commitIfNecessary('upgrade');

                } catch (\Exception $e) {
                    // Failed to run upgrade step
                    $this->log->error('Unable to run upgrade stepId ' . $upgradeStep->stepId . '. Message = ' . $e->getMessage());
                    $this->log->error($e->getTraceAsString());

                    try {
                        $this->store->getConnection('upgrade')->rollBack();
                    } catch (\Exception $exception) {
                        $this->log->error('Unable to rollback. E = ' . $e->getMessage());
                    }

                    $stepFailed = true;
                }

                $upgradeStep->lastTryDate = $this->date->parse()->format('U');
                $upgradeStep->save();

                // Commit the default connection to ensure we persist this upgrade step status change.
                $this->store->commitIfNecessary();

                // if we are a step that updates the version table, then exit
                if ($upgradeStep->type == 'sql' && stripos($upgradeStep->action, 'SET `DBVersion`'))
                    $previousStepSetsDbVersion = true;

                if ($stepFailed)
                    throw new ConfigurationException('Unable to run upgrade step. Aborting Maintenance Task.');
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
                $i = 0;
                $rows = 1;
                $maxAttempts = $this->getOption('statsDeleteMaxAttempts', 10);
                while ($rows > 0) {
                    $i++;

                    $rows = $this->store->update('DELETE FROM `stat` WHERE statDate < :maxage LIMIT 10000', ['maxage' => $maxage]);

                    // Give SQL time to recover
                    if ($rows > 0) {
                        $this->log->debug('Stats delete effected ' . $rows . ' rows, sleeping.');
                        sleep($this->getOption('statsDeleteSleep', 3));
                    }

                    // Break if we've exceeded the maximum attempts.
                    if ($i >= $maxAttempts)
                        break;
                }

                $this->log->debug('Deleted Stats back to ' . $maxage . ' in ' . $i . ' attempts');

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

            $folder = $this->config->uri('layouts', true);

            foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
                if (stripos($file, '.zip')) {
                    $layout = $this->layoutFactory->createFromZip($folder . '/' . $file, null, 1, false, false, true, false, true, $this->app->container->get('\Xibo\Controller\Library')->setApp($this->app));
                    $layout->save([
                        'audit' => false
                    ]);
                }
            }

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

    /**
     * Purge expired API tokens
     */
    private function purgeExpiredApiTokens()
    {
        $this->runMessage .= '## ' . __('Purge Expired API Tokens') . PHP_EOL;

        $params = ['now' => time()];

        try {
            // Run delete SQL for all token and session tables.
            $this->store->update('DELETE FROM `oauth_refresh_tokens` WHERE expire_time < :now', $params);

            $this->store->update('
              DELETE FROM `oauth_sessions` 
               WHERE id IN (
                 SELECT session_id 
                   FROM oauth_access_tokens
                  WHERE expire_time < :now
                    AND access_token NOT IN (SELECT access_token FROM oauth_refresh_tokens)
               )
            ', $params);

            // Delete access_tokens without a refresh token
            $this->store->update('
              DELETE FROM `oauth_access_tokens` 
               WHERE expire_time < :now AND access_token NOT IN (
                  SELECT access_token FROM oauth_refresh_tokens
               )
           ', $params);

            $this->store->update('
              DELETE FROM `oauth_access_token_scopes`
                WHERE access_token NOT IN (
                  SELECT access_token FROM oauth_access_tokens
                )
            ', []);

            // Auth codes
            $this->store->update('
              DELETE FROM `oauth_sessions` 
               WHERE id IN (
                 SELECT session_id 
                   FROM oauth_auth_codes
                  WHERE expire_time < :now
               )
            ', $params);

            $this->store->update('
              DELETE FROM `oauth_auth_codes` WHERE expire_time < :now', $params);

            $this->store->update('
              DELETE FROM `oauth_auth_code_scopes`
                WHERE auth_code NOT IN (
                  SELECT auth_code FROM oauth_auth_codes
                )
            ', []);

            // Delete session scopes
            $this->store->update('
              DELETE FROM `oauth_session_scopes`
                WHERE session_id NOT IN (
                  SELECT id FROM oauth_sessions
                )
            ', []);

            $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;

        } catch (\PDOException $PDOException) {
            $this->log->debug($PDOException->getTraceAsString());
            $this->log->error($PDOException->getMessage());

            $this->runMessage .= ' - ' . __('Error.') . PHP_EOL . PHP_EOL;
        }
    }
}