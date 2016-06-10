<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\Tests\LocalWebTestCase;
use Xibo\Helper\Random;

class DisplayTest extends \Xibo\Tests\LocalWebTestCase
{
    protected $startDisplays;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startDisplays = (new XiboDisplay($this->getEntityProvider()))->get();
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Tear down any displays that weren't there before
        $finalDisplays = (new XiboDisplay($this->getEntityProvider()))->get();
        
        # Loop over any remaining displays and nuke them
        foreach ($finalDisplays as $display) {
            /** @var XiboDisplay $display */

            $flag = true;

            foreach ($this->startDisplays as $startDisplay) {
               if ($startDisplay->displayId == $display->displayId) {
                   $flag = false;
               }
            }

            if ($flag) {
                try {
                    $display->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $display->displayId . '. E:' . $e->getMessage());
                }
            }
        }

        parent::tearDown();
    }


	/**
     * Shows list of all displays Test
     */
    public function testListAll()
    {
        $this->client->get('/display');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * Delete Display Test
     */
    public function testDelete()
    {

        // Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $response = $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        
        // Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get();
        $display = null;
        
        foreach ($displays as $disp) {
            if ($disp->license == $hardwareId) {
                $display = $disp;
            }
        }
        
        if ($display === null) {
            $this->fail('Display was not added correctly');
        }

        $this->client->delete('/display/' . $display->displayId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
     * Edit Display test
     */
    public function testEdit()
    {

        // Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $response = $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        
        // Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get();
        $display = null;
        
        foreach ($displays as $disp) {
            if ($disp->license == $hardwareId) {
                $display = $disp;
            }
        }
        
        if ($display === null) {
            $this->fail('Display was not added correctly');
        }

         $this->client->put('/display/' . $display->displayId, [
            'display' => 'API EDITED',
            'isAuditing' => $display->isAuditing,
            'defaultLayoutId' => $display->defaultLayoutId,
            'licensed' => $display->licensed,
            'license' => $display->license,
            'incSchedule' => $display->incSchedule,
            'emailAlert' => $display->emailAlert,
            'wakeOnLanEnabled' => $display->wakeOnLanEnabled,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
        $this->assertSame('API EDITED', $object->data->display);
    }

    /**
     * Request screenshot Test
     * @group broken
     */
    public function testScreenshot()
    {
        // Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $response = $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        
        // Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get();
        $display = null;
        
        foreach ($displays as $disp) {
            if ($disp->license == $hardwareId) {
                $display = $disp;
            }
        }
        
        if ($display === null) {
            $this->fail('Display was not added correctly');
        }

        $this->client->put('/display/requestscreenshot/' . $display->displayId);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
    }

    /**
     * Wake On Lan Test
     * @group broken
     */
    public function testWoL()
    {
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 
            'PHPUnit Test Display', 
            'windows', 
            null, 
            null, 
            null, 
            '00:16:D9:C9:AL:69', 
            Random::generateString(50), 
            Random::generateString(50)
        );

        // Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get();
        $display = null;

        foreach ($displays as $disp) {
            if ($disp->license == $hardwareId) {
                $display = $disp;
            }
        }

        if ($display === null) {
            $this->fail('Display was not added correctly');
        }

        $this->client->get('/display/wol/' . $display->displayId);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
    }
}
