<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayTest.php)
 */

namespace Xibo\Tests\Integration;
use Xibo\Tests\LocalWebTestCase;
use Xibo\Entity\Display;

class DisplayTest extends \Xibo\Tests\LocalWebTestCase
{

	 /**
     * Shows list of all displays Test
     */

    public function testListAll()
    {
        $this->client->get('/display');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
//      fwrite(STDERR, $this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * Shows specific display test with filters 
     */

    public function testListAll2()
    {
        $this->client->get('/display', [
       'displayId' => 2,
//     'displayGroupId' =>
       'display' => 'Android Peter',
//      'macAddress' => '',
       'ClientVersion' => '1.7'
            ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
//      fwrite(STDERR, $this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     *  Delete Display Test
     */

        public function testDelete()
    {
        $this->client->delete('/display/' . 5);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
   


   /**
   * Edit Display test
   */

        public function testEdit()
        {
             $this->client->put('/display/' . 2, [
            'display' => 'Android Peter',
            'description' =>'z64',
            'isAuditing' => 0,
            'defaultLayoutId' => 63,
            'licensed' => 1,
 //           'license' => '',
            'incSchedule' => 0,
            'emailAlert' => 0,
            'alertTimeout' =>0,
            'wakeOnLanEnabled' => 0,
//            'wakeOnLanTime' =>
//            'broadCastAddress' =>
//           'secureOn' =>
//            'cidr' =>
//            'latidute' =>
//            'longitude' =>
//            'displayProfileId' =>
//            'clearCachedData' =>
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

            $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

            $object = json_decode($this->client->response->body());
            fwrite(STDERR, $this->client->response->body());
        } 

    /**
    * Request screenshot Test
    * Will tell if zeroMQ is required
    */

    public function testScreenshot()
    {
        $this->client->put('/display/requestscreenshot/' . 2);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

    }
    

    /**
    * Wake On Lan Test
    */

    public function testWoL()
    {
        $this->client->put('/display/wol/' . 2);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }
}
