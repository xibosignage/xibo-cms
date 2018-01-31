<?php


use Phinx\Migration\AbstractMigration;

class PlaylistTagsMigration extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        $linkPlaylistTag = $this->table('lktagplaylist', ['id' => 'lkTagPlaylistId']);
        $linkPlaylistTag
            ->addColumn('tagId', 'integer')
            ->addColumn('playlistId', 'integer')
            ->addIndex(['tagId', 'playlistId'], ['unique' => true])
            ->save();
    }
}
