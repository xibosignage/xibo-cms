<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ClockWidgetTest.php)
 */

namespace Xibo\Tests\Integration\Widget;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboClock;
use Xibo\OAuth2\Client\Entity\XiboWidget;
use Xibo\Tests\LocalWebTestCase;

class ClockWidgetTest extends LocalWebTestCase
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
	public function testAdd($name, $duration, $useDuration, $theme, $clockTypeId, $offset, $format, $showSeconds, $clockFace)
	{
		# Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Clock add Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);

		$response = $this->client->post('/playlist/widget/clock/' . $region->playlists[0]['playlistId'], [
        	'name' => $name,
        	'duration' => $duration,
            'useDuration' => $useDuration,
        	'themeId' => $theme,
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
        $clockOptions = (new XiboClock($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame($name, $clockOptions->name);
        $this->assertSame($duration, $clockOptions->duration);
        
        foreach ($clockOptions->widgetOptions as $option) {
            if ($option['option'] == 'theme') {
                $this->assertSame($theme, intval($option['value']));
            }
            if ($option['option'] == 'clockTypeId') {
                $this->assertSame($clockTypeId, intval($option['value']));
            }
            if ($option['option'] == 'offset') {
                $this->assertSame($offset, intval($option['value']));
            }
            if ($option['option'] == 'format') {
                $this->assertSame($format, $option['value']);
            }
            if ($option['option'] == 'showSeconds') {
                $this->assertSame($showSeconds, intval($option['value']));
            }
            if ($option['option'] == 'clockFace') {
                $this->assertSame($clockFace, $option['value']);
            }
        }
	}

	/**
     * Each array is a test run
     * Format ($name, $duration, $useDuration, $theme, $clockTypeId, $offset, $format, $showSeconds, $clockFace)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Analogue' => ['Api Analogue clock', 20, 1, 1, 1, 0, '', 0, 'TwentyFourHourClock'],
            'Digital' => ['API digital clock', 20, 1, 0, 2, 0, '[HH:mm]', 0, 'TwentyFourHourClock'],
            'Flip 24h' => ['API Flip clock 24h', 5, 1, 0, 3, 0, '', 1, 'TwentyFourHourClock'],
            'Flip counter' => ['API Flip clock Minute counter', 50, 1, 0, 3, 0, '', 1, 'MinuteCounter']
        ];
    }

    public function testEdit()
    {
    	# Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Clock edit Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
    	# Create a clock with wrapper
    	$clock = (new XiboClock($this->getEntityProvider()))->create('Api Analogue clock', 20, 1, 1, 1, NULL, NULL, NULL, NULL, $region->playlists[0]['playlistId']);
    	$nameNew = 'Edited Name';
    	$durationNew = 80;
    	$clockTypeIdNew = 3;
    	$response = $this->client->put('/playlist/widget/' . $clock->widgetId, [
        	'name' => $nameNew,
        	'duration' => $durationNew,
        	'themeId' => $clock->theme,
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
        $clockOptions = (new XiboClock($this->getEntityProvider()))->getById($region->playlists[0]['playlistId']);
        $this->assertSame($nameNew, $clockOptions->name);
        $this->assertSame($durationNew, $clockOptions->duration);
        foreach ($clockOptions->widgetOptions as $option) {
            if ($option['option'] == 'clockTypeId') {
                $this->assertSame($clockTypeIdNew, intval($option['value']));
            }
        }
    }
    public function testDelete()
    {
    	# Create layout 
        $layout = (new XiboLayout($this->getEntityProvider()))->create('Clock delete Layout', 'phpunit description', '', 9);
        # Add region to our layout
        $region = (new XiboRegion($this->getEntityProvider()))->create($layout->layoutId, 1000,1000,200,200);
    	# Create a clock with wrapper
		$clock = (new XiboClock($this->getEntityProvider()))->create('Api Analogue clock', 20, 1, 1, 1, NULL, NULL, NULL, NULL, $region->playlists[0]['playlistId']);
		# Delete it
		$this->client->delete('/playlist/widget/' . $clock->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
