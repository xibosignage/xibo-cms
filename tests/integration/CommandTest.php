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

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboCommand;
use Xibo\Tests\LocalWebTestCase;


class CommandTest extends LocalWebTestCase
{
  protected $startCommands;
    
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all commands that weren't there initially
        $finalCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
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
        # Get the list of all commands
        $response = $this->sendRequest('GET','/command');
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
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
        # Add new comands with arguments from provideSuccessCases
        $response = $this->sendRequest('POST','/command', [
            'command' => $commandName,
            'description' => $commandDescription,
            'code' => $commandCode
        ]);
        $this->assertSame(200, $response->getStatusCode(), "Not successful: " . $response->getBody());
        $object = json_decode($response->getBody());
        # Check if commands were added successfully and have correct parameters
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($commandName, $object->data->command);
        $this->assertSame($commandDescription, $object->data->description);
        $this->assertSame($commandCode, $object->data->code);

        # Check again that the command was added correctly
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
        # Cases we provide to testAddSuccess, you can extend it by simply adding new case here
        return [
            'reboot' => ['test command', 'test description', 'reboot'],
            'binary' => ['test command 2', '|01100100|01100001|01101110|00001101', 'binary'],
            'sleep' => ['test command 3', 'test description', 'sleep'],
        ];
    }


    /**
     * testAddFailure - test adding various commands that should be invalid
     * @dataProvider provideFailureCases
     */
    public function testAddFailure($commandName, $commandDescription, $commandCode)
    {
        # Add new commands with arguments from provideFailureCases
        $response = $this->sendRequest('POST','/command', [
            'command' => $commandName,
            'description' => $commandDescription,
            'code' => $commandCode
        ]);
        # Check if commands are failing as expected
        $this->assertSame(422, $response->getStatusCode(), 'Expecting failure, received ' . $response->getStatusCode());
    }

    /**
     * Each array is a test run
     * Format (command name, description, code)
     * @return array
     */

    public function provideFailureCases()
    {
        # Cases we provide to testAddFailure, you can extend it by simply adding new case here
        return [
            'No code' => ['No code', 'aa', NULL],
            'Code with space' => ['Code with space', 'Code with space', 'Code with space'],
            'Code with symbol' => ['Code with symbol', 'Code with symbol', 'Codewithsymbol$$'],
            'No description' => ['no description', NULL, 'code'],
            'No Name' => [NULL, 'Bienvenue à la suite de tests Xibo', 'beep'],
            'Only Name' => ['Deutsch Prüfung 1', NULL, NULL],
            'Empty' => [NULL, NULL, NULL] 
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

        $response = $this->sendRequest('GET','/command');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        # There should be as many commands as we created plus the number we started with in the system
        $this->assertEquals(count($commands) + count($this->startCommands), $object->data->recordsTotal);
        # Clean up the groups we created
        foreach ($commands as $com) {
            $com->delete();
        }
    }

    /**
     * Edit an existing command
     */
    public function testEdit()
    {
        # Load in a known command
        /** @var XiboCommand $command */
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit description', 'phpunitcode');
        # Generate new name and description
        $name = Random::generateString(8, 'command');
        $description = Random::generateString(8, 'description');
        # Change name and description of earlier created command
        $response = $this->sendRequest('PUT','/command/' . $command->commandId, [
            'command' => $name,
            'description' => $description,
            'code' => $command->code
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if call was successful
        $this->assertSame(200, $response->getStatusCode(), 'Not successful: ' . $response->getBody());
        $object = json_decode($response->getBody());
        # Examine the returned object and check that it's what we expect
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->command);
        $this->assertSame($description, $object->data->description);
        # Check that the command name and description were actually renamed
        $command = (new XiboCommand($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $command->command);
        $this->assertSame($description, $command->description);
        # Clean up the Layout as we no longer need it
        $command->delete();
    }

     /**
     * Test delete
     * @group minimal
     */
    public function testDelete()
    {
        # Generate random names
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known commands
        $command1 = (new XiboCommand($this->getEntityProvider()))->create($name1, 'phpunit description', 'code');
        $command2 = (new XiboCommand($this->getEntityProvider()))->create($name2, 'phpunit description', 'codetwo');
        # Delete the one we created last
        $response = $this->sendRequest('DELETE','/command/' . $command2->commandId);
        # This should return 204 for success
        $object = json_decode($response->getBody());
        $this->assertSame(204, $object->status, $response->getBody());
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
        # Clean up the first command as we no longer need it
        $command1->delete();
    }
}
