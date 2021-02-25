<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (MaintenanceDailyTask.php)
 */


namespace Xibo\XTR;
use Xibo\Controller\Library;
use Xibo\Exception\XiboException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\UserFactory;

/**
 * Class MaintenanceDailyTask
 * @package Xibo\XTR
 */
class MaintenanceDailyTask implements TaskInterface
{
    use TaskTrait;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @var Library */
    private $libraryController;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->libraryController = $container->get('\Xibo\Controller\Library');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->userFactory = $container->get('userFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Daily Maintenance') . PHP_EOL . PHP_EOL;

        // Long running task
        set_time_limit(0);

        // Import layouts
        $this->importLayouts();

        // Install module files
        $this->installModuleFiles();

        // Tidy logs
        $this->tidyLogs();

        // Tidy Cache
        $this->tidyCache();

        // API tokens
        $this->purgeExpiredApiTokens();
    }

    /**
     * Tidy the DB logs
     */
    private function tidyLogs()
    {
        $this->runMessage .= '## ' . __('Tidy Logs') . PHP_EOL;

        if ($this->config->getSetting('MAINTENANCE_LOG_MAXAGE') != 0) {

            $maxage = date('Y-m-d H:i:s', time() - (86400 * intval($this->config->getSetting('MAINTENANCE_LOG_MAXAGE'))));

            try {
                $this->store->update('DELETE FROM `log` WHERE logdate < :maxage', ['maxage' => $maxage]);

                $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
            } catch (\PDOException $e) {
                $this->runMessage .= ' - ' . __('Error.') . PHP_EOL . PHP_EOL;
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
     * @throws XiboException
     */
    private function importLayouts()
    {
        $this->runMessage .= '## ' . __('Import Layouts') . PHP_EOL;

        if ($this->config->getSetting('DEFAULTS_IMPORTED') == 0) {

            $folder = $this->config->uri('layouts', true);

            foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
                if (stripos($file, '.zip')) {
                    $layout = $this->layoutFactory->createFromZip(
                        $folder . '/' . $file,
                        null,
                        $this->userFactory->getSystemUser()->getId(),
                        false,
                        false,
                        true,
                        false,
                        true,
                        $this->libraryController,
                        null
                    );

                    $layout->save([
                        'audit' => false,
                        'import' => true
                    ]);
                }
            }

            $this->config->changeSetting('DEFAULTS_IMPORTED', 1);

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
        $this->libraryController->installAllModuleFiles();
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