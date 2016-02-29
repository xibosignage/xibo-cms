<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CampaignTest.php)
 */

namespace Xibo\Tests;

use Xibo\Factory\CampaignFactory;
use Xibo\Factory\LayoutFactory;

class CampaignTest extends LocalWebTestCase
{
    public function testListAll()
    {
        $this->client->get('/campaign');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
    }

    public function testAdd()
    {
        $name = \Xibo\Helper\Random::generateString(8, 'phpunit');

        $this->client->post('/campaign', [
            'name' => $name
        ]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->campaign);

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

        $this->client->put('/campaign/' . $campaignId, [
            'name' => $name
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame($name, $object->data->campaign);

        return $campaignId;
    }

    /**
     * @param $campaignId
     * @depends testEdit
     */
    public function testDelete($campaignId)
    {
        $this->client->delete('/campaign/' . $campaignId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    public function testAssignLayout()
    {
        // Make a campaign with a known name
        $name = \Xibo\Helper\Random::generateString(8, 'phpunit');

        $campaign = (new CampaignFactory($this->getApp()))->create($name, 1);
        $campaign->save();

        $layout = (new LayoutFactory($this->getApp()))->query(null, ['start' => 1, 'length' => 1]);

        $this->assertGreaterThan(0, count($layout), 'Cannot find layout for test');

        // Call assign on the default layout
        $this->client->post('/campaign/layout/assign/' . $campaign->campaignId, ['layoutId' => [['layoutId' => $layout[0]->layoutId, 'displayOrder' => 1]]]);
        $this->assertSame(200, $this->client->response->status(), '/campaign/layout/assign/' . $campaign->campaignId . '. Body: ' . $this->client->response->body());

        // Get this campaign and check it has 1 layout
        $campaignCheck = (new CampaignFactory($this->getApp()))->getById($campaign->campaignId);

        $this->assertSame($campaign->campaignId, $campaignCheck->campaignId, $this->client->response->body());
        $this->assertSame(1, $campaignCheck->numberLayouts, $this->client->response->body());

        return $campaign->campaignId;
    }

    /**
     * @param int $campaignId
     * @depends testAssignLayout
     */
    public function testUnassignLayout($campaignId)
    {
        // Get any old layout
        $layout = (new LayoutFactory($this->getApp()))->query(null, ['start' => 1, 'length' => 1]);

        // Call assign on the default layout
        $this->client->post('/campaign/layout/unassign/' . $campaignId, ['layoutId' => [['layoutId' => $layout[0]->layoutId, 'displayOrder' => 1]]]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        // Get this campaign and check it has 0 layouts
        $campaign = (new CampaignFactory($this->getApp()))->getById($campaignId);

        $this->assertSame($campaignId, $campaign->campaignId, $this->client->response->body());
        $this->assertSame(0, $campaign->numberLayouts, $this->client->response->body());
    }

    public function testDeleteAllTests()
    {
        // Get a list of all phpunit related campaigns
        $campaigns = (new CampaignFactory($this->getApp()))->query(null, ['name' => 'phpunit']);

        foreach ($campaigns as $campaign) {

            // Check the name
            $this->assertStringStartsWith('phpunit', $campaign->campaign, 'Non-phpunit campaign found');

            // Issue a delete
            $delete = (new CampaignFactory($this->getApp()))->getById($campaign->campaignId);
            $delete->delete();
        }
    }
}
