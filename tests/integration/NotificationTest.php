<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (NotificationTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\OAuth2\Client\Entity\XiboUserGroup;
use Xibo\OAuth2\Client\Entity\XiboNotification;
use Xibo\Helper\Random;
use Xibo\Tests\LocalWebTestCase;

class NotificationTest extends LocalWebTestCase
{

	/**
     * List notifications
     */
    public function testListAll()
    {
        # Get all notifications
        $this->client->get('/notification');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
    * Create new Notification
    */
    public function testAdd()
    {
    	# Create new display group
    	$displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'notification description', 0, '');
    	# Add new notification and assign it to our display group
    	$subject = 'API Notification';
    	$this->client->post('/notification' , [
    		'subject' => $subject,
    		'body' => 'Notification body text',
    		'releaseDt' => '2016-09-01 00:00:00',
    		'isEmail' => 0,
    		'isInterrupt' => 0,
    		'displayGroupIds' => [$displayGroup->displayGroupId]
    	//	'userGroupId' =>
    	]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # Check if the subject is correctly set
        $this->assertSame($subject, $object->data->subject);

        $displayGroup->delete();
    }

    /**
    * Delete notification
    * @group broken
    */
    public function testDelete()
    {
		# Create new display group
    	$displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
    	# Create new notification
    	$notification = (new XiboNotification($this->getEntityProvider()))->create('API subject', 'API body', '2016-09-01 00:00:00', 0, 0, [$displayGroup->displayGroupId]);
    	# Delete notification
        $this->client->delete('/notification/' . $notification->notificationId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
        # Clean up
        $displayGroup->delete();
    }

   /**
    * Edit notification
    * @group broken
    */
    public function testEdit()
    {
		# Create new display group
    	$displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
    	# Create new notification
    	$notification = (new XiboNotification($this->getEntityProvider()))->create('API subject', 'API body', '2016-09-01 00:00:00', 0, 0, [$displayGroup->displayGroupId]);
    	# Create new subject
    	$subjectNew = 'Subject edited via API';
    	# Edit our notification
    	$this->client->put('/notification/' . $notification->notificationId, [
    		'subject' => $subjectNew,
    		'body' => $notification->body,
    		'releaseDt' => $notification->releaseDt,
    		'isEmail' => $notification->isEmail,
    		'isInterrupt' => $notification->isInterrupt
    		]);

    	$this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($subjectNew, $object->data->subject);
        # Clean up
        $displayGroup->delete();
        $notification->delete();
    }
}
