<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Tests\integration;

use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Tests copying a display group.
 */
class DisplayGroupCopyTest extends LocalWebTestCase
{
    use DisplayHelperTrait;

    /** @var XiboDisplay */
    protected $display;

    /** @var XiboDisplay */
    protected $display2;

    /** @var XiboDisplayGroup */
    protected $displayGroup;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for Cache ' . get_class($this) . ' Test');

        // Create a couple of displays to use in the test
        $this->display = $this->createDisplay();
        $this->display2 = $this->createDisplay();

        // Create a display group and assign both displays
        $this->displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create(
            'phpunit_' . bin2hex(random_bytes(4)),
            '',
            0,
            null
        );

        // Assign our two displays
        $this->displayGroup->assignDisplay($this->display->displayId);
        $this->displayGroup->assignDisplay($this->display2->displayId);

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        parent::tearDown();

        // Delete the Display
        $this->deleteDisplay($this->display);
        $this->deleteDisplay($this->display2);
        $this->displayGroup->delete();
    }
    // </editor-fold>

    public function testCopyPlain()
    {
        $response = $this->sendRequest('POST', '/displaygroup/' . $this->displayGroup->displayGroupId  . '/copy', [
            'displayGroup' => 'phpunit_' . bin2hex(random_bytes(4)),
            'description' => 'copied',
            'copyMembers' => 0,
            'copyAssignments' => 0,
            'copyTags' => 0,
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame('copied', $object->data->description);

        // Check there aren't any displays assigned.
        $results = $this->getStore()->select('SELECT COUNT(*) AS cnt FROM lkdisplaydg WHERE displayGroupId = :displayGroupId', [
            'displayGroupId' => $object->id
        ]);

        $this->assertEquals(0, intval($results[0]['cnt']));

        (new XiboDisplayGroup($this->getEntityProvider()))->getById($object->id)->delete();
    }

    public function testCopyMembers()
    {
        $response = $this->sendRequest('POST', '/displaygroup/' . $this->displayGroup->displayGroupId  . '/copy', [
            'displayGroup' => 'phpunit_' . bin2hex(random_bytes(4)),
            'description' => 'copied',
            'copyMembers' => 1,
            'copyAssignments' => 0,
            'copyTags' => 0,
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame('copied', $object->data->description);

        // Check there aren't any displays assigned.
        $results = $this->getStore()->select('SELECT COUNT(*) AS cnt FROM lkdisplaydg WHERE displayGroupId = :displayGroupId', [
            'displayGroupId' => $object->id
        ]);

        $this->assertEquals(2, intval($results[0]['cnt']));

        (new XiboDisplayGroup($this->getEntityProvider()))->getById($object->id)->delete();
    }
}
