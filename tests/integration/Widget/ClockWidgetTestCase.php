<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ClockWidgetTestCase.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboClock;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\LocalWebTestCase;
use Xibo\Tests\Integration\Widget\WidgetTestCase;

class ClockWidgetTestCase extends WidgetTestCase
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
	 * @group add
     * @dataProvider provideSuccessCases
     */
	public function testAdd($name, $duration, $theme, $clockTypeId, $offset, $format, $showSeconds, $clockFace)
	{
		//parent::setupEnv();
		# Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Clock add Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);

		$response = $this->client->post('/playlist/widget/clock/' . $region->playlists[0]['playlistId'], [
        	'name' => $name,
        	'duration' => $duration,
        	'theme' => $theme,
        	'clockTypeId' => $clockTypeId,
        	'offset' => $offset,
        	'format' => $format,
        	'showSeconds' => $showSeconds,
        	'clockFace' => $clockFace
        	]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
       // $this->assertSame($name, $object->data->widgetOptions->name);
        $this->assertSame($duration, $object->data->duration);
       // $this->assertSame($clockTypeId, $object->data->clockTypeId);
	}

	/**
     * Each array is a test run
     * Format ($name, $duration, $theme, $clockTypeId, $offset, $format, $showSeconds, $clockFace)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Analogue' => ['Api Analogue clock', 20, 1, 1, NULL, NULL, NULL, NULL],
            'Digital' => ['API digital clock', 20, NULL, 2, NULL, '[HH:mm]', NULL, NULL],
            'Flip 24h' => ['API Flip clock 24h', 5, NULL, 3, NULL, NULL, 1, 'TwentyFourHourClock'],
            'Flip counter' => ['API Flip clock 24h', 50, NULL, 3, NULL, NULL, 1, 'MinuteCounter']
        ];
    }

    public function testEdit()
    {
    	//parent::setupEnv();
    	# Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Clock edit Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
    	# Create a clock with wrapper
    	$clock = (new XiboClock($this->getEntityProvider()))->create('Api Analogue clock', 20, 1, 1, NULL, NULL, NULL, NULL, $region->playlists[0]['playlistId']);
    	$clockCheck = (new XiboWidget($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
    	$nameNew = 'Edited Name';
    	$durationNew = 80;
    	$clockTypeIdNew = 3;
    	$response = $this->client->put('/playlist/widget/' . $clockCheck->widgetId, [
        	'name' => $nameNew,
        	'duration' => $durationNew,
        	'theme' => $clock->theme,
        	'clockTypeId' => $clockTypeIdNew,
        	'offset' => $clock->offset,
        	'format' => $clock->format,
        	'showSeconds' => $clock->showSeconds,
        	'clockFace' => $clock->clockFace
        	], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
       // $this->assertSame($nameNew, $object->data->name);
        $this->assertSame($durationNew, $object->data->duration);
       // $this->assertSame($clockTypeIdNew, $object->data->clockTypeId);
    }

    public function testDelete()
    {
    	//parent::setupEnv();
    	# Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Clock delete Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);

    	# Create a clock with wrapper
		$clock = (new XiboClock($this->getEntityProvider()))->create('Api Analogue clock', 20, 1, 1, NULL, NULL, NULL, NULL, $region->playlists[0]['playlistId']);
		$clockCheck = (new XiboWidget($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
		# Delete it
		$this->client->delete('/playlist/widget/' . $clockCheck->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
