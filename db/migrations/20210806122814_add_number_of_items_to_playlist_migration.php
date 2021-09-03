<?php
/**
 * Add a new column (maxNumberOfItems) to Playlist table
 * Add two new Settings for default and limit of items per dynamic Playlist
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddNumberOfItemsToPlaylistMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // add a new column to the Playlist table
        $this->table('playlist')
            ->addColumn('maxNumberOfItems', 'integer', ['default' => null, 'null' => true, 'after' => 'filterMediaTags'])
            ->save();

        // get the count of Widgets in dynamic Playlists, first element will be the highest
        $widgetCount = $this->query('
                SELECT COUNT(*) AS cnt, widget.playlistId
                    FROM `widget` 
                    WHERE playlistId IN (SELECT playlistId FROM `playlist` WHERE isDynamic = 1)
                GROUP BY widget.playlistId
                ORDER BY COUNT(*) DESC
        ');
        $widgetCountData = $widgetCount->fetchAll(PDO::FETCH_ASSOC);

        // compare our proposed default values with the highest Widget count on dynamic Playlist in the system
        $default = max($widgetCountData[0]['cnt'] ?? 0, 30);
        $max = max($widgetCountData[0]['cnt'] ?? 0, 100);

        // set all dynamic Playlists maxNumberOfItems to the default value
        $this->execute('UPDATE `playlist` SET maxNumberOfItems = ' . $default. ' WHERE isDynamic = 1');

        // insert new Settings with default and max values calculated above
        $this->table('setting')->insert([
            [
                'setting' => 'DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER',
                'value' => $default,
                'userSee' => 1,
                'userChange' => 1
            ],
            [
                'setting' => 'DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT',
                'value' => $max,
                'userSee' => 1,
                'userChange' => 1
            ]
        ])->save();
    }
}
