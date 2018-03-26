<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UpgradeFactory.php)
 */


namespace Xibo\Factory;


use Slim\Helper\Set;
use Xibo\Entity\Upgrade;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Environment;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UpgradeFactory
 * @package Xibo\Factory
 */
class UpgradeFactory extends BaseFactory
{
    private $provisioned = false;

    /** @var  Set */
    private $container;

    /** @var  DateServiceInterface */
    private $date;

    /** @var  ConfigServiceInterface */
    private $config;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param Set $container
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     */
    public function __construct($store, $log, $sanitizerService, $container, $date, $config)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->container = $container;
        $this->date = $date;
        $this->config = $config;
    }

    /**
     * @return Upgrade
     */
    public function createEmpty()
    {
        return new Upgrade($this->getStore(), $this->getLog(), $this->container, $this->config);
    }

    /**
     * Get by Step Id
     * @param $stepId
     * @return Upgrade
     * @throws NotFoundException
     */
    public function getByStepId($stepId)
    {
        $steps = $this->query(null, ['stepId' => $stepId]);

        if (count($steps) <= 0)
            throw new NotFoundException();

        return $steps[0];
    }

    /**
     * Get Incomplete Steps
     * @return array[Upgrade]
     */
    public function getIncomplete()
    {
        return $this->query(null, ['complete' => 0]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Upgrade]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $this->checkAndProvision();

        if ($sortOrder === null)
            $sortOrder = ['stepId'];

        $entries = array();
        $params = array();
        $select = 'SELECT * ';
        $body = ' FROM `upgrade` WHERE 1 = 1 ';

        if ($this->getSanitizer()->getInt('stepId', $filterBy) !== null) {
            $body .= ' AND `upgrade`.stepId = :stepId ';
            $params['stepId'] = $this->getSanitizer()->getInt('stepId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('complete', $filterBy) !== null) {
            $body .= ' AND `upgrade`.complete = :complete ';
            $params['complete'] = $this->getSanitizer()->getInt('complete', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    /**
     * Return a set of Upgrade Objects
     * @param int $from
     * @param int $to
     * @return array[Upgrade]
     */
    public function createSteps($from, $to)
    {
        $this->getLog()->debug('Creating upgrade steps from %d to %d', $from, $to);

        $steps = [];
        $date = $this->date->parse();

        // Go from $from to $to and get the config file from the install folder.
        for ($i = $from + 1; $i <= $to; $i++) {
            $currentStep = PROJECT_ROOT . '/install/steps/' . $i . '.json';
            $this->getLog()->debug('Checking for %s', $currentStep);
            // Get the file
            if (file_exists($currentStep)) {
                $config = json_decode(file_get_contents($currentStep), true);

                foreach ($config['steps'] as $step) {
                    // If the step defines a fixedIn version (i.e. 85)
                    // only run if we are going from a version which is after that
                    if (isset($step['fixedIn']) && $from >= $step['fixedIn'])
                        continue;

                    if (!isset($step['type']))
                        $step['type'] = 'sql';

                    $step['dbVersion'] = $config['dbVersion'];
                    $step['appVersion'] = $config['appVersion'];
                    $step['requestDate'] = $date->format('U');
                    $steps[] = $this->createEmpty()->hydrate($step);
                }
            }

            // Add the version bump
            if ($i == $to) {
                $action = 'UPDATE `version` SET `app_ver` = \'' . Environment::$WEBSITE_VERSION_NAME . '\', `DBVersion` = ' . $to . '; UPDATE `setting` SET `value` = 0 WHERE `setting` = \'PHONE_HOME_DATE\';';
                $steps[] = $this->createEmpty()->hydrate([
                    'dbVersion' => $to,
                    'appVersion' => Environment::$WEBSITE_VERSION_NAME,
                    'step' => 'Finalise Upgrade',
                    'action' => $action,
                    'type' => 'sql'
                ]);
            }
        }

        $this->getLog()->debug('%d steps for upgrade', count($steps));

        return $steps;
    }

    /**
     * Check the table is present
     */
    private function checkAndProvision()
    {
        if ($this->provisioned)
            return;

        // Check if the table exists
        $results = $this->getStore()->select('SHOW TABLES LIKE :table', ['table' => 'upgrade']);

        if (count($results) <= 0)
            $this->createEmpty()->createTable();

        $this->provisioned = true;
    }
}