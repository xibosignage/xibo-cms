<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CommandTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\OAuth2\Client\Entity\XiboCommand;
use Xibo\Tests\LocalWebTestCase;
use Xibo\Helper\Random;


class CommandTest extends LocalWebTestCase
{
  protected $startCommands;
    
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startCommands = (new XiboCommand($this->getEntityProvider()))->get();
    }
    
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all commands that weren't there initially
        $finalCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 1000]);
        # Loop over any remaining commands and nuke them
        foreach ($finalCommands as $command) {
            /** @var XiboCommand $command */
            $flag = true;
            foreach ($this->startCommands as $startCom) {
               if ($startCom->commandId == $command->commandId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $command->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $command->commandId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

	/**
     * Shows this user commands
     */
    public function testListAll()
    {
        $this->client->get('/command');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

     /**
     * testAddSuccess - test adding various commands that should be valid
     * @dataProvider provideSuccessCases
     * @group minimal
     */
    public function testAddSuccess($commandName, $commandDescription, $commandCode)
    {

        // Loop through any pre-existing commands to make sure we're not
        // going to get a clash
        foreach ($this->startCommands as $tmpCom) {
            if ($tmpCom->command == $commandName) {
                $this->skipTest("There is a pre-existing command with this name");
                return;
            }
        }

        $response = $this->client->post('/command', [
            'command' => $commandName,
            'description' => $commandDescription,
            'code' => $commandCode
        ]);
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
//        fwrite(STDERR, $this->client->response->body());
       
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($commandName, $object->data->command);
        $this->assertSame($commandDescription, $object->data->description);
        $this->assertSame($commandCode, $object->data->code);

        # Check that the command was added correctly
        $command = (new XiboCommand($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($commandName, $command->command);
        $this->assertSame($commandDescription, $command->description);
        $this->assertSame($commandCode, $command->code);
        # Clean up the commands as we no longer need it
        $this->assertTrue($command->delete(), 'Unable to delete ' . $command->commandId);
    }

    /**
     * Each array is a test run
     * Format (command name, description, code)
     * @return array
     */

        public function provideSuccessCases()
    {
        return [
            'Test case 1' => ['test command', 'test description', '-reboot'],
            'Test case 2' => ['test command 2', 'aaa', '|01100100|01100001|01101110|00001101'],
            'Test case 3' => ['test command', 'test description', '-sleep'],
            'Test case 4' => ['test command 2', 'aaa', 'beep beeeep beeep']
        ];
    }


    /**
     * testAddFailure - test adding various commands that should be invalid
     * @dataProvider provideFailureCases
     */

        public function testAddFailure($commandName, $commandDescription, $commandCode)
    {
        try {
            $response = $this->client->post('/command', [
            'command' => $commandName,
            'description' => $commandDescription,
            'code' => $commandCode
        ]);
        }
        catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
            $this->closeOutputBuffers();
            return;
        }
        $this->fail('InvalidArgumentException not raised');
    }

    /**
     * Each array is a test run
     * Format (command name, description, code)
     * @return array
     */

    public function provideFailureCases()
    {
        return [
            'Test case 1' => ['No code', 'aa', NULL],
            'Test case 2' => ['no description', NULL, 'code'], 
            'French command case' => ['Test de Français 1', 'Bienvenue à la suite de tests Xibo', NULL],
            'German command case' => ['Deutsch Prüfung 1', 'Weiß mit schwarzem Text', NULL] 
        ];
    }

    /**
     *  List all commands known set
     *  @group minimal
     *  @depends testAddSuccess
     */
    public function testListKnown()
    {
        $cases =  $this->provideSuccessCases();
        $commands = [];
        // Check each possible case to ensure it's not pre-existing
        // If it is, skip over it
        foreach ($cases as $case) {
            $flag = true;
            foreach ($this->startCommands as $tmpCom) {
                if ($case[0] == $tmpCom->command) {
                    $flag = false;
                }
            }
            if ($flag) {
                $commands[] = (new XiboCommand($this->getEntityProvider()))->create($case[0],$case[1],$case[2]);
            }
        }
        $this->client->get('/command');
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # There should be as many commands as we created plus the number we started with in the system
        $this->assertEquals(count($commands) + count($this->startCommands), $object->data->recordsTotal);
        # Clean up the groups we created
        foreach ($commands as $com) {
            $com->delete();
        }
    }

    /**
     * Edit an existing comand
     * @depends testAddSuccess
     */
    public function testEdit()
    {
        # Load in a known command
        /** @var XiboCommand $command */
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit description', 'phpunit code');
        # Change the comand name, description and code
        $name = Random::generateString(8, 'command');
        $description = Random::generateString(8, 'description');
        $code = Random::generateString(8, 'code');

        $this->client->put('/command/' . $command->commandId, [
            'command' => $name,
            'description' => $description,
            'code' => $code
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->command);
        $this->assertSame($description, $object->data->description);
        $this->assertSame($code, $object->data->code);

        # Check that the command was actually renamed
        $command = (new XiboCommand($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $command->command);
        $this->assertSame($description, $command->description);
        $this->assertSame($code, $command->code);

        # Clean up the Layout as we no longer need it
        $command->delete();
    }

     /**
     * Test delete
     * @depends testAddSuccess
     * @group minimal
     */
    public function testDelete()
    {
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known commands
        $command1 = (new XiboCommand($this->getEntityProvider()))->create($name1, 'phpunit description', 'code');
        $command2 = (new XiboCommand($this->getEntityProvider()))->create($name2, 'phpunit description', 'code2');
        # Delete the one we created last
        $this->client->delete('/command/' . $command2->commandId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Check only one remains
        $commands = (new XiboCommand($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startCommands) + 1, count($commands));
        $flag = false;
        foreach ($commands as $command) {
            if ($command->commandId == $command1->commandId) {
                $flag = true;
            }
        }
        $this->assertTrue($flag, 'Command ID ' . $command1->commandId . ' was not found after deleting a different command');
        $command1->delete();
    }

}
