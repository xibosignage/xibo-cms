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
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\CommandFactory;
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
    /** @var  PoolInterface */
    private $pool;

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
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $pool, $displayProfileFactory, $commandFactory, $playerVersionFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->pool = $pool;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->commandFactory = $commandFactory;
        $this->playerVersionFactory = $playerVersionFactory;
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
     *      in="formData",
     *      description="Filter by DisplayProfile Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayProfile",
     *      in="formData",
     *      description="Filter by DisplayProfile Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="type",
     *      in="formData",
     *      description="Filter by DisplayProfile Type (windows|android|lg)",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="formData",
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
            'type' => $this->getSanitizer()->getString('type')
        ];

        $embed = ($this->getSanitizer()->getString('embed') != null) ? explode(',', $this->getSanitizer()->getString('embed')) : [];
        $profiles = $this->displayProfileFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        if (count($profiles) <= 0)
            throw new NotFoundException('Display Profile not found', 'DisplayProfile');

        foreach ($profiles as $profile) {
            /* @var \Xibo\Entity\DisplayProfile $profile */

            // Load the config
            if ((in_array('config', $embed) || in_array('commands', $embed)) && !in_array('configWithDefault', $embed)) {
                $profile->load([
                    'loadConfig' => in_array('config', $embed),
                    'loadCommands' => in_array('commands', $embed),
                ]);
            } elseif (in_array('configWithDefault', $embed)) {
                $profile->load([
                    'loadConfig' => true,
                    'loadConfigWithDefault' => true,
                    'loadCommands' => in_array('commands', $embed)
                ]);
                $profile->includeProperty('configDefault');
            } else {
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

        $combined = [];

        $displayProfile->save();

        $displayProfile->load();

        foreach ($displayProfile->configDefault as $setting) {
            // Validate the parameter
            $value = null;

            switch ($setting['type']) {
                case 'string':
                    $value = $this->getSanitizer()->getString($setting['name'], $setting['default']);
                    break;

                case 'int':
                    $value = $this->getSanitizer()->getInt($setting['name'], $setting['default']);
                    break;

                case 'double':
                    $value = $this->getSanitizer()->getDouble($setting['name'], $setting['default']);
                    break;

                case 'checkbox':
                    $value = $this->getSanitizer()->getCheckbox($setting['name']);
                    break;

                default:
                    $value = $this->getSanitizer()->getParam($setting['name'], $setting['default']);
            }

            // Add to the combined array
            $combined[] = [
                'name' => $setting['name'],
                'value' => $value,
                'type' => $setting['type']
            ];
        }

        // Recursively merge the arrays and update
        $displayProfile->config = $combined;

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
        $versionId = null;
        $playerVersions = '';

        foreach ($displayProfile->config as $setting) {
            if ($setting['name'] == 'versionMediaId') {
                $versionId = $setting['value'];
            }
        }

        // Decode JSON value and save as value (timers, pictureOptions, lockOptions)
        foreach ($displayProfile->configDefault as &$setting) {
            if (in_array($setting['name'], ['timers', 'pictureOptions', 'lockOptions'])) {
                $settingValues = json_decode((string )$setting['value'], true);
                $setting['data'] = $settingValues;
            }
        }

        // Get the Player Version for this display profile type
        if ($versionId != 0)
            try {
                $playerVersions = $this->playerVersionFactory->getByMediaId($versionId);
            } catch (NotFoundException $e) {
                $playerVersions = null;
            }

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));

        // Get a list of unassigned Commands
        $unassignedCommands = array_udiff($this->commandFactory->query(), $displayProfile->commands, function($a, $b) {
            /** @var \Xibo\Entity\Command $a */
            /** @var \Xibo\Entity\Command $b */
            return $a->getId() - $b->getId();
        });

        $this->getState()->template = 'displayprofile-form-edit';
        $this->getState()->setData([
            'displayProfile' => $displayProfile,
            'tabs' => $displayProfile->configTabs,
            'config' => $displayProfile->configDefault,
            'commands' => array_merge($displayProfile->commands, $unassignedCommands),
            'versions' => [$playerVersions]
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

        // Capture and validate the posted form parameters in accordance with the display config object.
        $combined = array();

        foreach ($displayProfile->configDefault as $setting) {
            // Validate the parameter
            $value = null;

            if ($setting['name'] == 'timers') {
                // Options object to be converted to a JSON string
                $timerOptions = (object)[];

                $timers = $this->getSanitizer()->getStringArray('timers');

                foreach ($timers as $timer) {
                    $timerDay = $timer['day'];

                    if(sizeof($timers) == 1 && $timerDay == '') {
                        break;
                    } elseif($timerDay == '' || property_exists($timerOptions, $timerDay)) {
                        // Repeated or Empty day input, throw exception
                        throw new InvalidArgumentException(__('On/Off Timers: Please check the days selected and remove the duplicates or empty'), 'timers');
                    } else {
                        // Get time values
                        $timerOn = $timer['on'];
                        $timerOff = $timer['off'];

                        // Check the on/off times are in the correct format (H:i)
                        if (strlen($timerOn) != 5 || strlen($timerOff) != 5) {
                            throw new InvalidArgumentException(__('On/Off Timers: Please enter a on and off date for any row with a day selected, or remove that row'), 'timers');
                        } else {
                            //Build object and add it to the main options object
                            $temp = [];
                            $temp['on'] = $timerOn;
                            $temp['off'] = $timerOff;
                            $timerOptions->$timerDay = $temp;
                        }
                    }
                }
                
                // Encode option and save it as a string to the lock setting
                $value = json_encode($timerOptions, JSON_PRETTY_PRINT);
            } elseif ($setting['name'] == 'pictureOptions') {
                // Options object to be converted to a JSON string
                $pictureControlsOptions = (object)[];

                // Special string properties map
                $specialProperties = (object)[];
                $specialProperties->dynamicContrast = ["off", "low", "medium", "high"];
                $specialProperties->superResolution = ["off", "low", "medium", "high"];
                $specialProperties->colorGamut = ["normal", "extended"];
                $specialProperties->dynamicColor = ["off", "low", "medium", "high"];
                $specialProperties->noiseReduction = ["auto", "off", "low", "medium", "high"];
                $specialProperties->mpegNoiseReduction = ["auto", "off", "low", "medium", "high"];
                $specialProperties->blackLevel = ["low", "high"];
                $specialProperties->gamma = ["low", "medium", "high", "high2"];

                // Get array from request
                $pictureControls = $this->getSanitizer()->getStringArray('pictureControls');

                foreach ($pictureControls as $pictureControl) {
                    $propertyName = $pictureControl['property'];

                    if(sizeof($pictureControls) == 1 && $propertyName == '') {
                        break;
                    } elseif($propertyName == '' || property_exists($pictureControlsOptions, $propertyName)) {
                        // Repeated or Empty property input, throw exception
                        throw new InvalidArgumentException(__('Picture: Please check the settings selected and remove the duplicates or empty'), 'pictureOptions');
                    } else {
                        // Get time values
                        $propertyValue = $pictureControl['value'];

                        // Check the on/off times are in the correct format (H:i)
                        if (property_exists($specialProperties, $propertyName)) {
                            $pictureControlsOptions->$propertyName = $specialProperties->$propertyName[$propertyValue];
                        } else {
                            //Build object and add it to the main options object
                            $pictureControlsOptions->$propertyName = (int)$propertyValue;
                        }
                    }
                }

                 // Encode option and save it as a string to the lock setting
                $value = json_encode($pictureControlsOptions, JSON_PRETTY_PRINT);
            } elseif ($setting['name'] == 'lockOptions') {  
                // Get values from lockOptions params
                $usblock = $this->getSanitizer()->getString('usblock', '');
                $osdlock = $this->getSanitizer()->getString('osdlock', '');
                $keylockLocal = $this->getSanitizer()->getString('keylockLocal', '');
                $keylockRemote = $this->getSanitizer()->getString('keylockRemote', '');

                // Options object to be converted to a JSON string
                $lockOptions = (object)[];

                if($usblock != 'empty') {
                    $lockOptions->usblock = $usblock === 'true'? true: false;
                }

                if($osdlock != 'empty') {
                    $lockOptions->osdlock = $osdlock === 'true'? true: false;
                }

                if($keylockLocal != '' || $keylockRemote != '') {
                    // Keylock sub object
                    $lockOptions->keylock = (object)[];

                    if($keylockLocal != '') {
                        $lockOptions->keylock->local = $keylockLocal;
                    }

                    if($keylockRemote != '') {
                        $lockOptions->keylock->remote = $keylockRemote;
                    }
                }

                // Encode option and save it as a string to the lock setting
                $value = json_encode($lockOptions, JSON_PRETTY_PRINT);
            } else {

                switch ($setting['type']) {
                    case 'string':
                        $value = $this->getSanitizer()->getString($setting['name'], $setting['default']);
                        break;

                    case 'int':
                        $value = $this->getSanitizer()->getInt($setting['name'], $setting['default']);
                        break;

                    case 'double':
                        $value = $this->getSanitizer()->getDouble($setting['name'], $setting['default']);
                        break;

                    case 'checkbox':
                        $value = $this->getSanitizer()->getCheckbox($setting['name']);
                        break;

                    default:
                        $value = $this->getSanitizer()->getParam($setting['name'], $setting['default']);
                }
            }

            // Add to the combined array
            $combined[] = array(
                'name' => $setting['name'],
                'value' => $value,
                'type' => $setting['type']
            );
        }

        // Recursively merge the arrays and update
        $displayProfile->config = $combined;

        // Capture and update commands
        foreach ($this->commandFactory->query() as $command) {
            /* @var \Xibo\Entity\Command $command */
            if ($this->getSanitizer()->getString('commandString_' . $command->commandId) != null) {
                // Set and assign the command
                $command->commandString = $this->getSanitizer()->getString('commandString_' . $command->commandId);
                $command->validationString = $this->getSanitizer()->getString('validationString_' . $command->commandId);
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

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));

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