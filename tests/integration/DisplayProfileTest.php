<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (displayProfileTest.php)
 */

namespace Xibo\Tests\Integration;
use Xibo\Entity\DisplayProfile;
use Xibo\Entity\Display;
use Xibo\Tests\LocalWebTestCase;
use Xibo\Helper\Random;


class CommandTest extends \Xibo\Tests\LocalWebTestCase
{

	 /**
     * Shows all display pofiles
     */
    
    public function testListAll()
    {
        $this->client->get('/displayprofile');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
//		fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

     /**
     * Shows specific display pofiles
     */

    public function testListAll2()
    {
        $this->client->get('/displayprofile' , [
//		'displayprofileId' => 3,
		'displayprofile' => 'Android',
		'type' => 'android'
        	]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
//		fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }


    /**
     * 	Add new profile test
     */

        public function testAdd()
    {

    	$name = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/displayprofile', [
        'name' => $name,
        'type' => 'android',
        'isDefault' => 0
            ]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
        fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);

        return $object->id;
    }

	/**
	* Edit profile Test
	* @depends testAdd
	*/

        public function testEdit($displayProfileId)
    {

		$name = Random::generateString(8, 'phpunit');

        $this->client->put('/displayprofile/' . $displayProfileId, [
            'name' => $name,
            'type' => 'android',
            'isDefault' => 0
        ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $response);

        $object = json_decode($this->client->response->body());
     //   fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);


        return $displayProfileId;
    }

    /**
	* Edit profile Test
	* @depends testAdd
	*/

        public function testEdit2()
    {

		$name = Random::generateString(8, 'phpunit');

        $this->client->put('/displayprofile/' . 27, [
            'name' => $name,
            'type' => 'android',
            'isDefault' => 0
        ]);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $response);

        $object = json_decode($this->client->response->body());
     //   fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->name);


        return $displayProfileId;
    }

    /**
     * 	delete added profile test
     */
/*
        public function testDelete($displayProfileId)
    {
        $this->client->delete('/displayprofile/' . $displayProfileId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
*/

    /**
     * 	Delete specific profile
     */

/*
        public function testDelete2()
    {
        $this->client->delete('/displayprofile/' . 27);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
*/
}