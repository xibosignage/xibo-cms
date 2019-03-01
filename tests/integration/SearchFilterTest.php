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
namespace Xibo\Tests\integration;

use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class SearchFilterTest
 * @package Xibo\Tests\integration
 */
class SearchFilterTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var XiboLayout */
    protected $layout;

    /** @var XiboLayout */
    protected $layout2;

    /** @var XiboLayout */
    protected $layout3;

    /** @var XiboLayout */
    protected $layout4;

    /** @var XiboLayout */
    protected $layout5;

    /** @var XiboLayout */
    protected $layout6;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for  ' . get_class() . ' Test');

        // Create 5 layouts to test with
        $this->layout = (new XiboLayout($this->getEntityProvider()))
            ->create(
                'integration layout 1',
                'PHPUnit Created Layout for Automated Integration Testing',
                '',
                $this->getResolutionId('landscape')
            );

        $this->layout2 = (new XiboLayout($this->getEntityProvider()))
            ->create(
                'integration example layout 2',
                'PHPUnit Created Layout for Automated Integration Testing',
                '',
                $this->getResolutionId('landscape')
            );

        $this->layout3 = (new XiboLayout($this->getEntityProvider()))
            ->create(
                'integration layout 3',
                'PHPUnit Created Layout for Automated Integration Testing',
                '',
                $this->getResolutionId('landscape')
            );

        $this->layout4 = (new XiboLayout($this->getEntityProvider()))
            ->create(
                'integration example 4',
                'PHPUnit Created Layout for Automated Integration Testing',
                '',
                $this->getResolutionId('landscape')
            );

        $this->layout5 = (new XiboLayout($this->getEntityProvider()))
            ->create(
                'example layout 5',
                'PHPUnit Created Layout for Automated Integration Testing',
                '',
                $this->getResolutionId('landscape')
            );

        $this->layout6 = (new XiboLayout($this->getEntityProvider()))
            ->create(
                'display different name',
                'PHPUnit Created Layout for Automated Integration Testing',
                '',
                $this->getResolutionId('landscape')
            );

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        $this->deleteLayout($this->layout);
        $this->deleteLayout($this->layout2);
        $this->deleteLayout($this->layout3);
        $this->deleteLayout($this->layout4);
        $this->deleteLayout($this->layout5);
        $this->deleteLayout($this->layout6);

        parent::tearDown();

    }
    // </editor-fold>

    /**
     * Search filter test.
     *
     * Single keyword
     */
    public function testSearch()
    {
        $this->client->get('/layout', ['layout' => 'integration']);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame(4, $object->data->recordsFiltered);
    }

    /**
     * Search filter test
     *
     * Comma separated
     */
    public function testSearchCommaSeparated()
    {
        $this->client->get('/layout', ['layout' => 'integration,example']);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame(5, $object->data->recordsFiltered);
    }

    /**
     * Search filter test
     *
     * Comma separated with spaces and not RLIKE
     */
    public function testSearchCommaSeparatedWithSpaces()
    {
        $this->client->get('/layout', ['layout' => 'integration layout, -3']);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame(2, $object->data->recordsFiltered);
    }

    /**
     * Search filter test
     *
     * Comma separated with not RLIKE filter
     */
    public function testSearchCommaSeparatedWithNotRlike()
    {
        $this->client->get('/layout', ['layout' => 'integration, -example']);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame(2, $object->data->recordsFiltered);
    }

    /**
     * Search filter test
     *
     * Comma separated with not RLIKE filter
     */
    public function testSearchCommaSeparatedWithNotRlike2()
    {
        $this->client->get('/layout', ['layout' => 'example, -layout']);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame(1, $object->data->recordsFiltered);
    }

    /**
     * Search filter test.
     *
     * partial match filter
     */
    public function testSearchPartialMatch()
    {
        $this->client->get('/layout', ['layout' => 'inte, exa, -5']);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame(4, $object->data->recordsFiltered);
    }

    /**
     * Search filter test.
     *
     * slightly more complex filter, with RLIKE, not RLIKE and spaces
     */
    public function testSearchComplex()
    {
        $this->client->get('/layout', ['layout' => 'integration, -1, -3, different name']);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame(3, $object->data->recordsFiltered);
    }
}