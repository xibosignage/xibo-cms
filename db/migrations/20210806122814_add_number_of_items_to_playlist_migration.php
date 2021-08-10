<?php
/**
 * Add a new column (maxNumberOfItems) to Playlist table
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddNumberOfItemsToPlaylistMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('playlist')
            ->addColumn('maxNumberOfItems', 'integer', ['default' => null, 'null' => true, 'after' => 'filterMediaTags'])
            ->save();

        $this->table('setting')->insert([
            [
                'setting' => 'DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER',
                'value' => 30,
                'userSee' => 1,
                'userChange' => 1
            ],
            [
                'setting' => 'DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT',
                'value' => 100,
                'userSee' => 1,
                'userChange' => 1
            ]
        ])->save();

        $this->execute('UPDATE `playlist` SET maxNumberOfItems = 30 WHERE isDynamic = 1');
    }
}
