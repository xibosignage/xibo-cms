<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CampaignTest.php)
 */

namespace Xibo\Tests;

class CampaignLocalWebTest extends LocalWebTestCase
{
    public function __construct()
    {
        parent::__construct('Campaign Test');

        $this->start();
    }

    public function testListAll()
    {
        $response = \Requests::get($this->url('/campaign'));

        $this->assertSame(200, $response->status_code);
        $this->assertNotEmpty($response);

        $object = json_decode($response->body);

        $this->assertObjectHasAttribute('data', $object);
    }

    public function testAdd()
    {
        $name = \Xibo\Helper\Random::generateString(8, 'phpunit');

        $response = \Requests::post($this->url('/campaign'), [], [
            'name' => $name
        ]);

        $this->assertSame(200, $response->status_code, $response->body);

        $object = json_decode($response->body);

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data[0]->campaign);

        return $object->id;
    }

    /**
     * Test edit
     * @param int $campaignId
     * @return int the id
     * @depends testAdd
     */
    public function testEdit($campaignId)
    {
        $name = \Xibo\Helper\Random::generateString(8, 'phpunit');

        $response = \Requests::put($this->url('/campaign/' . $campaignId), [], [
            'name' => $name
        ]);

        $this->assertSame(200, $response->status_code);

        $object = json_decode($response->body);

        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame($name, $object->data[0]->campaign);

        return $campaignId;
    }

    /**
     * @param $campaignId
     * @depends testEdit
     */
    public function testDelete($campaignId)
    {
        $response = \Requests::delete($this->url('/campaign/' . $campaignId));

        $this->assertSame(200, $response->status_code, $response->body);
    }

    public function testAssignLayout()
    {
        // Make a campaign with a known name
        $name = \Xibo\Helper\Random::generateString(8, 'phpunit');

        $response = \Requests::post($this->url('/campaign'), [], [
            'name' => $name
        ]);

        // Get the campaignId out
        $this->assertSame(200, $response->status_code);

        $object = json_decode($response->body);
        $id = $object->id;

        // Call assign on the default layout
        $response = \Requests::post($this->url('/campaign/layout/assign/') . $id, [], ['layoutIds' => [8]]);
        $this->assertSame(200, $response->status_code, $response->body);

        // Get this campaign and check it has 0 layouts
        $response = \Requests::get($this->url('/campaign') . '?campaignId=' . $id);
        $this->assertSame(200, $response->status_code, $response->body);

        $object = json_decode($response->body);
        $this->assertObjectHasAttribute('data', $object);

        $this->assertSame($id, $object->data[0]->campaignId, $response->body);
        $this->assertSame(1, $object->data[0]->numberLayouts, $response->body);

        return $id;
    }

    /**
     * @param int $campaignId
     * @depends testAssignLayout
     */
    public function testUnassignLayout($campaignId)
    {
        // Call assign on the default layout
        $response = \Requests::post($this->url('/campaign/layout/unassign/') . $campaignId, [], ['layoutIds' => [8]]);

        $this->assertSame(200, $response->status_code, $response->body);

        // Get this campaign and check it has 0 layouts
        $response = \Requests::get($this->url('/campaign') . '?campaignId=' . $campaignId);
        $this->assertSame(200, $response->status_code, $response->body);

        $object = json_decode($response->body);
        $this->assertObjectHasAttribute('data', $object);

        $this->assertSame($campaignId, $object->data[0]->campaignId);
        $this->assertSame(0, $object->data[0]->numberLayouts);
    }

    public function testDeleteAllTests()
    {
        // Get a list of all phpunit related campaigns
        $response = \Requests::get($this->url('/campaign') . '?name=phpunit');
        $this->assertSame(200, $response->status_code, $response->body);

        $object = json_decode($response->body);
        $this->assertObjectHasAttribute('data', $object);

        foreach ($object->data as $campaign) {

            // Check the name
            $this->assertStringStartsWith('phpunit', $campaign->campaign, 'Non-phpunit campaign found');

            // Issue a delete
            $response = \Requests::delete($this->url('/campaign/' . $campaign->campaignId));

            $this->assertSame(200, $response->status_code, $response->body);
        }
    }
}
