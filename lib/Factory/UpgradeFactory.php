<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UpgradeFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Upgrade;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class UpgradeFactory extends BaseFactory
{
    private static $provisioned = false;

    public static function getIncomplete()
    {
        return UpgradeFactory::query(null, ['complete' => 0]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Upgrade]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        self::checkAndProvision();

        if ($sortOrder === null)
            $sortOrder = ['stepId'];

        $entries = array();
        $params = array();
        $select = 'SELECT * ';
        $body = ' FROM `upgrade` WHERE 1 = 1 ';

        if (Sanitize::getInt('complete', $filterBy) != null) {
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

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Upgrade())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    /**
     * Return a set of Upgrade Objects
     * @param int $from
     * @param int $to
     * @return array[Upgrade]
     */
    public static function createSteps($from, $to)
    {
        Log::debug('Creating upgrade steps from %d to %d', $from, $to);

        $steps = [];
        $date = Date::parse();

        // Go from $from to $to and get the config file from the install folder.
        for ($i = $from; $i <= $to; $i++) {
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

                    $step['dbVersion'] = $config['dbVersion'];
                    $step['appVersion'] = $config['appVersion'];
                    $step['requestDate'] = $date->format('U');
                    $steps[] = (new Upgrade())->hydrate($step);
                }
            }

            // Add the version bump
            if ($i == $to) {
                $action = 'UPDATE `version` SET `app_ver` = \'' . VERSION . '\', `DBVersion` = ' . $to . '; UPDATE `setting` SET `value` = 0 WHERE `setting` = \'PHONE_HOME_DATE\';';
                $steps[] = (new Upgrade())->hydrate([
                    'dbVersion' => $to,
                    'appVersion' => VERSION,
                    'step' => 'Finalise Upgrade',
                    'action' => $action
                ]);
            }
        }

        Log::debug('%d steps for upgrade', count($steps));

        return $steps;
    }

    /**
     * Check the table is present
     */
    private static function checkAndProvision()
    {
        if (self::$provisioned)
            return;

        // Check if the table exists
        $results = PDOConnect::select('SHOW TABLES LIKE :table', ['table' => 'upgrade']);

        if (count($results) <= 0)
            Upgrade::createTable();

        self::$provisioned = true;
    }
}