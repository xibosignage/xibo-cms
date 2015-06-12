<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CampaignTest.php)
 */


class CampaignTest extends TestCase
{
    public function __construct()
    {
        parent::__construct('Campaign Test');

        $this->start();
    }

    public function testListAll()
    {
        $response = Requests::get($this->url('/campaign'));

        $this->assertSame(200, $response->status_code);
        $this->assertNotEmpty($response);

        $object = json_decode($response->body);

        $this->assertObjectHasAttribute('data', $object);
    }

    public function testAdd()
    {
        $name = $this->generateRandomString();

        $response = Requests::post($this->url('/campaign'), [], [
            'name' => $name
        ]);

        $this->assertSame(200, $response->status_code);

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
        $name = $this->generateRandomString();

        $response = Requests::put($this->url('/campaign/' . $campaignId), [], [
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
        $response = Requests::delete($this->url('/campaign/' . $campaignId));

        $this->assertSame(200, $response->status_code, $response->body);
    }
}
