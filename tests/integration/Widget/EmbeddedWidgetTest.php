<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (EmbeddedWidgetTest.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboEmbedded;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\LocalWebTestCase;

class EmbeddedWidgetTest extends LocalWebTestCase
{
	protected $startLayouts;
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all layouts that weren't there initially
        $finalLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining layouts and nuke them
        foreach ($finalLayouts as $layout) {
            /** @var XiboLayout $layout */
            $flag = true;
            foreach ($this->startLayouts as $startLayout) {
               if ($startLayout->layoutId == $layout->layoutId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $layout->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    public function testAdd()
    {
        # Create layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('embedded add', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        $response = $this->client->post('/playlist/widget/embedded/' . $region->playlists[0]['playlistId'], [
            'name' => 'API Embedded widget',
            'duration' => 60,
            'transparency' => 0,
            'scaleContent' => 0,
            'embedHtml' => null,
            'embedScript' => null,
            'embedStyle' => null
            ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $embeddedOptions = (new XiboEmbedded($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame('API Embedded widget', $embeddedOptions->name);       
        $this->assertSame(60, $embeddedOptions->duration);
    }

    public function testEdit()
    {
        # Create layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('embedded edit', '', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        $durationNew = 80;
        # Create embedded widget
        $embedded = (new XiboEmbedded($this->getEntityProvider()))->create('API embedded', 60, 1, 0, 0, null, null, null, $region->playlists[0]['playlistId']);
        $response = $this->client->put('/playlist/widget/' . $embedded->widgetId, [
            'name' => 'EDITED Name',
            'duration' => $durationNew,
            'transparency' => 1,
            'scaleContent' => 1,
            'embedHtml' => null,
            'embedScript' => null,
            'embedStyle' => '<style type="text/css"> </style>'
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $embeddedOptions = (new XiboEmbedded($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame('EDITED Name', $embeddedOptions->name);       
        $this->assertSame($durationNew, $embeddedOptions->duration);
    }

    public function testDelete()
    {
        # Create layout
        $layout = (new XiboLayout($this->getEntityProvider()))->create('embedded delete', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create embedded widget
        $embedded = (new XiboEmbedded($this->getEntityProvider()))->create('API embedded', 60, 1, 0, 0, null, null, null, $region->playlists[0]['playlistId']);
        # Delete it
        $this->client->delete('/playlist/widget/' . $embedded->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
