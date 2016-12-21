<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (NotificationTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\OAuth2\Client\Entity\XiboNotification;
use Xibo\OAuth2\Client\Entity\XiboUserGroup;
use Xibo\Tests\LocalWebTestCase;

class NotificationTest extends LocalWebTestCase
{
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startDisplayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startNotifications = (new XiboNotification($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
            // tearDown all display groups that weren't there initially
            $finalDisplayGroups = (new XiboDisplayGroup($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

            # Loop over any remaining display groups and nuke them
            foreach ($finalDisplayGroups as $displayGroup) {
                /** @var XiboDisplayGroup $displayGroup */

                $flag = true;

                foreach ($this->startDisplayGroups as $startGroup) {
                   if ($startGroup->displayGroupId == $displayGroup->displayGroupId) {
                       $flag = false;
                   }
                }

                if ($flag) {
                    try {
                        $displayGroup->delete();
                    } catch (\Exception $e) {
                        fwrite(STDERR, 'Unable to delete ' . $displayGroup->displayGroupId . '. E:' . $e->getMessage());
                    }
                }
            }

            // tearDown all notifications that weren't there initially
            $finalNotifications = (new XiboNotification($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

            # Loop over any remaining notifications and nuke them
            foreach ($finalNotifications as $notification) {
                /** @var XiboNotification $notification */

                $flag = true;

                foreach ($this->startNotifications as $startNotf) {
                   if ($startNotf->notificationId == $notification->notificationId) {
                       $flag = false;
                   }
                }

                if ($flag) {
                    try {
                        $notification->delete();
                    } catch (\Exception $e) {
                        fwrite(STDERR, 'Unable to delete ' . $notification->notificationId . '. E:' . $e->getMessage());
                    }
                }
            }
        parent::tearDown();
    }

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
        $this->client->post('/notification', [
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
    }

    /**
    * Delete notification
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
    */
    public function testEdit()
    {
		# Create new display group
    	$displayGroup = (new XiboDisplayGroup($this->getEntityProvider()))->create('phpunit group', 'phpunit description', 0, '');
    	# Create new notification
    	$notification = (new XiboNotification($this->getEntityProvider()))->create('API subject', 'API body', '2016-09-01 00:00:00', 0, 0, [$displayGroup->displayGroupId]);
    	$notification->releaseDt = date('Y-m-d H:i:s', $notification->releaseDt);
        # Create new subject
    	$subjectNew = 'Subject edited via API';
    	# Edit our notification
    	$this->client->put('/notification/' . $notification->notificationId, [
    		'subject' => $subjectNew,
    		'body' => $notification->body,
    		'releaseDt' => $notification->releaseDt,
    		'isEmail' => $notification->isEmail,
    		'isInterrupt' => $notification->isInterrupt,
            'displayGroupIds' => [$displayGroup->displayGroupId]
    		], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

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
