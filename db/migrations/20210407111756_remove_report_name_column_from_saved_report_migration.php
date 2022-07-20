<?php
/**
 * Remove not needed column from saved_report table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class RemoveReportNameColumnFromSavedReportMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('saved_report')
            ->removeColumn('reportName')
            ->save();
    }
}
