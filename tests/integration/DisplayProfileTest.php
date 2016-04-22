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
//        fwrite(STDERR, $this->client->response->body());

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

        $displayProfile = $this->container->displayProfileFactory->getById($displayProfileId);
		$name = Random::generateString(8, 'phpunit');

        $this->client->put('/displayprofile/' . $displayProfileId, [
            'name' => $name,
            'type' => $displayProfile->type,
            'isDefault' => $displayProfile->isDefault
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
//	    fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);

// 		we need to go deeper

        $object = $this->container->displayProfileFactory->getById($displayProfileId);

        $this->assertSame($name, $object->name);


        return $displayProfileId;
    }

    /**
	* Edit specific profile Test
	*/
/*
        public function testEdit2()
    {
    	$displayProfileId = 23;
        $displayProfile = $this->container->displayProfileFactory->getById($displayProfileId);

		
//		$name = Random::generateString(8, 'phpunit');

        $this->client->put('/displayprofile/' . $displayProfileId, [
            'name' => 'API EDITED',
            'type' => $displayProfile->type,
            'isDefault' => $displayProfile->isDefault
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
//	    fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
    }
*/
    /**
     * 	delete added profile test
     * @depends testAdd
     */

        public function testDelete($displayProfileId)
    {
        $this->client->delete('/displayprofile/' . $displayProfileId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }


    /**
     * 	Delete specific profile
     */

/*
        public function testDelete2()
    {
        $this->client->delete('/displayprofile/' . 4);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }
*/
}