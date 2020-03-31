<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Tests\Integration;

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;
use Xibo\OAuth2\Client\Entity\XiboDisplayGroup;
use Xibo\OAuth2\Client\Entity\XiboNotification;
use Xibo\Tests\LocalWebTestCase;

class NotificationTest extends LocalWebTestCase
{
    /** @var XiboDisplayGroup[] */
    protected $startDisplayGroups;

    /** @var XiboNotification[] */
    protected $startNotifications;

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
        $response = $this->sendRequest('GET','/notification');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
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
        $response = $this->sendRequest('POST','/notification', [
    		'subject' => $subject,
    		'body' => 'Notification body text',
    		'releaseDt' => '2016-09-01 00:00:00',
    		'isEmail' => 0,
    		'isInterrupt' => 0,
    		'displayGroupIds' => [$displayGroup->displayGroupId]
    	//	'userGroupId' =>
    	]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
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
        $response = $this->sendRequest('DELETE','/notification/' . $notification->notificationId);
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status);
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
    	$notification->releaseDt = Carbon::createFromTimestamp($notification->releaseDt)->format(DateFormatHelper::getSystemFormat());
        # Create new subject
    	$subjectNew = 'Subject edited via API';
    	# Edit our notification
    	$response = $this->sendRequest('PUT','/notification/' . $notification->notificationId, [
    		'subject' => $subjectNew,
    		'body' => $notification->body,
    		'releaseDt' => $notification->releaseDt,
    		'isEmail' => $notification->isEmail,
    		'isInterrupt' => $notification->isInterrupt,
            'displayGroupIds' => [$displayGroup->displayGroupId]
    		], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

    	$this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
        
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($subjectNew, $object->data->subject);
        # Clean up
        $displayGroup->delete();
        $notification->delete();
    }
}
