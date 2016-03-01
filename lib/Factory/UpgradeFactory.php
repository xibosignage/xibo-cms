<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UpgradeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Upgrade;
use Xibo\Exception\NotFoundException;

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
        if ($this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start'), 0) . ', ' . $this->getSanitizer()->getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = (new Upgrade())->hydrate($row)->setContainer($this->getContainer());
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
        $date = $this->getDate()->parse();

        // Go from $from to $to and get the config file from the install folder.
        for ($i = $from + 1; $i <= $to; $i++) {
            $currentStep = PROJECT_ROOT . '/install/steps/' . $i . '.json';
            $this->getLog()->debug('Checking for %s', $currentStep);
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
                $action = 'UPDATE `version` SET `app_ver` = \'' . $this->getConfig()->$WEBSITE_VERSION_NAME . '\', `DBVersion` = ' . $to . '; UPDATE `setting` SET `value` = 0 WHERE `setting` = \'PHONE_HOME_DATE\';';
                $steps[] = (new Upgrade())->hydrate([
                    'dbVersion' => $to,
                    'appVersion' => $this->getConfig()->$WEBSITE_VERSION_NAME,
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
        if ($this->$provisioned)
            return;

        // Check if the table exists
        $results = $this->getStore()->select('SHOW TABLES LIKE :table', ['table' => 'upgrade']);

        if (count($results) <= 0)
            Upgrade::createTable();

        $this->$provisioned = true;
    }
}