<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
use Xibo\OAuth2\Client\Exception\XiboApiException;

/**
 * Class DisplayProfileTest
 * @package Xibo\Tests\Integration
 */
class DisplayProfileTestDelete extends \Xibo\Tests\LocalWebTestCase
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
        if ($this->displayProfile !== null) {
            $this->displayProfile->delete();
        }

        parent::tearDown();
    }

    /**
     * Test delete
     */
    public function testDelete()
    {
        // Delete the one we created last
        $response = $this->sendRequest('DELETE','/displayprofile/' . $this->displayProfile->displayProfileId);

        // This should return 204 for success
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());

        // Check only one remains
        try {
            $displayProfile = (new XiboDisplayProfile($this->getEntityProvider()))->getById($this->displayProfile->displayProfileId);

            $this->fail('Display profile ID ' . $this->displayProfile->displayProfileId . ' was not found after deleting a different Display Profile');
        } catch (XiboApiException $exception) {
            // We know we've deleted it, so no clear for tearDown
            $this->displayProfile = null;
        }
    }
}
