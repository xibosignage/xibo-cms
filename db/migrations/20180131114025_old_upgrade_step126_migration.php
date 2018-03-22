<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep126Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 126;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $stat = $this->table('stat');
                $stat->addColumn('widgetId', 'integer', ['null' => true])
                    ->save();

                $displayEvent = $this->table('displayevent', ['id' => 'displayEventId']);
                $displayEvent
                    ->addColumn('eventDate', 'integer')
                    ->addColumn('displayId', 'integer')
                    ->addColumn('start', 'integer')
                    ->addColumn('end', 'integer', ['null' => true])
                    ->addIndex('eventDate')
                    ->addIndex('end')
                    ->save();

                $this->execute('INSERT INTO displayevent (eventDate, displayId, start, end) SELECT UNIX_TIMESTAMP(statDate), displayID, UNIX_TIMESTAMP(start), UNIX_TIMESTAMP(end) FROM stat WHERE Type = \'displaydown\';');

                $this->execute('DELETE FROM stat WHERE Type = \'displaydown\';');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
