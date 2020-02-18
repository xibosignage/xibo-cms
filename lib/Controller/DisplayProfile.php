<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
namespace Xibo\Controller;
use Stash\Interfaces\PoolInterface;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DisplayProfileFactory $displayProfileFactory
     * @param CommandFactory $commandFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param DayPartFactory $dayPartFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $pool, $displayProfileFactory, $commandFactory, $playerVersionFactory, $dayPartFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->pool = $pool;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->commandFactory = $commandFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->dayPartFactory = $dayPartFactory;
    }

    /**
     * Include display page template page based on sub page selected
     */
    function displayPage()
    {
        $this->getState()->template = 'displayprofile-page';
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
     * @throws \Xibo\Exception\NotFoundException
     */
    function grid()
    {
        $filter = [
            'displayProfileId' => $this->getSanitizer()->getInt('displayProfileId'),
            'displayProfile' => $this->getSanitizer()->getString('displayProfile'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'type' => $this->getSanitizer()->getString('type')
        ];

        $embed = ($this->getSanitizer()->getString('embed') != null) ? explode(',', $this->getSanitizer()->getString('embed')) : [];
        $profiles = $this->displayProfileFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        if (count($profiles) <= 0)
            throw new NotFoundException('Display Profile not found', 'DisplayProfile');

        foreach ($profiles as $profile) {
            /* @var \Xibo\Entity\DisplayProfile $profile */

            // Load the config
            $profile->load([
                'loadConfig' => in_array('config', $embed),
                'loadCommands' => in_array('commands', $embed),
            ]);

            if (in_array('configWithDefault', $embed)) {
                $profile->includeProperty('configDefault');
            }

            if (!in_array('config', $embed)) {
                $profile->excludeProperty('config');
            }

            if ($this->isApi()) {
                continue;
            }

            $profile->includeProperty('buttons');

            // Default Layout
            $profile->buttons[] = array(
                'id' => 'displayprofile_button_edit',
                'url' => $this->urlFor('displayProfile.edit.form', ['id' => $profile->displayProfileId]),
                'text' => __('Edit')
            );

            $profile->buttons[] = array(
                'id' => 'displayprofile_button_copy',
                'url' => $this->urlFor('displayProfile.copy.form', ['id' => $profile->displayProfileId]),
                'text' => __('Copy')
            );

            if ($this->getUser()->checkDeleteable($profile)) {
                $profile->buttons[] = array(
                    'id' => 'displayprofile_button_delete',
                    'url' => $this->urlFor('displayProfile.delete.form', ['id' => $profile->displayProfileId]),
                    'text' => __('Delete')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->displayProfileFactory->countLast();
        $this->getState()->setData($profiles);
    }

    /**
     * Display Profile Add Form
     */
    function addForm()
    {
        $this->getState()->template = 'displayprofile-form-add';
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
     */
    public function add()
    {
        $displayProfile = $this->displayProfileFactory->createEmpty();
        $displayProfile->name = $this->getSanitizer()->getString('name');
        $displayProfile->type = $this->getSanitizer()->getString('type');
        $displayProfile->isDefault = $this->getSanitizer()->getCheckbox('isDefault');
        $displayProfile->userId = $this->getUser()->userId;

        // We do not set any config at this point, so that unless the user chooses to edit the display profile
        // our defaults in the Display Profile Entity take effect
        $displayProfile->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $displayProfile->name),
            'id' => $displayProfile->displayProfileId,
            'data' => $displayProfile
        ]);
    }

    /**
     * Edit Profile Form
     * @param int $displayProfileId
     * @throws \Xibo\Exception\XiboException
     */
    public function editForm($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($displayProfileId);

        // Check permissions
        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId) {
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));
        }

        // Player Version Setting
        $versionId = $displayProfile->getSetting('versionMediaId');
        $playerVersions = [];

        // Daypart - Operating Hours
        $dayPartId = $displayProfile->getSetting('dayPartId');
        $dayparts = [];

        // Get the Player Version for this display profile type
        if ($versionId !== null) {
            try {
                $playerVersions[] = $this->playerVersionFactory->getByMediaId($versionId);
            } catch (NotFoundException $e) {
                $this->getLog()->debug('Unknown versionId set on Display Profile. ' . $displayProfile->displayProfileId);
            }
        }

        if ($dayPartId !== null) {
            try {
                $dayparts[] = $this->dayPartFactory->getById($dayPartId);
            } catch (NotFoundException $e) {
                $this->getLog()->debug('Unknown dayPartId set on Display Profile. ' . $displayProfile->displayProfileId);
            }
        }

        // Get a list of unassigned Commands
        $unassignedCommands = array_udiff($this->commandFactory->query(), $displayProfile->commands, function($a, $b) {
            /** @var \Xibo\Entity\Command $a */
            /** @var \Xibo\Entity\Command $b */
            return $a->getId() - $b->getId();
        });

        $this->getState()->template = 'displayprofile-form-edit';
        $this->getState()->setData([
            'displayProfile' => $displayProfile,
            'commands' => array_merge($displayProfile->commands, $unassignedCommands),
            'versions' => $playerVersions,
            'lockOptions' => json_decode($displayProfile->getSetting('lockOptions', '[]'), true),
            'dayParts' => $dayparts
        ]);
    }

    /**
     * Edit
     * @param $displayProfileId
     * @throws \Xibo\Exception\XiboException
     * 
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
    public function edit($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($displayProfileId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));

        $displayProfile->name = $this->getSanitizer()->getString('name');
        $displayProfile->isDefault = $this->getSanitizer()->getCheckbox('isDefault');

        // Different fields for each client type
        $this->editConfigFields($displayProfile);

        // Capture and update commands
        foreach ($this->commandFactory->query() as $command) {
            /* @var \Xibo\Entity\Command $command */
            if ($this->getSanitizer()->getString('commandString_' . $command->commandId) != null) {
                // Set and assign the command
                $command->commandString = $this->getSanitizer()->getString('commandString_' . $command->commandId);
                $command->validationString = $this->getSanitizer()->getString('validationString_' . $command->commandId, null);
                $displayProfile->assignCommand($command);
            } else {
                $displayProfile->unassignCommand($command);
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
    }

    /**
     * Delete Form
     * @param int $displayProfileId
     * @throws \Xibo\Exception\NotFoundException
     */
    function deleteForm($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($displayProfileId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));

        $this->getState()->template = 'displayprofile-form-delete';
        $this->getState()->setData([
            'displayProfile' => $displayProfile,
            'help' => $this->getHelp()->link('DisplayProfile', 'Delete')
        ]);
    }

    /**
     * Delete Display Profile
     * @param int $displayProfileId
     * @throws \Xibo\Exception\XiboException
     *
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
    function delete($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($displayProfileId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId) {
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));
        }

        $displayProfile->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $displayProfile->name)
        ]);
    }

    /**
     * @param $displayProfileId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function copyForm($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($displayProfileId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));

        $this->getState()->template = 'displayprofile-form-copy';
        $this->getState()->setData([
            'displayProfile' => $displayProfile
        ]);
    }

    /**
     * Copy Display Profile
     * @param int $displayProfileId
     * @throws \Xibo\Exception\XiboException
     *
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
    public function copy($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($displayProfileId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));

        $new = clone $displayProfile;
        $new->name = $this->getSanitizer()->getString('name');
        $new->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $new->name),
            'id' => $new->displayProfileId,
            'data' => $new
        ]);
    }
}