<?php


use Phinx\Migration\AbstractMigration;

class DisplayGroupClosureIndexToNonUnique extends AbstractMigration
{
    /** @inheritdoc */
    public function up()
    {
        // Drop the existing indexes if they exist
        $indexName = $this->checkIndexExists('lkdgdg', ['parentId', 'childId', 'depth'], true);
        if ($indexName !== false) {
            $this->execute('DROP INDEX `' . $indexName . '` ON `lkdgdg`');
        }

        $indexName = $this->checkIndexExists('lkdgdg', ['childId', 'parentId', 'depth'], true);
        if ($indexName !== false) {
            $this->execute('DROP INDEX `' . $indexName . '` ON `lkdgdg`');
        }

        // Add new indexes
        $table = $this->table('lkdgdg');
        $table
            ->addIndex(['parentId', 'childId', 'depth'])
            ->addIndex(['childId', 'parentId', 'depth'])
            ->update();
    }

    /**
     * Check if an index exists
     * @param string $table
     * @param string[] $columns
     * @param bool $isUnique
     * @return string|false
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

        return (count($indexes) === count($columns)) ? $indexes['INDEX_NAME'] : false;
    }
}
