<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015-2018 Spring Signage Ltd
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

namespace Xibo\Tests\Integration\Widget;

use Xibo\OAuth2\Client\Entity\XiboCommand;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboShellCommand;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class ShellCommandWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

	protected $startLayouts;
    protected $startCommands;
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        $this->startCommands = (new XiboCommand($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all layouts that weren't there initially
        $finalLayouts = (new XiboLayout($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining layouts and nuke them
        foreach ($finalLayouts as $layout) {
            /** @var XiboLayout $layout */
            $flag = true;
            foreach ($this->startLayouts as $startLayout) {
               if ($startLayout->layoutId == $layout->layoutId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $layout->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $layout->layoutId . '. E:' . $e->getMessage());
                }
            }
        }
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
     * @group add
     * @dataProvider provideSuccessCases
     */
    public function testAdd($name, $duration, $useDuration, $windowsCommand, $linuxCommand, $launchThroughCmd, $terminateCommand, $useTaskkill, $commandCode)
    {
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit description', 'phpunitcode');

        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        $response = $this->client->post('/playlist/widget/shellCommand/' . $playlistId, [
            'name' => $name,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'windowsCommand' => $windowsCommand,
            'linuxCommand' => $linuxCommand,
            'launchThroughCmd' => $launchThroughCmd,
            'terminateCommand' => $terminateCommand,
            'useTaskkill' => $useTaskkill,
            'commandCode' => $commandCode,
            ]);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboShellCommand($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($name, $widgetOptions->name);
        $this->assertSame($duration, $widgetOptions->duration);        
    }

    /**
     * Each array is a test run
     * Format ($name, $duration, $windowsCommand, $linuxCommand, $launchThroughCmd, $terminateCommand, $useTaskkill, $commandCode)
     * @return array
     */
    public function provideSuccessCases()
    {
        # Sets of data used in testAdd
        return [
            'Windows new command' => ['Api Windows command', 20, 1,'reboot', NULL, 1, null, 1, null],
            'Android new command' => ['Api Android command', 30, 1, null, 'reboot', null, 1, null, null],
            'Previously created command' => ['Api shell command', 50, 1, null, null, 1, 1, 1, 'phpunit code']
        ];
    }

     public function testEdit()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create a command with wrapper
        $command = (new XiboCommand($this->getEntityProvider()))->create('phpunit command', 'phpunit description', 'phpunitcode');
        # Create a shell command widget with wrapper
        $shellCommand = (new XiboShellCommand($this->getEntityProvider()))->create('Api shell command', 0, 1, null, null, 1, 1, 1, 'test code', $playlistId);
        $nameNew = 'Edited Name';
        $durationNew = 80;
        $commandCode = $command->code;
        $response = $this->client->put('/playlist/widget/' . $shellCommand->widgetId, [
            'name' => $nameNew,
            'duration' => $durationNew,
            'useDuration' => 1,
            'windowsCommand' => null,
            'linuxCommand' => null,
            'launchThroughCmd' => 1,
            'terminateCommand' => 1,
            'useTaskkill' => 1,
            'commandCode' => $commandCode,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $widgetOptions = (new XiboShellCommand($this->getEntityProvider()))->getById($playlistId);
        $this->assertSame($nameNew, $widgetOptions->name);
        $this->assertSame($durationNew, $widgetOptions->duration);    
        foreach ($widgetOptions->widgetOptions as $option) {
            if ($option['option'] == 'commandCode') {
                $this->assertSame($commandCode, $option['value']);
            }
        }
    }

    public function testDelete()
    {
        // Create layout
        $layout = $this->createLayout();
        $layout = $this->checkout($layout);
        $playlistId = $layout->regions[0]->regionPlaylist['playlistId'];

        # Create a shell command widget with wrapper
        $shellCommand = (new XiboShellCommand($this->getEntityProvider()))->create('Api shell command', 0, 1, null, null, 1, 1, 1, 'phpunit code', $playlistId);
        # Delete it
        $this->client->delete('/playlist/widget/' . $shellCommand->widgetId);
        $response = json_decode($this->client->response->body());
        $this->assertSame(200, $response->status, $this->client->response->body());
    }
}
