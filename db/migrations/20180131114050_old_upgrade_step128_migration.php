<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep128Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 128;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $this->execute('UPDATE `resolution` SET resolution = \'4k cinema\' WHERE resolution = \'4k\';');

                $this->execute('INSERT INTO `resolution` (`resolution`, `width`, `height`, `intended_width`, `intended_height`, `version`, `enabled`) VALUES(\'4k UHD Landscape\', 450, 800, 3840, 2160, 2, 1),(\'4k UHD Portrait\', 800, 450, 2160, 3840, 2, 1);');

                $this->execute('UPDATE schedule SET fromDt = 0, toDt = 2556057600 WHERE dayPartId = 1');

                $this->dropTable('schedule_detail');

                $schedule = $this->table('schedule');
                $schedule->addColumn('lastRecurrenceWatermark', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'null' => true])
                    ->save();

                $this->dropTable('requiredfile');

                $log = $this->table('log');
                $log
                    ->changeColumn('channel', 'string', ['limit' => 20])
                    ->save();

                $this->execute('UPDATE `setting` SET `helpText` = \'The Time to Live (maxage) of the STS header expressed in seconds.\' WHERE `setting` = \'STS_TTL\';');

                if (!$this->checkIndexExists('lkdisplaydg', ['displayGroupId', 'displayId'], 1)) {
                    $index = 'CREATE UNIQUE INDEX lkdisplaydg_displayGroupId_displayId_uindex ON `lkdisplaydg` (displayGroupId, displayId);';

                    // Try to create the index, if we fail, assume duplicates
                    try {
                        $this->execute($index);
                    } catch (\PDOException $e) {
                        // Create a verify table
                        $this->execute('CREATE TABLE lkdisplaydg_verify AS SELECT * FROM lkdisplaydg WHERE 1 GROUP BY displaygroupId, displayId;');

                        // Delete from original table
                        $this->execute('DELETE FROM lkdisplaydg;');

                        // Insert the de-duped records
                        $this->execute('INSERT INTO lkdisplaydg SELECT * FROM lkdisplaydg_verify;');

                        // Drop the verify table
                        $this->execute('DROP TABLE lkdisplaydg_verify;');

                        // Create the index fresh, now that duplicates removed
                        $this->execute($index);
                    }
                }

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }

    /**
     * Check if an index exists
     * @param string $table
     * @param string[] $columns
     * @param bool $isUnique
     * @return bool
     * @throws InvalidArgumentException
     */
    private function checkIndexExists($table, $columns, $isUnique)
    {
        if (!is_array($columns) || count($columns) <= 0)
            throw new InvalidArgumentException('Incorrect call to checkIndexExists', 'columns');

        // Use the information schema to see if the index exists or not.
        // all users have permission to the information schema
        $sql = '
          SELECT * 
            FROM INFORMATION_SCHEMA.STATISTICS 
           WHERE table_schema=DATABASE() 
            AND table_name = \'' . $table . '\'
            AND non_unique = \'' . (($isUnique) ? 0 : 1) . '\'
            AND (
        ';

        $i = 0;
        foreach ($columns as $column) {
            $i++;

            $sql .= (($i == 1) ? '' : ' OR') . ' (seq_in_index = \'' . $i . '\' AND column_name = \'' . $column . '\') ';
        }

        $sql .= ' )';

        $indexes = $this->fetchAll($sql);

        return (count($indexes) === count($columns));
    }
}
