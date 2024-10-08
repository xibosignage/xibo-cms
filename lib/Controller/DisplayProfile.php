<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DisplayProfile
 * @package Xibo\Controller
 */
class DisplayProfile extends Base
{
    use DisplayProfileConfigFields;

    /** @var  PoolInterface */
    private $pool;

    /**
     * @var DayPartFactory
     */
    private $dayPartFactory;

    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /** @var PlayerVersionFactory */
    private $playerVersionFactory;

    /**
     * Set common dependencies.
     * @param PoolInterface $pool
     * @param DisplayProfileFactory $displayProfileFactory
     * @param CommandFactory $commandFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param DayPartFactory $dayPartFactory
     */
    public function __construct($pool, $displayProfileFactory, $commandFactory, $playerVersionFactory, $dayPartFactory)
    {
        $this->pool = $pool;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->commandFactory = $commandFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->dayPartFactory = $dayPartFactory;
    }

    /**
     * Include display page template page based on sub page selected
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'displayprofile-page';
        $this->getState()->setData([
            'types' => $this->displayProfileFactory->getAvailableTypes()
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/displayprofile",
     *  operationId="displayProfileSearch",
     *  tags={"displayprofile"},
     *  summary="Display Profile Search",
     *  description="Search this users Display Profiles",
     *  @SWG\Parameter(
     *      name="displayProfileId",
     *      in="query",
     *      description="Filter by DisplayProfile Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayProfile",
     *      in="query",
     *      description="Filter by DisplayProfile Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="query",
     *      description="Filter by DisplayProfile Type (windows|android|lg)",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
     *      description="Embed related data such as config,commands,configWithDefault",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/DisplayProfile")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function grid(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'displayProfileId' => $parsedQueryParams->getInt('displayProfileId'),
            'displayProfile' => $parsedQueryParams->getString('displayProfile'),
            'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
            'type' => $parsedQueryParams->getString('type'),
            'logicalOperatorName' => $parsedQueryParams->getString('logicalOperatorName'),
        ];

        $embed = ($parsedQueryParams->getString('embed') != null)
            ? explode(',', $parsedQueryParams->getString('embed'))
            : [];

        $profiles = $this->displayProfileFactory->query(
            $this->gridRenderSort($parsedQueryParams),
            $this->gridRenderFilter($filter, $parsedQueryParams)
        );

        foreach ($profiles as $profile) {
            // Load the config
            $profile->load([
                'loadConfig' => in_array('config', $embed),
                'loadCommands' => in_array('commands', $embed)
            ]);

            if (in_array('configWithDefault', $embed)) {
                $profile->includeProperty('configDefault');
            }

            if (!in_array('config', $embed)) {
                $profile->excludeProperty('config');
            }

            if ($this->isApi($request)) {
                continue;
            }

            $profile->includeProperty('buttons');

            if ($this->getUser()->featureEnabled('displayprofile.modify')) {
                // Default Layout
                $profile->buttons[] = array(
                    'id' => 'displayprofile_button_edit',
                    'url' => $this->urlFor(
                        $request,
                        'displayProfile.edit.form',
                        ['id' => $profile->displayProfileId]
                    ),
                    'text' => __('Edit')
                );

                $profile->buttons[] = array(
                    'id' => 'displayprofile_button_copy',
                    'url' => $this->urlFor(
                        $request,
                        'displayProfile.copy.form',
                        ['id' => $profile->displayProfileId]
                    ),
                    'text' => __('Copy')
                );

                if ($this->getUser()->checkDeleteable($profile)) {
                    $profile->buttons[] = array(
                        'id' => 'displayprofile_button_delete',
                        'url' => $this->urlFor(
                            $request,
                            'displayProfile.delete.form',
                            ['id' => $profile->displayProfileId]
                        ),
                        'text' => __('Delete')
                    );
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->displayProfileFactory->countLast();
        $this->getState()->setData($profiles);

        return $this->render($request, $response);
    }

    /**
     * Display Profile Add Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'displayprofile-form-add';
        $this->getState()->setData([
            'types' => $this->displayProfileFactory->getAvailableTypes()
        ]);

        return $this->render($request, $response);
    }

    /**
     * Display Profile Add
     *
     * @SWG\Post(
     *  path="/displayprofile",
     *  operationId="displayProfileAdd",
     *  tags={"displayprofile"},
     *  summary="Add Display Profile",
     *  description="Add a Display Profile",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Name of the Display Profile",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="formData",
     *      description="The Client Type this Profile will apply to",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="isDefault",
     *      in="formData",
     *      description="Flag indicating if this is the default profile for the client type",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DisplayProfile"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $displayProfile = $this->displayProfileFactory->createEmpty();
        $displayProfile->name = $sanitizedParams->getString('name');
        $displayProfile->type = $sanitizedParams->getString('type');
        $displayProfile->isDefault = $sanitizedParams->getCheckbox('isDefault');
        $displayProfile->userId = $this->getUser()->userId;
        $displayProfile->isCustom = $this->displayProfileFactory->isCustomType($displayProfile->type);

        // We do not set any config at this point, so that unless the user chooses to edit the display profile
        // our defaults in the Display Profile Factory take effect
        $displayProfile->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $displayProfile->name),
            'id' => $displayProfile->displayProfileId,
            'data' => $displayProfile
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Profile Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function editForm(Request $request, Response $response, $id)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($id);

        // Check permissions
        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId) {
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));
        }

        // Player Version Setting
        $versionId = $displayProfile->type === 'chromeOS'
            ? $displayProfile->getSetting('playerVersionId')
            : $displayProfile->getSetting('versionMediaId');

        $playerVersions = [];

        // Daypart - Operating Hours
        $dayPartId = $displayProfile->getSetting('dayPartId');
        $dayparts = [];

        // Get the Player Version for this display profile type
        if ($versionId !== null) {
            try {
                $playerVersions[] = $this->playerVersionFactory->getById($versionId);
            } catch (NotFoundException) {
                $this->getLog()->debug('Unknown versionId set on Display Profile. '
                    . $displayProfile->displayProfileId);
            }
        }

        if ($dayPartId !== null) {
            try {
                $dayparts[] = $this->dayPartFactory->getById($dayPartId);
            } catch (NotFoundException $e) {
                $this->getLog()->debug('Unknown dayPartId set on Display Profile. ' . $displayProfile->displayProfileId);
            }
        }

        // elevated logs
        $elevateLogsUntil = $displayProfile->getSetting('elevateLogsUntil');
        $elevateLogsUntilIso = !empty($elevateLogsUntil)
            ? Carbon::createFromTimestamp($elevateLogsUntil)->format(DateFormatHelper::getSystemFormat())
            : null;
        $displayProfile->setUnmatchedProperty('elevateLogsUntilIso', $elevateLogsUntilIso);

        $this->getState()->template = 'displayprofile-form-edit';
        $this->getState()->setData([
            'displayProfile' => $displayProfile,
            'commands' => $displayProfile->commands,
            'versions' => $playerVersions,
            'lockOptions' => json_decode($displayProfile->getSetting('lockOptions', '[]'), true),
            'dayParts' => $dayparts
        ]);


        return $this->render($request, $response);
    }

    /**
     * Edit
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @SWG\Put(
     *  path="/displayprofile/{displayProfileId}",
     *  operationId="displayProfileEdit",
     *  tags={"displayprofile"},
     *  summary="Edit Display Profile",
     *  description="Edit a Display Profile",
     *  @SWG\Parameter(
     *      name="displayProfileId",
     *      in="path",
     *      description="The Display Profile ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Name of the Display Profile",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="formData",
     *      description="The Client Type this Profile will apply to",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="isDefault",
     *      in="formData",
     *      description="Flag indicating if this is the default profile for the client type",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function edit(Request $request, Response $response, $id)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($id);

        $parsedParams = $this->getSanitizer($request->getParams());

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId) {
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));
        }

        $displayProfile->name = $parsedParams->getString('name');
        $displayProfile->isDefault = $parsedParams->getCheckbox('isDefault');

        // Track changes to versionMediaId
        $originalPlayerVersionId = $displayProfile->getSetting('playerVersionId');

        // Different fields for each client type
        $this->editConfigFields($displayProfile, $parsedParams);

        // Capture and update commands
        foreach ($this->commandFactory->query() as $command) {
            if ($parsedParams->getString('commandString_' . $command->commandId) != null) {
                // Set and assign the command
                $command->commandString = $parsedParams->getString('commandString_' . $command->commandId);
                $command->validationString = $parsedParams->getString('validationString_' . $command->commandId);
                $command->createAlertOn = $parsedParams->getString('createAlertOn_' . $command->commandId);

                $displayProfile->assignCommand($command);
            } else {
                $displayProfile->unassignCommand($command);
            }
        }

        // If we are chromeOS and the default profile, has the player version changed?
        if ($displayProfile->type === 'chromeOS'
            && ($displayProfile->isDefault || $displayProfile->hasPropertyChanged('isDefault'))
            && ($originalPlayerVersionId !== $displayProfile->getSetting('playerVersionId'))
        ) {
            $this->getLog()->debug('edit: updating symlink to the latest chromeOS version');

            // Update a symlink to the new player version.
            try {
                $version = $this->playerVersionFactory->getById($displayProfile->getSetting('playerVersionId'));
                $version->setActive();
            } catch (NotFoundException) {
                $this->getLog()->error('edit: Player version does not exist');
            }
        }

        // Save the changes
        $displayProfile->save();

        // Clear the display cached
        $this->pool->deleteItem('display/');

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $displayProfile->name),
            'id' => $displayProfile->displayProfileId,
            'data' => $displayProfile
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function deleteForm(Request $request, Response $response, $id)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($id);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));

        $this->getState()->template = 'displayprofile-form-delete';
        $this->getState()->setData([
            'displayProfile' => $displayProfile,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Display Profile
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @SWG\Delete(
     *  path="/displayprofile/{displayProfileId}",
     *  operationId="displayProfileDelete",
     *  tags={"displayprofile"},
     *  summary="Delete Display Profile",
     *  description="Delete an existing Display Profile",
     *  @SWG\Parameter(
     *      name="displayProfileId",
     *      in="path",
     *      description="The Display Profile ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    function delete(Request $request, Response $response, $id)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($id);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId) {
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));
        }

        $displayProfile->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $displayProfile->name)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function copyForm(Request $request, Response $response, $id)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($id);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));

        $this->getState()->template = 'displayprofile-form-copy';
        $this->getState()->setData([
            'displayProfile' => $displayProfile
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copy Display Profile
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @SWG\Post(
     *  path="/displayprofile/{displayProfileId}/copy",
     *  operationId="displayProfileCopy",
     *  tags={"displayprofile"},
     *  summary="Copy Display Profile",
     *  description="Copy an existing Display Profile",
     *  @SWG\Parameter(
     *      name="displayProfileId",
     *      in="path",
     *      description="The Display Profile ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="path",
     *      description="The name for the copy",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DisplayProfile"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function copy(Request $request, Response $response, $id)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($id);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId) {
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));
        }

        // clear DisplayProfileId, commands and set isDefault to 0
        $new = clone $displayProfile;
        $new->name = $this->getSanitizer($request->getParams())->getString('name');

        foreach ($displayProfile->commands as $command) {
            /* @var \Xibo\Entity\Command $command */
            if (!empty($command->commandStringDisplayProfile)) {
                // if the original Display Profile has a commandString
                // assign this command with the same commandString to new Display Profile
                // commands with only default commandString are not directly assigned to Display profile
                $command->commandString = $command->commandStringDisplayProfile;
                $command->validationString = $command->validationStringDisplayProfile;
                $new->assignCommand($command);
            }
        }

        $new->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $new->name),
            'id' => $new->displayProfileId,
            'data' => $new
        ]);

        return $this->render($request, $response);
    }
}
