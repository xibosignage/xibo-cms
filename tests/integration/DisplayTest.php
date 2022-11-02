<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Tests\Integration;
use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;

class DisplayTest extends \Xibo\Tests\LocalWebTestCase
{
    protected $startDisplays;
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startDisplays = (new XiboDisplay($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Tear down any displays that weren't there before
        $finalDisplays = (new XiboDisplay($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        
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
        # Get all displays
        $response = $this->sendRequest('GET','/display');
        # Check if successful
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
    }

    /**
     * Delete Display Test
     */
    public function testDelete()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);
        if (count($displays) != 1)
            $this->fail('Display was not added correctly');
        /** @var XiboDisplay $display */
        $display = $displays[0];
        $response = $this->sendRequest('DELETE','/display/' . $display->displayId);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status);
    }

    /**
     * Edit Display test, expecting success
     */
    public function testEditSuccess()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);

        if (count($displays) != 1) {
            $this->fail('Display was not added correctly');
        }

        /** @var XiboDisplay $display */
        $display = $displays[0];
        $auditingTime = time()+3600;
        # Edit display and change its name
        $response = $this->sendRequest('PUT','/display/' . $display->displayId, [
            'display' => 'API EDITED',
            'defaultLayoutId' => $display->defaultLayoutId,
            'auditingUntil' => Carbon::createFromTimestamp($auditingTime)->format(DateFormatHelper::getSystemFormat()),
            'licensed' => $display->licensed,
            'license' => $display->license,
            'incSchedule' => $display->incSchedule,
            'emailAlert' => $display->emailAlert,
            'wakeOnLanEnabled' => $display->wakeOnLanEnabled,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
        # Check if display has new edited name
        $this->assertSame('API EDITED', $object->data->display);
    }

    /**
     * Edit Display Type and reference, expecting success
     */
    public function testEditDisplayType()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display Type');
        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);

        if (count($displays) != 1) {
            $this->fail('Display was not added correctly');
        }

        /** @var XiboDisplay $display */
        $display = $displays[0];
        $auditingTime = time()+3600;
        # Edit display and change its name
        $response = $this->sendRequest('PUT', '/display/' . $display->displayId, [
            'display' => 'PHPUnit Test Display Type - EDITED',
            'defaultLayoutId' => $display->defaultLayoutId,
            'auditingUntil' => Carbon::createFromTimestamp($auditingTime)->format(DateFormatHelper::getSystemFormat()),
            'licensed' => $display->licensed,
            'license' => $display->license,
            'incSchedule' => $display->incSchedule,
            'emailAlert' => $display->emailAlert,
            'wakeOnLanEnabled' => $display->wakeOnLanEnabled,
            'displayTypeId' => 1,
            'ref1' => 'Lorem ipsum',
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
        # Check if display has new edited name
        $this->assertSame(1, $object->data->displayTypeId);
        $this->assertSame('Lorem ipsum', $object->data->ref1);
    }

    /**
     * Edit Display test, expecting failure
     */
    public function testEditFailure()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);
        if (count($displays) != 1)
            $this->fail('Display was not added correctly');
        /** @var XiboDisplay $display */
        $display = $displays[0];
        # Edit display and change its hardwareKey
        $response = $this->sendRequest('PUT','/display/' . $display->displayId, [
            'display' => 'API EDITED',
            'defaultLayoutId' => $display->defaultLayoutId,
            'licensed' => $display->licensed,
            'license' => null,
            'incSchedule' => $display->incSchedule,
            'emailAlert' => $display->emailAlert,
            'wakeOnLanEnabled' => $display->wakeOnLanEnabled,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if call failed as expected (license cannot be null)
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getBody());
    }

    /**
     * Request screenshot Test
     */
    public function testScreenshot()
    {
        # Generate names for display and xmr channel
        $hardwareId = Random::generateString(12, 'phpunit');
        $xmrChannel = Random::generateString(50);
        # This is a dummy pubKey and isn't used by anything important
        $xmrPubkey = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDmdnXL4gGg3yJfmqVkU1xsGSQI
3b6YaeAKtWuuknIF1XAHAHtl3vNhQN+SmqcNPOydhK38OOfrdb09gX7OxyDh4+JZ
inxW8YFkqU0zTqWaD+WcOM68wTQ9FCOEqIrbwWxLQzdjSS1euizKy+2GcFXRKoGM
pbBhRgkIdydXoZZdjQIDAQAB
-----END PUBLIC KEY-----';
        # Register our display
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId,
            'PHPUnit Test Display',
            'windows',
            null,
            null,
            null,
            '00:16:D9:C9:AL:69',
            $xmrChannel,
            $xmrPubkey
        );

        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);
        if (count($displays) != 1)
            $this->fail('Display was not added correctly');
        /** @var XiboDisplay $display */
        $display = $displays[0];
        # Check if xmr channel and pubkey were registered correctly
        $this->assertSame($xmrChannel, $display->xmrChannel, 'XMR Channel not set correctly by XMDS Register Display');
        $this->assertSame($xmrPubkey, $display->xmrPubKey, 'XMR PubKey not set correctly by XMDS Register Display');
        # Call request screenshot
        $response = $this->sendRequest('PUT','/display/requestscreenshot/' . $display->displayId);
        # Check if successful
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
    }

    /**
     * Wake On Lan Test
     */
    public function testWoL()
    {
        # Create dummy hardware key and mac address
        $hardwareId = Random::generateString(12, 'phpunit');
        $macAddress = '00-16-D9-C9-AE-69';
        # Register our display
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 
            'PHPUnit Test Display', 
            'windows', 
            null, 
            null, 
            null, 
            $macAddress,
            Random::generateString(50), 
            Random::generateString(50)
        );
        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);
        if (count($displays) != 1)
            $this->fail('Display was not added correctly');
        /** @var XiboDisplay $display */
        $display = $displays[0];
        # Check if mac address was added correctly
        $this->assertSame($macAddress, $display->macAddress, 'Mac Address not set correctly by XMDS Register Display');
        $auditingTime = time()+3600;
        # Edit display and add broadcast channel
        $display->edit(
            $display->display,
            $display->description,
            $display->tags,
            Carbon::createFromTimestamp($auditingTime)->format(DateFormatHelper::getSystemFormat()),
            $display->defaultLayoutId,
            $display->licensed,
            $display->license,
            $display->incSchedule,
            $display->emailAlert,
            $display->alertTimeout,
            $display->wakeOnLanEnabled,
            null,
            '127.0.0.1');
        # Call WOL
        $response = $this->sendRequest('POST','/display/wol/' . $display->displayId);
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
    }
}
