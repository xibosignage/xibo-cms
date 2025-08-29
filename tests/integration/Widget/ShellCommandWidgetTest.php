<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboCommand;
use Xibo\OAuth2\Client\Entity\XiboShellCommand;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class ShellCommandWidgetTest
 * @package Xibo\Tests\Integration\Widget
 */
class ShellCommandWidgetTest extends LocalWebTestCase
{
    use LayoutHelperTrait;

    /** @var \Xibo\OAuth2\Client\Entity\XiboLayout */
    protected $publishedLayout;

    /** @var XiboCommand */
    protected $command;

    /** @var int */
    protected $widgetId;

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup for ' . get_class($this) .' Test');

        // Create a Layout
        $this->publishedLayout = $this->createLayout();

        // Checkout
        $layout = $this->getDraft($this->publishedLayout);

        // Create a Widget for us to edit.
        $response = $this->getEntityProvider()->post('/playlist/widget/shellcommand/' . $layout->regions[0]->regionPlaylist->playlistId);

        // Create a command
        $this->command = (new XiboCommand($this->getEntityProvider()))->create(Random::generateString(), 'phpunit description', 'phpunitcode');

        $this->widgetId = $response['widgetId'];
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Delete the Layout we've been working with
        $this->deleteLayout($this->publishedLayout);

        // Delete the commant
        $this->command->delete();

        parent::tearDown();

        $this->getLogger()->debug('Tear down for ' . get_class($this) .' Test');
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

    /**
     * @throws \Xibo\OAuth2\Client\Exception\XiboApiException
     * @dataProvider provideSuccessCases
     */
    public function testEdit($name, $duration, $useDuration, $windowsCommand, $linuxCommand, $launchThroughCmd, $terminateCommand, $useTaskkill, $commandCode)
    {
        $response = $this->sendRequest('PUT','/playlist/widget/' . $this->widgetId, [
            'name' => $name,
            'duration' => $duration,
            'useDuration' => $useDuration,
            'windowsCommand' => $windowsCommand,
            'linuxCommand' => $linuxCommand,
            'launchThroughCmd' => $launchThroughCmd,
            'terminateCommand' => $terminateCommand,
            'useTaskkill' => $useTaskkill,
            'commandCode' => $commandCode,
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());

        /** @var XiboShellCommand $checkWidget */
        $response = $this->getEntityProvider()->get('/playlist/widget', ['widgetId' => $this->widgetId]);
        $checkWidget = (new XiboShellCommand($this->getEntityProvider()))->hydrate($response[0]);

        $this->assertSame($name, $checkWidget->name);
        $this->assertSame($duration, $checkWidget->duration);

        foreach ($checkWidget->widgetOptions as $option) {
            if ($option['option'] == 'commandCode') {
                $this->assertSame($commandCode, $option['value']);
            }
        }
    }
}
