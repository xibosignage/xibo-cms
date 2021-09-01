<?php
/**
 * Add new column ipAddress to Audit Log table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddIpAddressColumnToAuditLogMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('auditlog')
            ->addColumn('ipAddress', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->save();
    }
}
