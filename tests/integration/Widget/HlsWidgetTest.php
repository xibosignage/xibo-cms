<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (HlsWidgetTest.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboHls;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\LocalWebTestCase;

class HlsWidgetTest extends LocalWebTestCase
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

	/**
     * @group broken
     * @dataProvider provideSuccessCases
     */
	public function testAdd($name, $useDuration, $duration, $uri, $mute, $transparency)
	{
		# Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Hls add Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);

		$response = $this->client->post('/playlist/widget/hls/' . $region->playlists[0]['playlistId'], [
        	'name' => $name,
            'useDuration' => $useDuration,
        	'duration' => $duration,
        	'uri' => $uri,
        	'mute' => $mute,
        	'transparency' => $transparency,
        	]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboHls($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame($name, $widgetOptions->name);
        $this->assertSame($duration, $widgetOptions->duration);
        
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($uri, urldecode(($option['value'])));
            }
        }
	}

	/**
     * Each array is a test run
     * Format ($name, $useDuration, $duration, $uri, $mute, $transparency)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'HLS stream' => ['HLS stream', 1, 20, 'http://ceu.xibo.co.uk/hls/big_buck_bunny_adaptive_master.m3u8', 0, 0],
            'HLS stream 512' => ['HLS stream with transparency', 1, 20, 'http://ceu.xibo.co.uk/hls/big_buck_bunny_adaptive_512.m3u8', 0, 1],
        ];
    }

    /**
     * testAddFailure - test adding various hls widgets that should be invalid
     * @dataProvider provideFailureCases
     * @group broken
     */
    public function testAddFailure($name, $useDuration, $duration, $uri, $mute, $transparency)
    {
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Traffic failure add Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create Google traffic widgets with arguments from provideFailureCases
        $response = $this->client->post('/playlist/widget/hls/' . $region->playlists[0]['playlistId'], [
            'name' => $name,
            'useDuration' => $useDuration,
            'duration' => $duration,
            'uri' => $uri,
            'mute' => $mute,
            'transparency' => $transparency,
            ]);
        # check if they fail as expected
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
    }

    /**
     * Each array is a test run
     * Format ($$name, $useDuration, $duration, $uri, $mute, $transparency)
     * @return array
     */
    public function provideFailureCases()
    {
        # Data for testAddfailure, easily expandable - just add another set of data below
        return [
            'No url provided' => ['no uri', 1, 10, null, 0, 0],
            'No duration provided' => ['no duration with useDuration 1', 1, 0, 'http://ceu.xibo.co.uk/hls/big_buck_bunny_adaptive_512.m3u8', 0, 0],
        ];
    }

    /**
    * Edit
    * @group broken
    */
    public function testEdit()
    {
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Traffic failure add Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create Google traffic widget 
        $hls = (new XiboHls($this->getEntityProvider()))->create('HLS stream', 1, 20, 'http://ceu.xibo.co.uk/hls/big_buck_bunny_adaptive_master.m3u8', 0, 0, $region->playlists[0]['playlistId']);     
        $nameNew = 'Edited Widget';
        $durationNew = 100;
        $uriNew = 'http://ceu.xibo.co.uk/hls/big_buck_bunny_adaptive_512.m3u8';
        $response = $this->client->put('/playlist/widget/' . $hls->widgetId, [
            'name' => $nameNew,
            'useDuration' => 1,
            'duration' => $durationNew,
            'uri' => $uriNew,
            'mute' => $hls->mute,
            'transparency' => $hls->transparency,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboHls($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame($nameNew, $widgetOptions->name);
        $this->assertSame($durationNew, $widgetOptions->duration);
        
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'uri') {
                $this->assertSame($uriNew, urldecode(($option['value'])));
            }
        }
    }

    /**
    * Delete
    * @group broken
    */
    public function testDelete()
    {
        # Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Clock delete Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
        # Create Google traffic widget 
        $hls = (new XiboHls($this->getEntityProvider()))->create('HLS stream', 1, 20, 'http://ceu.xibo.co.uk/hls/big_buck_bunny_adaptive_master.m3u8', 0, 0, $region->playlists[0]['playlistId']);   
        # Delete it
        $this->client->delete('/playlist/widget/' . $hls->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
