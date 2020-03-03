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

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplayProfile;

/**
 * Class DisplayProfileTest
 * @package Xibo\Tests\Integration
 */
class DisplayProfileTestEdit extends \Xibo\Tests\LocalWebTestCase
{
    /** @var XiboDisplayProfile */
    private $displayProfile;

    public function setup()
    {
        parent::setup();

        $this->displayProfile = (new XiboDisplayProfile($this->getEntityProvider()))->create(Random::generateString(), 'android', 0);
    }

    protected function tearDown()
    {
        $this->displayProfile->delete();

        parent::tearDown();
    }

    /**
     * Edit an existing profile
     */
    public function testEdit()
    {
        // Call edit on the profile.
        $name = Random::generateString(8, 'phpunit');
        $response = $this->sendRequest('PUT','/displayprofile/' . $this->displayProfile->displayProfileId, [
            'name' => $name,
            'type' => $this->displayProfile->type,
            'isDefault' => $this->displayProfile->isDefault
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());

        // Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame('android', $object->data->type);

        // Check that the profile was actually renamed
        $displayProfile = (new XiboDisplayProfile($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $displayProfile->name);
    }

    /**
     * Edit an existing profile
     */
    public function testEditConfig()
    {
        // Call edit on the profile.
        $name = Random::generateString(8, 'phpunit');
        $response = $this->sendRequest('PUT','/displayprofile/' . $this->displayProfile->displayProfileId, [
            'name' => $name,
            'type' => $this->displayProfile->type,
            'isDefault' => $this->displayProfile->isDefault,
            'emailAddress' => 'phpunit@xibo.org.uk'
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getStatusCode());

        $object = json_decode($response->getBody());

        // Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);
        $this->assertSame('android', $object->data->type);

        foreach ($object->data->config as $config) {
            if ($config->name === 'emailAddress') {
                $this->assertSame('phpunit@xibo.org.uk', $config->value, json_encode($object->data->config));
            }
        }

        // Check that the profile was actually renamed
        $displayProfile = (new XiboDisplayProfile($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $displayProfile->name);
    }
}
