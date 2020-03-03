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

use Xibo\OAuth2\Client\Entity\XiboDisplayProfile;

/**
 * Class DisplayProfileTest
 * @package Xibo\Tests\Integration
 */
class DisplayProfileTest extends \Xibo\Tests\LocalWebTestCase
{

    protected $startProfiles;
    
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startProfiles = (new XiboDisplayProfile($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all profiles that weren't there initially
        $finalProfiles = (new XiboDisplayProfile($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

        // Loop over any remaining profiles and nuke them
        foreach ($finalProfiles as $displayProfile) {
            /** @var XiboDisplayProfile $displayProfile */
            $flag = true;
            foreach ($this->startProfiles as $startProfile) {
               if ($startProfile->displayProfileId == $displayProfile->displayProfileId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $displayProfile->delete();
                } catch (\Exception $e) {
                    $this->getLogger()->error('Unable to delete ' . $displayProfile->displayProfileId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }
    /**
     * Shows all display profiles
     */
    public function testListAll()
    {
        # Get list of all display profiles
        $response = $this->sendRequest('GET','/displayprofile');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
    }


    /**
     * testAddSuccess - test adding various display profiles that should be valid
     * @dataProvider provideSuccessCases
     * @group minimal
     */
    public function testAddSuccess($profileName, $profileType, $profileIsDefault)
    {
        // Loop through any pre-existing profiles to make sure we're not
        // going to get a clash
        foreach ($this->startProfiles as $tmpProfile) {
            if ($tmpProfile->name == $profileName) {
                $this->skipTest("There is a pre-existing profiles with this name");
                return;
            }
        }

        $response = $this->sendRequest('POST','/displayprofile', [
            'name' => $profileName,
            'type' => $profileType,
            'isDefault' => $profileIsDefault
        ]);
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($profileName, $object->data->name);
        $this->assertSame($profileType, $object->data->type);
        $this->assertSame($profileIsDefault, $object->data->isDefault);
        # Check that the profile was added correctly
        $profile = (new XiboDisplayProfile($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($profileName, $profile->name);
        $this->assertSame($profileType, $profile->type);
        # Clean up the Profiles as we no longer need it
        $this->assertTrue($profile->delete(), 'Unable to delete ' . $profile->displayProfileId);
    }

    /**
     * Each array is a test run
     * Format (profile name, type(windows/android), isDefault flag)
     * @return array
     */
    public function provideSuccessCases()
    {
        // Cases we provide to testAddSuccess, you can extend it by simply adding new case here
        return [
            'Android notDefault' => ['test profile', 'android', 0],
            'Windows notDefault' => ['different test profile', 'windows', 0],
            'French Android' => ['Test de Français 1', 'android', 0],
            'Linux' => ['Test de Français 1', 'linux', 0],
            'Tizen' => ['Test de Français 1', 'sssp', 0],
            'webOS' => ['Test de Français 1', 'lg', 0]
        ];
    }

    /**
     * testAddFailure - test adding various profiles that should be invalid
     * @dataProvider provideFailureCases
     */
    public function testAddFailure($profileName, $profileType, $profileIsDefault)
    {
        # Add new display profile with arguments from provideFailureCases
        $response = $this->sendRequest('POST','/displayprofile', [
            'name' => $profileName,
            'type' => $profileType,
            'isDefault' => $profileIsDefault
        ]);
        # Check if it fails as expected
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
    }

    /**
     * Each array is a test run
     * Format (profile name, type(windows/android), isDefault flag)
     * @return array
     */

    public function provideFailureCases()
    {
        # Cases we provide to testAddFailure, you can extend it by simply adding new case here
        return [
            'NULL Type' => ['no type', NULL, 0],
            'NULL name' => [NULL, 'android', 1],
            'is Default 1' => ['TEST PHP', 'android', 1]
        ];
    }
}
