<?php
/**
 * Add Orientation column to Layout and Media tables
 * Attempt to determine the resolution for existing Layouts
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddOrientationToResolutionAndMediaMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('layout')
            ->addColumn('orientation', 'string', ['limit' => 10, 'after' => 'height', 'null' => true, 'default' => null])
            ->save();

        $layouts = $this->query('SELECT layoutId, width, height FROM layout');
        $layouts = $layouts->fetchAll(PDO::FETCH_ASSOC);

        // go through existing Layouts and determine the orientation
        foreach ($layouts as $layout) {
            $orientation = ($layout['width'] >= $layout['height']) ? 'landscape' : 'portrait';
            $this->execute('UPDATE `layout` SET `orientation` = "'. $orientation .'" WHERE layoutId = '. $layout['layoutId']);
        }

        $this->table('media')
            ->addColumn('orientation', 'string', ['limit' => 10, 'null' => true, 'default' => null])
            ->save();

        $this->table('task')
            ->insert([
            [
                'name' => 'Media Orientation',
                'class' => '\Xibo\XTR\MediaOrientationTask',
                'options' => '[]',
                'schedule' => '*/5 * * * * *',
                'isActive' => '1',
                'configFile' => '/tasks/media-orientation.task',
                'pid' => null,
                'lastRunDt' => 0,
                'lastRunDuration' => 0,
                'lastRunExitCode' => 0
            ],
        ])->save();
    }
}
