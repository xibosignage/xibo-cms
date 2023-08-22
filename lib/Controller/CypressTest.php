<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
namespace Xibo\Controller;

use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Display;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Helper\Session;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class CypressTest
 * @package Xibo\Controller
 */
class CypressTest extends Base
{
    /** @var  StorageServiceInterface */
    private $store;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /** @var FolderFactory */
    private $folderFactory;
    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  DayPartFactory */
    private $dayPartFactory;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param Session $session
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param CampaignFactory $campaignFactory
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param DayPartFactory $dayPartFactory
     */

    public function __construct(
        $store,
        $session,
        $scheduleFactory,
        $displayGroupFactory,
        $campaignFactory,
        $displayFactory,
        $layoutFactory,
        $dayPartFactory,
        $folderFactory,
        $commandFactory
    ) {
        $this->store = $store;
        $this->session = $session;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->campaignFactory = $campaignFactory;
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->dayPartFactory = $dayPartFactory;
        $this->folderFactory = $folderFactory;
        $this->commandFactory = $commandFactory;
    }

    // <editor-fold desc="Displays">

    /**
     * @throws InvalidArgumentException
     * @throws ControllerNotImplemented
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function scheduleCampaign(Request $request, Response $response): Response|ResponseInterface
    {
        $this->getLog()->debug('Add Schedule');
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $schedule = $this->scheduleFactory->createEmpty();
        $schedule->userId = $this->getUser()->userId;
        $schedule->eventTypeId = 5;
        $schedule->campaignId = $sanitizedParams->getInt('campaignId');
        $schedule->commandId = $sanitizedParams->getInt('commandId');
        $schedule->displayOrder = $sanitizedParams->getInt('displayOrder', ['default' => 0]);
        $schedule->isPriority = $sanitizedParams->getInt('isPriority', ['default' => 0]);
        $schedule->isGeoAware = $sanitizedParams->getCheckbox('isGeoAware');
        $schedule->actionType = $sanitizedParams->getString('actionType');
        $schedule->actionTriggerCode = $sanitizedParams->getString('actionTriggerCode');
        $schedule->actionLayoutCode = $sanitizedParams->getString('actionLayoutCode');
        $schedule->maxPlaysPerHour = $sanitizedParams->getInt('maxPlaysPerHour', ['default' => 0]);
        $schedule->syncGroupId = $sanitizedParams->getInt('syncGroupId');

        // Set the parentCampaignId for campaign events
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$CAMPAIGN_EVENT) {
            $schedule->parentCampaignId = $schedule->campaignId;

            // Make sure we're not directly scheduling an ad campaign
            $campaign = $this->campaignFactory->getById($schedule->campaignId);
            if ($campaign->type === 'ad') {
                throw new InvalidArgumentException(
                    __('Direct scheduling of an Ad Campaign is not allowed'),
                    'campaignId'
                );
            }
        }

        // Fields only collected for interrupt events
        if ($schedule->eventTypeId === \Xibo\Entity\Schedule::$INTERRUPT_EVENT) {
            $schedule->shareOfVoice = $sanitizedParams->getInt('shareOfVoice', [
                'throw' => function () {
                    new InvalidArgumentException(
                        __('Share of Voice must be a whole number between 0 and 3600'),
                        'shareOfVoice'
                    );
                }
            ]);
        } else {
            $schedule->shareOfVoice = null;
        }

        $schedule->dayPartId = 2;
        $schedule->syncTimezone = 0;

        $displays = $this->displayFactory->query(null, ['display' => $sanitizedParams->getString('displayName')]);
        $display = $displays[0];
        $schedule->assignDisplayGroup($this->displayGroupFactory->getById($display->displayGroupId));

        // Ready to do the add
        $schedule->setDisplayNotifyService($this->displayFactory->getDisplayNotifyService());
        if ($schedule->campaignId != null) {
            $schedule->setCampaignFactory($this->campaignFactory);
        }
        $schedule->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added Event'),
            'id' => $schedule->eventId,
            'data' => $schedule
        ]);

        return $this->render($request, $response);
    }

    /**
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function displaySetStatus(Request $request, Response $response): Response|ResponseInterface
    {
        $this->getLog()->debug('Set display status');
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $displays = $this->displayFactory->query(null, ['display' => $sanitizedParams->getString('displayName')]);
        $display = $displays[0];

        // Get the display
        $status = $sanitizedParams->getInt('statusId');

        // Set display status
        $display->mediaInventoryStatus = $status;

        $this->store->update('UPDATE `display` SET MediaInventoryStatus = :status, auditingUntil = :auditingUntil 
             WHERE displayId = :displayId', [
            'displayId' => $display->displayId,
            'auditingUntil' => Carbon::now()->addSeconds(86400)->format('U'),
            'status' => Display::$STATUS_DONE
        ]);
        $this->store->commitIfNecessary();
        $this->store->close();

        return $this->render($request, $response);
    }

    /**
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function displayStatusEquals(Request $request, Response $response): Response|ResponseInterface
    {
        $this->getLog()->debug('Check display status');
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Get the display
        $displays = $this->displayFactory->query(null, ['display' => $sanitizedParams->getString('displayName')]);
        $display = $displays[0];
        $status = $sanitizedParams->getInt('statusId');

        // Check display status
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'data' => $display->mediaInventoryStatus === $status
        ]);

        return $this->render($request, $response);
    }

    // </editor-fold>

    public function createCommand(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $command = $this->commandFactory->create();
        $command->command = $sanitizedParams->getString('command');
        $command->description = $sanitizedParams->getString('description');
        $command->code = $sanitizedParams->getString('code');
        $command->userId = $this->getUser()->userId;
        $command->commandString = $sanitizedParams->getString('commandString');
        $command->validationString = $sanitizedParams->getString('validationString');
        $availableOn = $sanitizedParams->getArray('availableOn');
        if (empty($availableOn)) {
            $command->availableOn = null;
        } else {
            $command->availableOn = implode(',', $availableOn);
        }
        $command->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $command->command),
            'id' => $command->commandId,
            'data' => $command
        ]);

        return $this->render($request, $response);
    }

    /**
     * @throws InvalidArgumentException
     * @throws ControllerNotImplemented
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function createCampaign(Request $request, Response $response): Response|ResponseInterface
    {
        $this->getLog()->debug('Creating campaign');
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $folder = $this->folderFactory->getById($this->getUser()->homeFolderId, 0);

        // Create Campaign
        $campaign = $this->campaignFactory->create(
            'list',
            $sanitizedParams->getString('name'),
            $this->getUser()->userId,
            $folder->getId()
        );

        // Cycle based playback
        if ($campaign->type === 'list') {
            $campaign->cyclePlaybackEnabled = $sanitizedParams->getCheckbox('cyclePlaybackEnabled');
            $campaign->playCount = ($campaign->cyclePlaybackEnabled) ? $sanitizedParams->getInt('playCount') : null;

            // For compatibility with existing API implementations we set a default here.
            $campaign->listPlayOrder = ($campaign->cyclePlaybackEnabled)
                ? 'block'
                : $sanitizedParams->getString('listPlayOrder', ['default' => 'round']);
        } else if ($campaign->type === 'ad') {
            $campaign->targetType = $sanitizedParams->getString('targetType');
            $campaign->target = $sanitizedParams->getInt('target');
            $campaign->listPlayOrder = 'round';
        }

        // All done, save.
        $campaign->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added campaign'),
            'id' => $campaign->campaignId,
            'data' => $campaign
        ]);

        return $this->render($request, $response);
    }

    // <editor-fold desc="Schedule">

    // </editor-fold>
}
