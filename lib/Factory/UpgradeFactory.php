<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UpgradeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Upgrade;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class UpgradeFactory extends BaseFactory
{
    private $provisioned = false;

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
    public function query($sortOrder = null, $filterBy = null)
    {
        $this->checkAndProvision();

        if ($sortOrder === null)
            $sortOrder = ['stepId'];

        $entries = array();
        $params = array();
        $select = 'SELECT * ';
        $body = ' FROM `upgrade` WHERE 1 = 1 ';

        if (Sanitize::getInt('stepId', $filterBy) !== null) {
            $body .= ' AND `upgrade`.stepId = :stepId ';
            $params['stepId'] = Sanitize::getInt('stepId', $filterBy);
        }

        if (Sanitize::getInt('complete', $filterBy) !== null) {
            $body .= ' AND `upgrade`.complete = :complete ';
            $params['complete'] = Sanitize::getInt('complete', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;



        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Upgrade())->hydrate($row)->setApp($this->getApp());
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
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
        Log::debug('Creating upgrade steps from %d to %d', $from, $to);

        $steps = [];
        $date = Date::parse();

        // Go from $from to $to and get the config file from the install folder.
        for ($i = $from + 1; $i <= $to; $i++) {
            $currentStep = PROJECT_ROOT . '/install/steps/' . $i . '.json';
            Log::debug('Checking for %s', $currentStep);
            // Get the file
            if (file_exists($currentStep)) {
                $config = json_decode(file_get_contents($currentStep), true);

                foreach ($config['steps'] as $step) {
                    // If the step defines a from version (i.e. 85) and we are going from a version
                    // greater than that, we skip as we have corrected the original statement
                    if (isset($step['fixedIn']) && $step['fixedIn'] <= $from)
                        continue;

                    if (!isset($step['type']))
                        $step['type'] = 'sql';

                    $step['dbVersion'] = $config['dbVersion'];
                    $step['appVersion'] = $config['appVersion'];
                    $step['requestDate'] = $date->format('U');
                    $steps[] = (new Upgrade())->hydrate($step);
                }
            }

            // Add the version bump
            if ($i == $to) {
                $action = 'UPDATE `version` SET `app_ver` = \'' . WEBSITE_VERSION_NAME . '\', `DBVersion` = ' . $to . '; UPDATE `setting` SET `value` = 0 WHERE `setting` = \'PHONE_HOME_DATE\';';
                $steps[] = (new Upgrade())->hydrate([
                    'dbVersion' => $to,
                    'appVersion' => WEBSITE_VERSION_NAME,
                    'step' => 'Finalise Upgrade',
                    'action' => $action,
                    'type' => 'sql'
                ]);
            }
        }

        Log::debug('%d steps for upgrade', count($steps));

        return $steps;
    }

    /**
     * Check the table is present
     */
    private function checkAndProvision()
    {
        if ($this->$provisioned)
            return;

        // Check if the table exists
        $results = PDOConnect::select('SHOW TABLES LIKE :table', ['table' => 'upgrade']);

        if (count($results) <= 0)
            Upgrade::createTable();

        $this->$provisioned = true;
    }
}