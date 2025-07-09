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

        $this->getLogger()->debug('Setup test for  ' . get_class($this) . ' Test');

        // Create 6 layouts to test with
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
        $response = $this->sendRequest('GET', '/layout', ['layout' => 'integration']);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(4, $object->data->recordsFiltered);
    }

    /**
     * Search filter test
     *
     * Comma separated
     */
    public function testSearchCommaSeparated()
    {
        $response = $this->sendRequest('GET', '/layout', ['layout' => 'integration,example']);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(5, $object->data->recordsFiltered);
    }

    /**
     * Search filter test
     *
     * Comma separated with not RLIKE filter
     */
    public function testSearchCommaSeparatedWithNotRlike()
    {
        $response = $this->sendRequest('GET', '/layout', ['layout' => 'integration layout, -example']);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(4, $object->data->recordsFiltered);
    }

    /**
     * Search filter test
     *
     * Comma separated with not RLIKE filter
     */
    public function testSearchCommaSeparatedWithNotRlike2()
    {
        $response = $this->sendRequest('GET', '/layout', ['layout' => 'example, -layout']);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(4, $object->data->recordsFiltered);
    }

    /**
     * Search filter test.
     *
     * partial match filter
     */
    public function testSearchPartialMatch()
    {
        $response = $this->sendRequest('GET', '/layout', ['layout' => 'inte, exa']);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(5, $object->data->recordsFiltered);
    }

    /**
     * Search filter test.
     *
     * using regexp
     */
    public function testSearchWithRegEx()
    {
        $response = $this->sendRequest('GET', '/layout', ['layout' => 'name$', 'useRegexForName' => 1]);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(1, $object->data->recordsFiltered);
    }

    /**
     * Search filter test.
     *
     * using regexp
     */
    public function testSearchWithRegEx2()
    {
        $response = $this->sendRequest('GET', '/layout', ['layout' => '^example, ^disp', 'useRegexForName' => 1]);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame(2, $object->data->recordsFiltered);
    }
}
