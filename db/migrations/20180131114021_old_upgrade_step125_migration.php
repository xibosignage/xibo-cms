<?php


use Phinx\Migration\AbstractMigration;

class OldUpgradeStep125Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 125;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $schedule = $this->table('schedule');
                $schedule->changeColumn('is_priority', 'integer')
                    ->save();

                $this->execute('
                  INSERT INTO module (Module, Name, Enabled, RegionSpecific, Description, ImageUri, SchemaVersion, ValidExtensions, PreviewEnabled, assignable, render_as, settings, viewPath, class, defaultDuration) VALUES 
                    (\'audio\', \'Audio\', 1, 0, \'Audio - support varies depending on the client hardware\', \'forms/video.gif\', 1, \'mp3,wav\', 1, 1, null, null, \'../modules\', \'Xibo\\\\Widget\\\\Audio\', 0),
                    (\'pdf\', \'PDF\', 1, 0, \'PDF document viewer\', \'forms/pdf.gif\', 1, \'pdf\', 1, 1, \'html\', null, \'../modules\', \'Xibo\\\\Widget\\\\Pdf\', 60);
                  ');

                $linkWidgetAudio = $this->table('lkwidgetaudio', ['id' => false, 'primary_key' => ['widgetId', 'mediaId']]);
                $linkWidgetAudio->addColumn('widgetId', 'integer')
                    ->addColumn('mediaId', 'integer')
                    ->addColumn('volume', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->addColumn('loop', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
                    ->save();

                $oauthClientScopes = $this->table('oauth_client_scopes');
                $oauthClientScopes
                    ->addColumn('clientId', 'string', ['limit' => 254])
                    ->addColumn('scopeId', 'string', ['limit' => 254])
                    ->addIndex(['clientId', 'scopeId'], ['unique' => true])
                    ->save();

                // Bulk insert doesn't appear to handle non auto-index primary keys?!
                $this->execute('
                  INSERT INTO `oauth_scopes` (id, description) VALUES 
                    (\'all\', \'All access\'), 
                    (\'mcaas\', \'Media Conversion as a Service\')
                ');

                $oauthRouteScopes = $this->table('oauth_scope_routes');
                $oauthRouteScopes
                    ->addColumn('scopeId', 'string', ['limit' => 254])
                    ->addColumn('route', 'string', ['limit' => 1000])
                    ->addColumn('method', 'string', ['limit' => 8])
                    ->insert([
                        ['scopeId' => 'mcaas', 'route' => '/', 'method' => 'GET'],
                        ['scopeId' => 'mcaas', 'route' => '/library/download/:id(/:type)', 'method' => 'GET'],
                        ['scopeId' => 'mcaas', 'route' => '/library/mcaas/:id', 'method' => 'POST'],
                    ])
                    ->save();

                $module = $this->table('module');
                $module->addColumn('installName', 'string', ['limit' => 254, 'null' => true])
                    ->save();

                $this->execute('ALTER TABLE display CHANGE isAuditing auditingUntil int NOT NULL DEFAULT \'0\' COMMENT \'Is this display auditing\';');

                $this->execute('INSERT INTO setting (setting, value, fieldType, helptext, options, cat, userChange, title, validation, ordering, `default`, userSee, type) VALUES (\'ELEVATE_LOG_UNTIL\', \'1463396415\', \'datetime\', \'Elevate the log level until this date.\', null, \'troubleshooting\', 1, \'Elevate Log Until\', \' \', 25, \'\', 1, \'datetime\');');

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
