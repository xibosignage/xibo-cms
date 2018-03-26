<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (displayProfileTest.php)
 */

namespace Xibo\Tests\Integration;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplayProfile;


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
        # Loop over any remaining profiles and nuke them
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
                    $DisplayProfile->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $displayProfile->displayProfileId . '. E:' . $e->getMessage());
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
        $this->client->get('/displayprofile');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
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

        $response = $this->client->post('/displayprofile', [
            'name' => $profileName,
            'type' => $profileType,
            'isDefault' => $profileIsDefault
        ]);
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        
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
        # Cases we provide to testAddSuccess, you can extend it by simply adding new case here
        return [
            'Android notDefault' => ['test profile', 'android', 0],
            'Windows notDefault' => ['different test profile', 'windows', 0],
            'French Android' => ['Test de Français 1', 'android', 0]
        ];
    }

    /**
     * testAddFailure - test adding various profiles that should be invalid
     * @dataProvider provideFailureCases
     */
    public function testAddFailure($profileName, $profileType, $profileIsDefault)
    {
        # Add new display profile with arguments from provideFailureCases
        $response = $this->client->post('/displayprofile', [
            'name' => $profileName,
            'type' => $profileType,
            'isDefault' => $profileIsDefault
        ]);
        # Check if it fails as expected
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
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

    /**
     * Edit an existing profile
     * @depends testAddSuccess
     */
    public function testEdit()
    {
        # Load in a known profile
        /** @var XiboDisplayProfile $displayProfile */
        $displayProfile = (new XiboDisplayProfile($this->getEntityProvider()))->create('phpunit profile', 'android', 0);
        # Change the profile name
        $name = Random::generateString(8, 'phpunit');
        $this->client->put('/displayprofile/' . $displayProfile->displayProfileId, [
            'name' => $name,
            'type' => $displayProfile->type,
            'isDefault' => $displayProfile->isDefault
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
       
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame('android', $object->data->type);
        # Check that the profile was actually renamed
        $displayProfile = (new XiboDisplayProfile($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $displayProfile->name);
        # Clean up the profile as we no longer need it
        $displayProfile->delete();
    }

    /**
     * Test delete
     * @depends testAddSuccess
     * @group minimal
     */
    public function testDelete()
    {
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known profiles
        $profile1 = (new XiboDisplayProfile($this->getEntityProvider()))->create($name1, 'android', 0);
        $profile2 = (new XiboDisplayProfile($this->getEntityProvider()))->create($name2, 'windows', 0);
        # Delete the one we created last
        $this->client->delete('/displayprofile/' . $profile2->displayProfileId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Check only one remains
        $profiles = (new XiboDisplayProfile($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startProfiles) + 1, count($profiles));
        $flag = false;
        foreach ($profiles as $profile) {
            if ($profile->displayProfileId == $profile1->displayProfileId) {
                $flag = true;
            }
        }
        $this->assertTrue($flag, 'Display profile ID ' . $profile1->displayProfileId . ' was not found after deleting a different Display Profile');
        $profile1->delete();
    }
}
