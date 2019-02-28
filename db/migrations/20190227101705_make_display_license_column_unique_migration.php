<?php


use Phinx\Migration\AbstractMigration;

class MakeDisplayLicenseColumnUniqueMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // query the database and look for duplicate entries
        $duplicatesData = $this->query('SELECT displayId, license FROM display WHERE license IN ( SELECT license FROM display GROUP BY license HAVING count(*) > 1) GROUP BY displayId;');
        $rowsDuplicatesData = $duplicatesData->fetchAll(PDO::FETCH_ASSOC);
        // only execute this code if any duplicates were found
        if (count($rowsDuplicatesData) > 0) {
            $licences = [];
            $filtered = [];
            // create new array with license as the key
            foreach ($rowsDuplicatesData as $row) {
                $licences[$row['license']][] = $row['displayId'];
            }
            // iterate through the array and remove first element from each of the arrays with displayIds
            foreach ($licences as $licence) {
                array_shift($licence);
                $filtered[] = $licence;
            }
            // iterate through our new filtered array, that only contains displayIds that should be removed and execute the SQL DELETE statements
            foreach ($filtered as $item) {
                foreach ($item as $displayId) {
                    $this->execute('DELETE FROM lkdisplaydg WHERE displayId = ' . $displayId);
                    $this->execute('DELETE FROM display WHERE `displayId` = ' . $displayId);
                }
            }
        }

        // add unique index to license column
        $table = $this->table('display');
        $table->addIndex(['license'], ['unique' => true])->update();
    }
}