<?php


use Phinx\Migration\AbstractMigration;

class AdjustGenericfileValidExtensionsMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // get the current validExtensions for genericfile module
        $extensionsData = $this->query('SELECT `validExtensions` FROM `module` WHERE `Module` = \'genericfile\';');
        $extensions = $extensionsData->fetchAll(PDO::FETCH_ASSOC);
        $newExtensions = [];

        //iterate through the array
        foreach ($extensions as $extension) {
            foreach ($extension as $validExt) {

                // make an array out of comma separated string
                $explode = explode(',', $validExt);

                // iterate through our array, remove apk and ipk extensions from it and put them in a new array
                foreach ($explode as $item) {
                    if ($item != 'apk' && $item != 'ipk') {
                        $newExtensions[] = $item;
                    }
                }
            }
        }

        // make a comma separated string from our new array
        $newValidExtensions = implode(',', $newExtensions);

        // update validExtensions for genericfile module with our adjusted extensions
        $this->execute('UPDATE `module` SET `validExtensions` = ' . "'" . $newValidExtensions . "'" .' WHERE module = \'genericfile\' LIMIT 1;');
    }
}
