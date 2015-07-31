<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (AuditLogTest.php)
 */


class AuditLogTest extends \Xibo\Tests\LocalWebTestCase
{
    public function testSearch()
    {
        $this->client->get('/audit');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertObjectHasAttribute('draw', $object->data, $this->client->response->body());
        $this->assertObjectHasAttribute('recordsTotal', $object->data, $this->client->response->body());
        $this->assertObjectHasAttribute('recordsFiltered', $object->data, $this->client->response->body());

        // Make sure the recordsTotal is not greater than 10 (the default paging)
        $this->assertLessThanOrEqual(10, count($object->data));
    }

    public function testExportForm()
    {
        $response = $this->client->get('/audit/form/export');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($response);
    }

    public function testExport()
    {
        $this->client->get('/audit/export');
        $this->assertSame(200, $this->client->response->status());
    }
}
