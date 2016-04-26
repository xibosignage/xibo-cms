<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroupTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\Tests\LocalWebTestCase;
use Xibo\Entity\DisplayGroup;
use Xibo\Helper\Random;

class DisplayGroupTest extends LocalWebTestCase
{


    protected $startDisplayGroups;

    /**
     * setUp - called before every test automatically
     */
    public function setUp()
    {  
        parent::setUp();
        $this->startDisplayGroups = $this->container->displayGroupFactory->query(null, []);
    }

    /**
     * assertPreConditions
     */
    public function assertPreConditions()
    {
        $this->assertEquals($this->startDisplayGroups, array());
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        $finalDisplayGroups = $this->container->displayGroupFactory->query(null, []);
        # Loop over any remaining display groups and nuke them
        foreach ($finalDisplayGroups as $displayGroup)
        {
            $displayGroup->setChildObjectDependencies($this->container->displayFactory,
                                                      $this->container->layoutFactory,
                                                      $this->container->mediaFactory,
                                                      $this->container->scheduleFactory
                                                      );
            $displayGroup->delete();
        }
        parent::tearDown();
    }

    /**
     *  List all display groups
     */ 

    public function testListAll()
    {
        $this->client->get('/displaygroup');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
//		fwrite(STDOUT, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }
/**
 *  List specific display groups
 */ 

/*
    public function testListAll2()
    {
        $this->client->get('/displaygroup', [
		'displayGroupId' => 7,
		'displayGroup' => 'Android'
        	]);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());
//		fwrite(STDOUT, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    */

   /**
    *  testAddSuccess - test adding various Display Groups that should be valid
    *  @dataProvider addSuccessCases
    */ 
    public function testAddSuccess($groupName, $groupDescription, $isDynamic, $expectedDynamic, $dynamicContent, $expectedDynamicContent)
    {
 
        $response = $this->client->post('/displaygroup', [
            'displayGroup' => $groupName,
            'description' => $groupDescription,
            'isDynamic' => $isDynamic,
            'dynamicContent' => $dynamicContent
        ]);
        
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($groupName, $object->data->displayGroup);
        $this->assertSame($groupDescription, $object->data->description);
        $this->assertSame($expectedDynamic, $object->data->isDynamic);
        $this->assertSame($expectedDynamicContent, $object->data->dynamicCriteria);
    }
    
   /**
    *  testAddFailure - test adding various Display Groups that should be invalid
    *  @dataProvider addFailureCases
    *  @expectedException \InvalidArgumentException
    */ 
    public function testAddFailure($groupName, $groupDescription, $isDynamic, $dynamicContent)
    {
        $response = $this->client->post('/displaygroup', [
            'displayGroup' => $groupName,
            'description' => $groupDescription,
            'isDynamic' => $isDynamic,
            'dynamicContent' => $dynamicContent
        ]);
    }


    public function addSuccessCases()
    {
    # Each array is a test run
    # Format
    # (Group Name, Group Description, isDynamic, Returned isDynamic (0 or 1),
    #  Criteria for Dynamic group, Returned Criteria for Dynamic group)
    # For example, if you set isDynamic to 0 and send criteria, it will come back
    # with criteria = null
        return array(
            array(Random::generateString(8, 'phpunit'), 'Api', 0, 0, '', null),
            array('Test de Français', 'Bienvenue à la suite de tests Xibo', 0, 0, null, null),
            array('Invalid isDynamic flag 1', 'Invalid isDynamic flag', 7, 0, 0, null, null),
            array('Invalid isDynamic flag 2 ', 'Invalid isDynamic flag', 7, 0, 'criteria', null),
            array('Invalid isDynamic flag alpha 1', 'Invalid isDynamic flag alpha', 'invalid', 0, null, null),
            array('Invalid isDynamic flag alpha 2', 'Invalid isDynamic flag alpha', 'invalid', 0, 'criteria', null)
        );
    }
    
    public function addFailureCases()
    {
        return array(
            array('Too long description', Random::generateString(255), 0, null),
            array('No dynamic criteria', 'No dynamic criteria', 1, null)
        );
    }
    
   /**
    *  Edit display group test
    *  @depends testAdd
    */ 

    public function testEdit($displayGroupId)
    {
    //    $displayGroup = $this->container->displaGroupFactory->getById($displayGroupId);

        $name = Random::generateString(8, 'phpunit');

        $this->client->put('/displaygroup/' . $displayGroupId, [
            'displayGroup' => $name,
            'description' => 'API',
            'isDynamic' => 0,
            'dynamicContent' => ''
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->displayGroup);

        return $displayGroupId;
    }

    /**
     * Test delete
     * @param int $displayGroupId
     * @depends testEdit
     */ 

    /*
        public function testDelete($displayGroupId)
    {
        $this->client->delete('/displaygroup/' . $displayGroupId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

  */

	/**
	 *Assign new displays Test
	 */

    public function testAssign()
    {

		$this->client->post('/displaygroup/' . 7 . '/display/assign', [
        'displayId' => [7]
        ]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
    }

    /**
	 *Unassign displays Test
	 */

    public function testUnassign()
    {

        $this->client->post('/displaygroup/' . 7 . '/display/unassign', [
        'displayId' => [7]
        ]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
    }

	/**
	 *Assign new display group Test
	 */

    public function testAssignGroup()
    {

		$this->client->post('/displaygroup/' . 7 . '/displayGroup/assign', [
        'displayGroupId' => [29]
        ]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
	 *Unassign displays group Test
	 */

    public function testUnassignGroup()
    {

		$this->client->post('/displaygroup/' . 7 . '/displayGroup/unassign', [
        	'displayGroupId' => [29]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
	 * Assign new media file to a group Test
	 */

    public function testAssignMedia()
    {

		$this->client->post('/displaygroup/' . 7 . '/media/assign', [
        	'mediaId' => [13, 17],
        	'unassignMediaId' => [13]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
	 * Unassign media files from a group Test
	 */

    public function testUnassignMedia()
    {

        $this->client->post('/displaygroup/' . 7 . '/media/unassign', [
        	'mediaId' => [17]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
	 * Assign new layouts to a group Test
	 */

    public function testAssignLayout()
    {

        $this->client->post('/displaygroup/' . 7 . '/layout/assign', [
        	'layoutId' => [51, 63],
        	'unassignLayoutsId' => [51]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
	 * Unassign layouts from a group Test     
	 *  does not work, method name differences between /routes and controller/displayGroup
	 */

    public function testUnassignLayout()
    {

		$this->client->post('/displaygroup/' . 7 . '/layout/unassign', [
        	'layoutId' => [63]
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
	 * Assign apk version to a group Test
	 */

    public function testVersion()
    {

        $this->client->post('/displaygroup/' . 7 . '/version', [
        	'mediaId' => 18
        	]);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
    }

    /**
     * Collect now action test
     */
/*
   	public function testCollect()
    {

        $this->client->post('/displaygroup/' . 7 . '/action/collectNow');

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

    }
*/
    /**
     * Change Layout action test
     */
/*
   	public function testChange()
    {

        $this->client->post('/displaygroup/' . 7 . '/action/changeLayout', [
		'layoutId' => 3,
		'duration' => 900,  
		'downloadRequired' => 1,
		'changeMode' => 'queue'
    	]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

    }
*/
    /**
     * Revert to Schedule action test
     */
/*
   	public function testRevert()
    {

        $this->client->post('/displaygroup/' . 7 . '/action/revertToSchedule');

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

    }
*/
    /**
     * Send command action test
     */
/*
   	public function testCommand()
    {

        $this->client->post('/displaygroup/' . 7 . '/action/command' , [
		'commandId' => 5
        	]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());

    }
*/

}
