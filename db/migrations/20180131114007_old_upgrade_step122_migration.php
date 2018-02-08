<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep122Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 122;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $sql = '
                  SELECT TABLE_NAME
                    FROM INFORMATION_SCHEMA.TABLES
                   WHERE TABLE_SCHEMA = \'' . $_SERVER['MYSQL_DATABASE']  . '\'
                    AND ENGINE = \'MyISAM\'
                ';

                foreach ($this->fetchAll($sql) as $table) {
                    $this->execute('ALTER TABLE `' . $table['TABLE_NAME'] . '` ENGINE=INNODB', []);
                }

                $auditLog = $this->table('auditlog');
                $auditLog->changeColumn('userId', 'integer', ['null' => true])
                    ->save();

                $dataSet = $this->table('dataset');
                $dataSet->addColumn('code', 'string', ['limit' => 50, 'null' => true])
                    ->addColumn('isLookup', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                    ->save();

                $module = $this->table('module');
                $module->addColumn('defaultDuration', 'integer')
                    ->save();

                $this->execute('UPDATE `module` SET defaultDuration = 10;');
                $this->execute('UPDATE `module` SET defaultDuration = (SELECT MAX(value) FROM `setting` WHERE setting = \'jpg_length\') WHERE `module` = \'image\';');
                $this->execute('UPDATE `module` SET defaultDuration = (SELECT MAX(value) FROM `setting` WHERE setting = \'swf_length\') WHERE `module` = \'flash\';');
                $this->execute('UPDATE `module` SET defaultDuration = (SELECT MAX(value) FROM `setting` WHERE setting = \'ppt_length\') WHERE `module` = \'powerpoint\';');
                $this->execute('UPDATE `module` SET defaultDuration = 0 WHERE `module` = \'video\';');
                $this->execute('DELETE FROM `setting` WHERE setting IN (\'ppt_length\', \'jpg_length\', \'swf_length\');');
                $this->execute('UPDATE `widget` SET `calculatedDuration` = `duration`;');

                $userOption = $this->table('useroption', ['id' => false, 'primary_key' => ['userId', 'option']]);
                $userOption->addColumn('userId', 'integer')
                    ->addColumn('option', 'string', ['limit' => 50])
                    ->addColumn('value', 'text')
                    ->save();

                $displayGroup = $this->table('displaygroup');
                $displayGroup->addColumn('isDynamic', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('dynamicCriteria', 'string', ['null' => true, 'limit' => 2000])
                    ->addColumn('userId', 'integer')
                    ->save();

                $this->execute('UPDATE `displaygroup` SET userId = (SELECT userId FROM `user` WHERE usertypeid = 1 LIMIT 1) WHERE userId = 0;');

                $session = $this->table('session');
                $session->removeColumn('lastPage')
                    ->removeColumn('securityToken')
                    ->save();

                $linkDisplayGroup = $this->table('lkdgdg', ['id' => false, ['primary_key' => ['parentId', 'childId', 'depth']]]);
                $linkDisplayGroup
                    ->addColumn('parentId', 'integer')
                    ->addColumn('childId', 'integer')
                    ->addColumn('depth', 'integer')
                    ->addIndex(['childId', 'parentId', 'depth'], ['unique' => true])
                    ->save();

                $this->execute('INSERT INTO `lkdgdg` (parentId, childId, depth) SELECT displayGroupId, displayGroupId, 0 FROM `displaygroup` WHERE `displayGroupID` NOT IN (SELECT `parentId` FROM `lkdgdg` WHERE depth = 0);');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
