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

use GuzzleHttp\Client;
use Intervention\Image\ImageManagerStatic as Img;
use Jenssegers\Date\Date;
use Respect\Validation\Validator as v;
use RobThree\Auth\TwoFactorAuth;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\RequiredFile;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayEventFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\LogFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\Random;
use Xibo\Helper\WakeOnLan;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\XMR\LicenceCheckAction;
use Xibo\XMR\RekeyAction;
use Xibo\XMR\ScreenShotAction;

/**
 * Class Display
 * @package Xibo\Controller
 */
class Display extends Base
{
    use DisplayProfileConfigFields;

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @var PlayerActionServiceInterface
     */
    private $playerAction;

    /**
     * @var DayPartFactory
     */
    private $dayPartFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var LogFactory
     */
    private $logFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /** @var  DisplayEventFactory */
    private $displayEventFactory;

    /** @var PlayerVersionFactory */
    private $playerVersionFactory;

    /** @var  RequiredFileFactory */
    private $requiredFileFactory;

    /** @var  TagFactory */
    private $tagFactory;

    /** @var NotificationFactory */
    private $notificationFactory;

    /** @var UserGroupFactory */
    private $userGroupFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param PlayerActionServiceInterface $playerAction
     * @param DisplayFactory $displayFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param LogFactory $logFactory
     * @param LayoutFactory $layoutFactory
     * @param DisplayProfileFactory $displayProfileFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayEventFactory $displayEventFactory
     * @param RequiredFileFactory $requiredFileFactory
     * @param TagFactory $tagFactory
     * @param NotificationFactory $notificationFactory
     * @param UserGroupFactory $userGroupFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param DayPartFactory $dayPartFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $pool, $playerAction, $displayFactory, $displayGroupFactory, $logFactory, $layoutFactory, $displayProfileFactory, $mediaFactory, $scheduleFactory, $displayEventFactory, $requiredFileFactory, $tagFactory, $notificationFactory, $userGroupFactory, $playerVersionFactory, $dayPartFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->pool = $pool;
        $this->playerAction = $playerAction;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->logFactory = $logFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayEventFactory = $displayEventFactory;
        $this->requiredFileFactory = $requiredFileFactory;
        $this->tagFactory = $tagFactory;
        $this->notificationFactory = $notificationFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->dayPartFactory = $dayPartFactory;
    }

    /**
     * Include display page template page based on sub page selected
     * @throws NotFoundException
     */
    function displayPage()
    {
        // Build a list of display profiles
        $displayProfiles = $this->displayProfileFactory->query();
        $displayProfiles[] = ['displayProfileId' => -1, 'name' => __('Default')];

        // Call to render the template
        $this->getState()->template = 'display-page';
        $this->getState()->setData([
            'displayGroups' => $this->displayGroupFactory->query(),
            'displayProfiles' => $displayProfiles
        ]);
    }

    /**
     * Display Management Page for an Individual Display
     * @param int $displayId
     * @throws \Xibo\Exception\NotFoundException
     */
    function displayManage($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        // Zero out some variables
        $layouts = [];
        $widgets = [];
        $media = [];
        $totalCount = 0;
        $completeCount = 0;
        $totalSize = 0;
        $completeSize = 0;


        // Show 3 widgets
        $sql = '
          SELECT layoutId, layout, `requiredfile`.*
              FROM `layout`
                INNER JOIN `requiredfile`
                ON `requiredfile`.itemId = `layout`.layoutId
           WHERE `requiredfile`.displayId = :displayId 
            AND `requiredfile`.type = :type
          ORDER BY layout
        ';

        foreach ($this->store->select($sql, ['displayId' => $displayId, 'type' => 'L']) as $row) {
            /** @var RequiredFile $rf */
            $rf = $this->requiredFileFactory->getByDisplayAndLayout($displayId, $row['layoutId']);

            $totalCount++;

            if ($rf->complete) {
                $completeCount = $completeCount + 1;
            }

            $rf = $rf->toArray();
            $rf['layout'] = $row['layout'];
            $layouts[] = $rf;
        }

        // Media
        $sql = '
          SELECT mediaId, `name`, fileSize, media.type AS mediaType, storedAs, `requiredfile`.*
              FROM `media`
                INNER JOIN `requiredfile`
                ON `requiredfile`.itemId = `media`.mediaId
           WHERE `requiredfile`.displayId = :displayId 
            AND `requiredfile`.type = :type
          ORDER BY `name`
        ';

        foreach ($this->store->select($sql, ['displayId' => $displayId, 'type' => 'M']) as $row) {
            /** @var RequiredFile $rf */
            $rf = $this->requiredFileFactory->getByDisplayAndMedia($displayId, $row['mediaId']);

            $totalSize = $totalSize + $row['fileSize'];
            $totalCount++;

            if ($rf->complete) {
                $completeSize = $completeSize + $row['fileSize'];
                $completeCount = $completeCount + 1;
            }

            $rf = $rf->toArray();
            $rf['name'] = $row['name'];
            $rf['type'] = $row['mediaType'];
            $rf['storedAs'] = $row['storedAs'];
            $rf['size'] = $row['fileSize'];
            $media[] = $rf;
        }

        // Widgets
        $sql = '
          SELECT `widget`.type AS widgetType,
                IFNULL(`widgetoption`.value, `widget`.type) AS widgetName,
                `widget`.widgetId,
                 `requiredfile`.*
              FROM `widget`
                INNER JOIN `requiredfile`
                ON `requiredfile`.itemId = `widget`.widgetId
                LEFT OUTER JOIN `widgetoption`
                ON `widgetoption`.widgetId = `widget`.widgetId
                  AND `widgetoption`.option = \'name\'
           WHERE `requiredfile`.displayId = :displayId 
            AND `requiredfile`.type = :type
          ORDER BY IFNULL(`widgetoption`.value, `widget`.type)
        ';

        foreach ($this->store->select($sql, ['displayId' => $displayId, 'type' => 'W']) as $row) {
            /** @var RequiredFile $rf */
            $rf = $this->requiredFileFactory->getByDisplayAndWidget($displayId, $row['widgetId']);

            $totalCount++;

            if ($rf->complete) {
                $completeCount = $completeCount + 1;
            }

            $rf = $rf->toArray();
            $rf['type'] = $row['widgetType'];
            $rf['widgetName'] = $row['widgetName'];
            $widgets[] = $rf;
        }

        // Widget for file status
        // Decide what our units are going to be, based on the size
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');
        $base = (int)floor(log($totalSize) / log(1024));

        if ($base < 0)
            $base = 0;

        $units = (isset($suffixes[$base]) ? $suffixes[$base] : '');
        $this->getLog()->debug('Base for size is %d and suffix is %s', $base, $units);


        // Call to render the template
        $this->getState()->template = 'display-page-manage';
        $this->getState()->setData([
            'requiredFiles' => [],
            'display' => $display,
            'timeAgo' => $this->getDate()->parse($display->lastAccessed, 'U')->diffForHumans(),
            'errorSearch' => http_build_query([
                'displayId' => $display->displayId,
                'type' => 'ERROR',
                'fromDt' => $this->getDate()->getLocalDate($this->getDate()->parse()->subHours(12)),
                'toDt' => $this->getDate()->getLocalDate()
            ]),
            'inventory' => [
                'layouts' => $layouts,
                'media' => $media,
                'widgets' => $widgets
            ],
            'status' => [
                'units' => $units,
                'countComplete' => $completeCount,
                'countRemaining' => $totalCount - $completeCount,
                'sizeComplete' => round((double)$completeSize / (pow(1024, $base)), 2),
                'sizeRemaining' => round((double)($totalSize - $completeSize) / (pow(1024, $base)), 2),
            ],
            'defaults' => [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate()
            ]
        ]);
    }

    /**
     * Grid of Displays
     *
     * @SWG\Get(
     *  path="/display",
     *  operationId="displaySearch",
     *  tags={"display"},
     *  summary="Display Search",
     *  description="Search Displays for this User",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="query",
     *      description="Filter by Display Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="query",
     *      description="Filter by DisplayGroup Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="display",
     *      in="query",
     *      description="Filter by Display Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="macAddress",
     *      in="query",
     *      description="Filter by Mac Address",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="hardwareKey",
     *      in="query",
     *      description="Filter by Hardware Key",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="clientVersion",
     *      in="query",
     *      description="Filter by Client Version",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="clientType",
     *      in="query",
     *      description="Filter by Client Type",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="clientCode",
     *      in="query",
     *      description="Filter by Client Code",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
     *      description="Embed related data, namely displaygroups. A comma separated list of child objects to embed.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="authorised",
     *      in="query",
     *      description="Filter by authorised flag",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayProfileId",
     *      in="query",
     *      description="Filter by Display Profile",
     *      type="integer",
     *      required=false
     *   ),
     *  *  @SWG\Parameter(
     *      name="mediaInventoryStatus",
     *      in="query",
     *      description="Filter by Display Status ( 1 - up to date, 2 - downloading, 3 - Out of date)",
     *      type="integer",
     *      required=false
     *   ),
     *  *  @SWG\Parameter(
     *      name="loggedIn",
     *      in="query",
     *      description="Filter by Logged In flag",
     *      type="integer",
     *      required=false
     *   ),
     *  *  @SWG\Parameter(
     *      name="lastAccessed",
     *      in="query",
     *      description="Filter by Display Last Accessed date, expects date in Y-m-d H:i:s format",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Display")
     *      )
     *  )
     * )
     *
     * @throws \Xibo\Exception\XiboException
     */
    function grid()
    {
        // Embed?
        $embed = ($this->getSanitizer()->getString('embed') != null) ? explode(',', $this->getSanitizer()->getString('embed')) : [];

        $filter = [
            'displayId' => $this->getSanitizer()->getInt('displayId'),
            'display' => $this->getSanitizer()->getString('display'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'macAddress' => $this->getSanitizer()->getString('macAddress'),
            'license' => $this->getSanitizer()->getString('hardwareKey'),
            'displayGroupId' => $this->getSanitizer()->getInt('displayGroupId'),
            'clientVersion' => $this->getSanitizer()->getString('clientVersion'),
            'clientType' => $this->getSanitizer()->getString('clientType'),
            'clientCode' => $this->getSanitizer()->getString('clientCode'),
            'authorised' => $this->getSanitizer()->getInt('authorised'),
            'displayProfileId' => $this->getSanitizer()->getInt('displayProfileId'),
            'tags' => $this->getSanitizer()->getString('tags'),
            'exactTags' => $this->getSanitizer()->getCheckbox('exactTags'),
            'showTags' => true,
            'clientAddress' => $this->getSanitizer()->getString('clientAddress'),
            'mediaInventoryStatus' => $this->getSanitizer()->getInt('mediaInventoryStatus'),
            'loggedIn' => $this->getSanitizer()->getInt('loggedIn'),
            'lastAccessed' => ($this->getSanitizer()->getDate('lastAccessed') != null) ? $this->getSanitizer()->getDate('lastAccessed')->format('U') : null,
            'displayGroupIdMembers' => $this->getSanitizer()->getInt('displayGroupIdMembers'),
            'orientation' => $this->getSanitizer()->getString('orientation'),
            'commercialLicence' => $this->getSanitizer()->getInt('commercialLicence')
        ];

        // Get a list of displays
        $displays = $this->displayFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        // Get all Display Profiles
        $displayProfiles = [];
        foreach ($this->displayProfileFactory->query() as $displayProfile) {
            $displayProfiles[$displayProfile->displayProfileId] = $displayProfile->name;
        }

        // validate displays so we get a realistic view of the table
        $this->validateDisplays($displays);

        foreach ($displays as $display) {
            /* @var \Xibo\Entity\Display $display */
            if (in_array('displaygroups', $embed)) {
                $display->load();
            } else {
                $display->excludeProperty('displayGroups');
            }

            if (in_array('overrideconfig', $embed)) {
                $display->includeProperty('overrideConfig');
            }

            $display->bandwidthLimitFormatted = ByteFormatter::format($display->bandwidthLimit * 1024);

            // Current layout from cache
            $display->setChildObjectDependencies($this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
            $display->getCurrentLayoutId($this->pool);

            if ($this->isApi()) {
                $display->lastAccessed = $this->getDate()->getLocalDate($display->lastAccessed);
                $display->auditingUntil = ($display->auditingUntil == 0) ? 0 : $this->getDate()->getLocalDate($display->auditingUntil);
                $display->storageAvailableSpace = ByteFormatter::format($display->storageAvailableSpace);
                $display->storageTotalSpace = ByteFormatter::format($display->storageTotalSpace);
                continue;
            }

            // use try and catch here to cover scenario when there is no default display profile set for any of the existing display types.
            $displayProfileName = '';
            try {
                $defaultDisplayProfile = $this->displayProfileFactory->getDefaultByType($display->clientType);
                $displayProfileName = $defaultDisplayProfile->name;
            } catch (NotFoundException $e) {
                $this->getLog()->debug('No default Display Profile set for Display type ' . $display->clientType);
            }

            // Add in the display profile information
            $display->displayProfile = (!array_key_exists($display->displayProfileId, $displayProfiles)) ? $displayProfileName . __(' (Default)') : $displayProfiles[$display->displayProfileId];

            $display->includeProperty('buttons');

            // Format the storage available / total space
            $display->storageAvailableSpaceFormatted = ByteFormatter::format($display->storageAvailableSpace);
            $display->storageTotalSpaceFormatted = ByteFormatter::format($display->storageTotalSpace);
            $display->storagePercentage = ($display->storageTotalSpace == 0) ? 0 : round($display->storageAvailableSpace / $display->storageTotalSpace * 100.0, 2);

            // Set some text for the display status
            switch ($display->mediaInventoryStatus) {
                case 1:
                    $display->statusDescription = __('Display is up to date');
                    break;

                case 2:
                    $display->statusDescription = __('Display is downloading new files');
                    break;

                case 3:
                    $display->statusDescription = __('Display is out of date but has not yet checked in with the server');
                    break;

                default:
                    $display->statusDescription = __('Unknown Display Status');
            }

            // Commercial Licence
            switch ($display->commercialLicence) {
                case 1:
                    $display->commercialLicenceDescription = __('Display is fully licensed');
                    break;

                case 2:
                    $display->commercialLicenceDescription = __('Display is on a trial licence');
                    break;

                default:
                    $display->commercialLicenceDescription = __('Display is not licensed');
            }

            // Thumbnail
            $display->thumbnail = '';
            // If we aren't logged in, and we are showThumbnail == 2, then show a circle
            if (file_exists($this->getConfig()->getSetting('LIBRARY_LOCATION') . 'screenshots/' . $display->displayId . '_screenshot.jpg')) {
                $display->thumbnail = $this->urlFor('display.screenShot', ['id' => $display->displayId]) . '?' . Random::generateString();
            }

            // Remote support buttons.
            $teamViewerSerial = '';
            $webkeySerial = '';
            foreach ($display->overrideConfig as $override) {
                if ($override['name'] === 'teamViewerSerial') {
                    $teamViewerSerial = $override['value'];
                }
                if ($override['name'] === 'webkeySerial') {
                    $webkeySerial = $override['value'];
                }
            }

            $display->teamViewerLink = (!empty($teamViewerSerial)) ? 'https://start.teamviewer.com/' . $teamViewerSerial : '';
            $display->webkeyLink = (!empty($webkeySerial)) ? 'https://webkeyapp.com/mgm?publicid=' . $webkeySerial : '';

            // Is a transfer to another CMS in progress?
            $display->isCmsTransferInProgress = (!empty($display->newCmsAddress));

            // Edit and Delete buttons first
            if ($this->getUser()->checkEditable($display)) {

                // Manage
                $display->buttons[] = array(
                    'id' => 'display_button_manage',
                    'url' => $this->urlFor('display.manage', ['id' => $display->displayId]),
                    'text' => __('Manage'),
                    'external' => true
                );

                $display->buttons[] = ['divider' => true];

                // Edit
                $display->buttons[] = array(
                    'id' => 'display_button_edit',
                    'url' => $this->urlFor('display.edit.form', ['id' => $display->displayId]),
                    'text' => __('Edit')
                );
            }

            // Delete
            if ($this->getUser()->checkDeleteable($display)) {
                $display->buttons[] = array(
                    'id' => 'display_button_delete',
                    'url' => $this->urlFor('display.delete.form', ['id' => $display->displayId]),
                    'text' => __('Delete'),
                    /*'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('display.delete', ['id' => $display->displayId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'display_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $display->display)
                    )*/
                );
            }

            if ($this->getUser()->checkEditable($display) || $this->getUser()->checkDeleteable($display)) {
                $display->buttons[] = ['divider' => true];
            }

            if ($this->getUser()->checkEditable($display)) {

                // Authorise
                $display->buttons[] = array(
                    'id' => 'display_button_authorise',
                    'url' => $this->urlFor('display.authorise.form', ['id' => $display->displayId]),
                    'text' => __('Authorise'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('display.authorise', ['id' => $display->displayId])),
                        array('name' => 'commit-method', 'value' => 'put'),
                        array('name' => 'id', 'value' => 'display_button_authorise'),
                        array('name' => 'text', 'value' => __('Toggle Authorise')),
                        array('name' => 'rowtitle', 'value' => $display->display)
                    )
                );

                // Default Layout
                $display->buttons[] = array(
                    'id' => 'display_button_defaultlayout',
                    'url' => $this->urlFor('display.defaultlayout.form', ['id' => $display->displayId]),
                    'text' => __('Default Layout'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('display.defaultlayout', ['id' => $display->displayId])),
                        array('name' => 'commit-method', 'value' => 'put'),
                        array('name' => 'id', 'value' => 'display_button_defaultlayout'),
                        array('name' => 'text', 'value' => __('Set Default Layout')),
                        array('name' => 'rowtitle', 'value' => $display->display),
                        ['name' => 'form-callback', 'value' => 'setDefaultMultiSelectFormOpen']
                    )
                );

                if (in_array($display->clientType, ['android', 'lg', 'sssp'])) {
                    $display->buttons[] = array(
                        'id' => 'display_button_checkLicence',
                        'url' => $this->urlFor('display.licencecheck.form', ['id' => $display->displayId]),
                        'text' => __('Check Licence'),
                        'multi-select' => true,
                        'dataAttributes' => array(
                            array('name' => 'commit-url', 'value' => $this->urlFor('display.licencecheck', ['id' => $display->displayId])),
                            array('name' => 'commit-method', 'value' => 'put'),
                            array('name' => 'id', 'value' => 'display_button_checkLicence'),
                            array('name' => 'text', 'value' => __('Check Licence')),
                            array('name' => 'rowtitle', 'value' => $display->display)
                        )
                    );
                }

                $display->buttons[] = ['divider' => true];
            }

            // Schedule Now
            if (($this->getUser()->checkEditable($display) || $this->getConfig()->getSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 1) && $this->getUser()->routeViewable('/schedulenow/form/now/:from/:id') === true ) {
                $display->buttons[] = array(
                    'id' => 'display_button_schedulenow',
                    'url' => $this->urlFor('schedulenow.now.form', ['id' => $display->displayGroupId, 'from' => 'DisplayGroup']),
                    'text' => __('Schedule Now')
                );
            }

            if ($this->getUser()->checkEditable($display)) {

                if ($this->getUser()->routeViewable('/layout/view')) {
                    $display->buttons[] = [
                        'id' => 'display_button_layouts_jump',
                        'linkType' => '_self',
                        'external' => true,
                        'url' => $this->urlFor('layout.view') . '?activeDisplayGroupId=' . $display->displayGroupId,
                        'text' => __('Jump to Scheduled Layouts')
                    ];
                }

                // File Associations
                $display->buttons[] = array(
                    'id' => 'displaygroup_button_fileassociations',
                    'url' => $this->urlFor('displayGroup.media.form', ['id' => $display->displayGroupId]),
                    'text' => __('Assign Files')
                );

                // Layout Assignments
                $display->buttons[] = array(
                    'id' => 'displaygroup_button_layout_associations',
                    'url' => $this->urlFor('displayGroup.layout.form', ['id' => $display->displayGroupId]),
                    'text' => __('Assign Layouts')
                );

                // Screen Shot
                $display->buttons[] = array(
                    'id' => 'display_button_requestScreenShot',
                    'url' => $this->urlFor('display.screenshot.form', ['id' => $display->displayId]),
                    'text' => __('Request Screen Shot'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('display.requestscreenshot', ['id' => $display->displayId])),
                        array('name' => 'commit-method', 'value' => 'put'),
                        array('name' => 'id', 'value' => 'display_button_requestScreenShot'),
                        array('name' => 'text', 'value' => __('Request Screen Shot')),
                        array('name' => 'rowtitle', 'value' => $display->display)
                    )
                );

                // Collect Now
                $display->buttons[] = array(
                    'id' => 'display_button_collectNow',
                    'url' => $this->urlFor('displayGroup.collectNow.form', ['id' => $display->displayGroupId]),
                    'text' => __('Collect Now'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('displayGroup.action.collectNow', ['id' => $display->displayGroupId])),
                        array('name' => 'commit-method', 'value' => 'post'),
                        array('name' => 'id', 'value' => 'display_button_collectNow'),
                        array('name' => 'text', 'value' => __('Collect Now')),
                        array('name' => 'rowtitle', 'value' => $display->display)
                    )
                );

                $display->buttons[] = ['divider' => true];
            }

            if ($this->getUser()->checkPermissionsModifyable($display)) {

                // Display Groups
                $display->buttons[] = array(
                    'id' => 'display_button_group_membership',
                    'url' => $this->urlFor('display.membership.form', ['id' => $display->displayId]),
                    'text' => __('Display Groups')
                );

                // Permissions
                $display->buttons[] = array(
                    'id' => 'display_button_group_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'DisplayGroup', 'id' => $display->displayGroupId]),
                    'text' => __('Permissions')
                );
            }

            if ($this->getUser()->checkEditable($display)) {

                if ($this->getUser()->checkPermissionsModifyable($display)) {
                    $display->buttons[] = ['divider' => true];
                }

                // Wake On LAN
                $display->buttons[] = array(
                    'id' => 'display_button_wol',
                    'url' => $this->urlFor('display.wol.form', ['id' => $display->displayId]),
                    'text' => __('Wake on LAN')
                );

                $display->buttons[] = array(
                    'id' => 'displaygroup_button_command',
                    'url' => $this->urlFor('displayGroup.command.form', ['id' => $display->displayGroupId]),
                    'text' => __('Send Command')
                );

                $display->buttons[] = ['divider' => true];

                $display->buttons[] = [
                    'id' => 'display_button_move_cms',
                    'url' => $this->urlFor('display.moveCms.form', ['id' => $display->displayId]),
                    'text' => __('Transfer to another CMS'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor('display.moveCms', ['id' => $display->displayId])],
                        ['name' => 'commit-method', 'value' => 'put'],
                        ['name' => 'id', 'value' => 'display_button_move_cms'],
                        ['name' => 'text', 'value' => __('Transfer to another CMS')],
                        ['name' => 'rowtitle', 'value' => $display->display],
                        ['name' => 'form-callback', 'value' => 'setMoveCmsMultiSelectFormOpen']
                    ]
                ];

                if ($display->isCmsTransferInProgress) {
                    $display->buttons[] = [
                        'id' => 'display_button_move_cancel',
                        'url' => $this->urlFor('display.moveCmsCancel.form', ['id' => $display->displayId]),
                        'text' => __('Cancel CMS Transfer'),
                    ];
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->displayFactory->countLast();
        $this->getState()->setData($displays);
    }

    /**
     * Edit Display Form
     * @param int $displayId
     * @throws \Xibo\Exception\XiboException
     */
    function editForm($displayId)
    {
        $display = $this->displayFactory->getById($displayId, true);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        // We have permission - load
        $display->load();

        $tags = '';

        $arrayOfTags = array_filter(explode(',', $display->tags));
        $arrayOfTagValues = array_filter(explode(',', $display->tagValues));

        for ($i=0; $i<count($arrayOfTags); $i++) {
            if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                $tags .= $arrayOfTags[$i] . '|' . $arrayOfTagValues[$i];
                $tags .= ',';
            } else {
                $tags .= $arrayOfTags[$i] . ',';
            }
        }

        // Dates
        $display->auditingUntilIso = $this->getDate()->getLocalDate($display->auditingUntil);

        // Get the settings from the profile
        $profile = $display->getSettings();

        // Get a list of timezones
        $timeZones = [];
        foreach ($this->getDate()->timezoneList() as $key => $value) {
            $timeZones[] = ['id' => $key, 'value' => $value];
        }

        // Get the currently assigned default layout
        try {
            $layouts = (($display->defaultLayoutId != null) ? [$this->layoutFactory->getById($display->defaultLayoutId)] : []);
        } catch (NotFoundException $notFoundException) {
            $layouts = [];
        }

        // Player Version Setting
        $versionId = $display->getSetting('versionMediaId', null, ['displayOnly' => true]);
        $profileVersionId = $display->getDisplayProfile()->getSetting('versionMediaId');
        $playerVersions = [];

        // Daypart - Operating Hours
        $dayPartId = $display->getSetting('dayPartId', null,['displayOnly' => true]);
        $profileDayPartId = $display->getDisplayProfile()->getSetting('dayPartId');
        $dayparts = [];

        // Get the Player Version for this display profile type
        if ($versionId !== null) {
            try {
                $playerVersions[] = $this->playerVersionFactory->getByMediaId($versionId);
            } catch (NotFoundException $e) {
                $this->getLog()->debug('Unknown versionId set on Display Profile for displayId ' . $display->displayId);
            }
        }

        if ($versionId !== $profileVersionId) {
            try {
                $playerVersions[] = $this->playerVersionFactory->getByMediaId($profileVersionId);
            } catch (NotFoundException $e) {
                $this->getLog()->debug('Unknown versionId set on Display Profile for displayId ' . $display->displayId);
            }
        }

        if ($dayPartId !== null) {
            try {
                $dayparts[] = $this->dayPartFactory->getById($dayPartId);
            } catch (NotFoundException $e) {
                $this->getLog()->debug('Unknown dayPartId set on Display Profile for displayId ' . $display->displayId);
            }
        }

        if ($dayPartId !== $profileDayPartId) {
            try {
                $dayparts[] = $this->dayPartFactory->getById($profileDayPartId);
            } catch (NotFoundException $e) {
                $this->getLog()->debug('Unknown dayPartId set on Display Profile for displayId ' . $display->displayId);
            }
        }

        // These are temporary additions and will be removed in v3
        $teamViewerSerial = '';
        $webkeySerial = '';
        foreach ($display->overrideConfig as $override) {
            if ($override['name'] === 'teamViewerSerial') {
                $teamViewerSerial = $override['value'];
            }
            if ($override['name'] === 'webkeySerial') {
                $webkeySerial = $override['value'];
            }
        }

        $this->getState()->template = 'display-form-edit';
        $this->getState()->setData([
            'display' => $display,
            'displayProfile' => $display->getDisplayProfile(),
            'lockOptions' => json_decode($display->getDisplayProfile()->getSetting('lockOptions', '[]'), true),
            'layouts' => $layouts,
            'profiles' => $this->displayProfileFactory->query(NULL, array('type' => $display->clientType)),
            'settings' => $profile,
            'timeZones' => $timeZones,
            'displayLockName' => ($this->getConfig()->getSetting('DISPLAY_LOCK_NAME_TO_DEVICENAME') == 1),
            'help' => $this->getHelp()->link('Display', 'Edit'),
            'versions' => $playerVersions,
            'tags' => $tags,
            'dayParts' => $dayparts,
            // These are temporary additions and will be removed in v3
            'teamViewerSerial' => $teamViewerSerial,
            'webkeySerial' => $webkeySerial
        ]);
    }

    /**
     * Delete form
     * @param int $displayId
     */
    function deleteForm($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkDeleteable($display))
            throw new AccessDeniedException();

        $this->getState()->template = 'display-form-delete';
        $this->getState()->setData([
            'display' => $display,
            'help' => $this->getHelp()->link('Display', 'Delete')
        ]);
    }

    /**
     * Display Edit
     * @param int $displayId
     *
     * @SWG\Put(
     *  path="/display/{displayId}",
     *  operationId="displayEdit",
     *  tags={"display"},
     *  summary="Display Edit",
     *  description="Edit a Display",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="display",
     *      in="formData",
     *      description="The Display Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of the Display",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="A comma separated list of tags for this item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="auditingUntil",
     *      in="formData",
     *      description="A date this Display records auditing information until.",
     *      type="string",
     *      format="date-time",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="defaultLayoutId",
     *      in="formData",
     *      description="A Layout ID representing the Default Layout for this Display.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="licensed",
     *      in="formData",
     *      description="Flag indicating whether this display is licensed.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="license",
     *      in="formData",
     *      description="The hardwareKey to use as the licence key for this Display",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="incSchedule",
     *      in="formData",
     *      description="Flag indicating whether the Default Layout should be included in the Schedule",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="emailAlert",
     *      in="formData",
     *      description="Flag indicating whether the Display generates up/down email alerts.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="alertTimeout",
     *      in="formData",
     *      description="How long in seconds should this display wait before alerting when it hasn't connected. Override for the collection interval.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="wakeOnLanEnabled",
     *      in="formData",
     *      description="Flag indicating if Wake On LAN is enabled for this Display",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="wakeOnLanTime",
     *      in="formData",
     *      description="A h:i string representing the time that the Display should receive its Wake on LAN command",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="broadCastAddress",
     *      in="formData",
     *      description="The BroadCast Address for this Display - used by Wake On LAN",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="secureOn",
     *      in="formData",
     *      description="The secure on configuration for this Display",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="cidr",
     *      in="formData",
     *      description="The CIDR configuration for this Display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="latitude",
     *      in="formData",
     *      description="The Latitude of this Display",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="longitude",
     *      in="formData",
     *      description="The Longitude of this Display",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="timeZone",
     *      in="formData",
     *      description="The timezone for this display, or empty to use the CMS timezone",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayProfileId",
     *      in="formData",
     *      description="The Display Settings Profile ID",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="clearCachedData",
     *      in="formData",
     *      description="Clear all Cached data for this display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="rekeyXmr",
     *      in="formData",
     *      description="Clear the cached XMR configuration and send a rekey",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="teamViewerSerial",
     *      in="formData",
     *      description="The TeamViewer serial number for this Display, if applicable",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="webkeySerial",
     *      in="formData",
     *      description="The Webkey serial number for this Display, if applicable",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Display")
     *  )
     * )
     *
     * @throws \Xibo\Exception\XiboException
     */
    function edit($displayId)
    {
        $display = $this->displayFactory->getById($displayId, true);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        // Update properties
        if ($this->getConfig()->getSetting('DISPLAY_LOCK_NAME_TO_DEVICENAME') == 0)
            $display->display = $this->getSanitizer()->getString('display');

        $display->load();

        $display->description = $this->getSanitizer()->getString('description');
        $display->auditingUntil = $this->getSanitizer()->getDate('auditingUntil');
        $display->defaultLayoutId = $this->getSanitizer()->getInt('defaultLayoutId');
        $display->licensed = $this->getSanitizer()->getInt('licensed');
        $display->license = $this->getSanitizer()->getString('license');
        $display->incSchedule = $this->getSanitizer()->getInt('incSchedule', 0);
        $display->emailAlert = $this->getSanitizer()->getInt('emailAlert');
        $display->alertTimeout = $this->getSanitizer()->getCheckbox('alertTimeout');
        $display->wakeOnLanEnabled = $this->getSanitizer()->getCheckbox('wakeOnLanEnabled');
        $display->wakeOnLanTime = $this->getSanitizer()->getString('wakeOnLanTime');
        $display->broadCastAddress = $this->getSanitizer()->getString('broadCastAddress');
        $display->secureOn = $this->getSanitizer()->getString('secureOn');
        $display->cidr = $this->getSanitizer()->getString('cidr');
        $display->latitude = $this->getSanitizer()->getDouble('latitude');
        $display->longitude = $this->getSanitizer()->getDouble('longitude');
        $display->timeZone = $this->getSanitizer()->getString('timeZone');
        $display->displayProfileId = $this->getSanitizer()->getInt('displayProfileId');
        $display->bandwidthLimit = $this->getSanitizer()->getInt('bandwidthLimit', 0);


        // Get the display profile and use that to pull in any overrides
        // start with an empty config
        $display->overrideConfig = $this->editConfigFields($display->getDisplayProfile(), [], $display);

        // Workaround
        // in v3 these will have their own fields.
        $profile = $display->getDisplayProfile();
        $profile->setSetting('teamViewerSerial', $this->getSanitizer()->getString('teamViewerSerial'),
            false, $display->overrideConfig);
        $profile->setSetting('webkeySerial', $this->getSanitizer()->getString('webkeySerial'),
            false, $display->overrideConfig);

        // Tags are stored on the displaygroup, we're just passing through here
        $display->tags = $this->tagFactory->tagsFromString($this->getSanitizer()->getString('tags'));

        if ($display->auditingUntil !== null)
            $display->auditingUntil = $display->auditingUntil->format('U');

        // Should we invalidate this display?
        if ($display->hasPropertyChanged('defaultLayoutId')) {
            $display->notify();
        } else if ($this->getSanitizer()->getCheckbox('clearCachedData', 1) == 1) {
            // Remove the cache if the display licenced state has changed
            $this->pool->deleteItem($display->getCacheKey());
        }

        // Should we rekey?
        if ($this->getSanitizer()->getCheckbox('rekeyXmr', 0) == 1) {
            // Queue the rekey action first (before we clear the channel and key)
            $this->playerAction->sendAction($display, new RekeyAction());

            // Clear the config.
            $display->xmrChannel = null;
            $display->xmrPubKey = null;
        }

        $display->setChildObjectDependencies($this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $display->save();

        if ($this->isApi()) {
            $display->lastAccessed = $this->getDate()->getLocalDate($display->lastAccessed);
            $display->auditingUntil = ($display->auditingUntil == 0) ? 0 : $this->getDate()->getLocalDate($display->auditingUntil);
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $display->display),
            'id' => $display->displayId,
            'data' => $display
        ]);
    }

    /**
     * Delete a display
     * @param int $displayId
     *
     * @SWG\Delete(
     *  path="/display/{displayId}",
     *  operationId="displayDelete",
     *  tags={"display"},
     *  summary="Display Delete",
     *  description="Delete a Display",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    function delete($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkDeleteable($display))
            throw new AccessDeniedException();

        $display->setChildObjectDependencies($this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $display->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $display->display),
            'id' => $display->displayId,
            'data' => $display
        ]);
    }

    /**
     * Member of Display Groups Form
     * @param int $displayId
     */
    public function membershipForm($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        // Groups we are assigned to
        $groupsAssigned = $this->displayGroupFactory->getByDisplayId($display->displayId);

        // All Groups
        $allGroups = $this->displayGroupFactory->getByIsDynamic(0);

        // The available users are all users except users already in assigned users
        $checkboxes = array();

        foreach ($allGroups as $group) {
            /* @var \Xibo\Entity\DisplayGroup $group */
            // Check to see if it exists in $usersAssigned
            $exists = false;
            foreach ($groupsAssigned as $groupAssigned) {
                /* @var \Xibo\Entity\DisplayGroup $groupAssigned */
                if ($groupAssigned->displayGroupId == $group->displayGroupId) {
                    $exists = true;
                    break;
                }
            }

            // Store this checkbox
            $checkbox = array(
                'id' => $group->displayGroupId,
                'name' => $group->displayGroup,
                'value_checked' => (($exists) ? 'checked' : '')
            );

            $checkboxes[] = $checkbox;
        }

        $this->getState()->template = 'display-form-membership';
        $this->getState()->setData([
            'display' => $display,
            'checkboxes' => $checkboxes,
            'help' =>  $this->getHelp()->link('Display', 'Members')
        ]);
    }

    /**
     * Assign Display to Display Groups
     * @param int $displayId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function assignDisplayGroup($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        // Go through each ID to assign
        foreach ($this->getSanitizer()->getIntArray('displayGroupId') as $displayGroupId) {
            $displayGroup = $this->displayGroupFactory->getById($displayGroupId);
            $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);

            if (!$this->getUser()->checkEditable($displayGroup))
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));

            $displayGroup->assignDisplay($display);
            $displayGroup->save(['validate' => false]);
        }

        // Have we been provided with unassign id's as well?
        foreach ($this->getSanitizer()->getIntArray('unassignDisplayGroupId') as $displayGroupId) {
            $displayGroup = $this->displayGroupFactory->getById($displayGroupId);
            $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);

            if (!$this->getUser()->checkEditable($displayGroup))
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));

            $displayGroup->unassignDisplay($display);
            $displayGroup->save(['validate' => false]);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('%s assigned to Display Groups'), $display->display),
            'id' => $display->displayId
        ]);
    }

    /**
     * Output a screen shot
     * @param int $displayId
     * @throws XiboException
     */
    public function screenShot($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        // The request will output its own content, disable framework
        $this->setNoOutput(true);

        // Output an image if present, otherwise not found image.
        $file = 'screenshots/' . $displayId . '_screenshot.jpg';

        // File upload directory.. get this from the settings object
        $library = $this->getConfig()->getSetting("LIBRARY_LOCATION");
        $fileName = $library . $file;

        if (!file_exists($fileName)) {
            $fileName = $this->getConfig()->uri('forms/filenotfound.gif');
        }

        Img::configure(array('driver' => 'gd'));
        $img = Img::make($fileName);

        $date = $display->getCurrentScreenShotTime($this->pool);

        if ($date != '') {
            $img
                ->rectangle(0, 0, 110, 15, function($draw) {
                    $draw->background('#ffffff');
                })
                ->text($date, 10, 10);
        }

        // Cache headers
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");

        // Disable any buffering to prevent OOM errors.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo $img->encode();
    }

    /**
     * Request ScreenShot form
     * @param int $displayId
     */
    public function requestScreenShotForm($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        // Work out the next collection time based on the last accessed date/time and the collection interval
        if ($display->lastAccessed == 0) {
            $nextCollect = __('once it has connected for the first time');
        } else {
            $collectionInterval = $display->getSetting('collectionInterval', 5);
            $nextCollect = $this->getDate()->parse($display->lastAccessed, 'U')->addMinutes($collectionInterval)->diffForHumans();
        }

        $this->getState()->template = 'display-form-request-screenshot';
        $this->getState()->setData([
            'display' => $display,
            'nextCollect' => $nextCollect,
            'help' =>  $this->getHelp()->link('Display', 'ScreenShot')
        ]);
    }

    /**
     * Request ScreenShot
     * @param int $displayId
     * @throws \InvalidArgumentException if XMR is not configured
     * @throws ConfigurationException if XMR cannot be contacted
     *
     * @SWG\Put(
     *  path="/display/requestscreenshot/{displayId}",
     *  operationId="displayRequestScreenshot",
     *  tags={"display"},
     *  summary="Request Screen Shot",
     *  description="Notify the display that the CMS would like a screen shot to be sent.",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Display")
     *  )
     * )
     */
    public function requestScreenShot($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        $display->screenShotRequested = 1;
        $display->save(['validate' => false, 'audit' => false]);

        if (!empty($display->xmrChannel))
            $this->playerAction->sendAction($display, new ScreenShotAction());

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Request sent for %s'), $display->display),
            'id' => $display->displayId
        ]);
    }

    /**
     * Form for wake on Lan
     * @param int $displayId
     */
    public function wakeOnLanForm($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        if ($display->macAddress == '')
            throw new \InvalidArgumentException(__('This display has no mac address recorded against it yet. Make sure the display is running.'));

        $this->getState()->template = 'display-form-wakeonlan';
        $this->getState()->setData([
            'display' => $display,
            'help' =>  $this->getHelp()->link('Display', 'WakeOnLan')
        ]);
    }

    /**
     * Wake this display using a WOL command
     * @param int $displayId
     *
     * @SWG\Post(
     *  path="/display/wol/{displayId}",
     *  operationId="displayWakeOnLan",
     *  tags={"display"},
     *  summary="Issue WOL",
     *  description="Send a Wake On LAN packet to this Display",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function wakeOnLan($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkViewable($display))
            throw new AccessDeniedException();

        if ($display->macAddress == '' || $display->broadCastAddress == '')
            throw new \InvalidArgumentException(__('This display has no mac address recorded against it yet. Make sure the display is running.'));

        $this->getLog()->notice('About to send WOL packet to ' . $display->broadCastAddress . ' with Mac Address ' . $display->macAddress);

        WakeOnLan::TransmitWakeOnLan($display->macAddress, $display->secureOn, $display->broadCastAddress, $display->cidr, '9', $this->getLog());

        $display->lastWakeOnLanCommandSent = time();
        $display->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Wake on Lan sent for %s'), $display->display),
            'id' => $display->displayId
        ]);
    }

    /**
     * Validate the display list
     * @param array[Display] $displays
     * @throws XiboException
     */
    public function validateDisplays($displays)
    {
        // Get the global time out (overrides the alert time out on the display if 0)
        $globalTimeout = $this->getConfig()->getSetting('MAINTENANCE_ALERT_TOUT') * 60;
        $emailAlerts = ($this->getConfig()->getSetting("MAINTENANCE_EMAIL_ALERTS") == 1);
        $alwaysAlert = ($this->getConfig()->getSetting("MAINTENANCE_ALWAYS_ALERT") == 1);

        foreach ($displays as $display) {
            /* @var \Xibo\Entity\Display $display */

            // Should we test against the collection interval or the preset alert timeout?
            if ($display->alertTimeout == 0 && $display->clientType != '') {
                $timeoutToTestAgainst = ((double)$display->getSetting('collectInterval', $globalTimeout)) * 1.1;
            }
            else {
                $timeoutToTestAgainst = $globalTimeout;
            }

            // Store the time out to test against
            $timeOut = $display->lastAccessed + $timeoutToTestAgainst;

            // If the last time we accessed is less than now minus the time out
            if ($timeOut < time()) {
                $this->getLog()->debug('Timed out display. Last Accessed: ' . date('Y-m-d h:i:s', $display->lastAccessed) . '. Time out: ' . date('Y-m-d h:i:s', $timeOut));

                // Is this the first time this display has gone "off-line"
                $displayOffline = ($display->loggedIn == 1);

                // If this is the first switch (i.e. the row was logged in before)
                if ($displayOffline) {
                    // Update the display and set it as logged out
                    $display->loggedIn = 0;
                    $display->save(\Xibo\Entity\Display::$saveOptionsMinimum);

                    // Log the down event
                    $event = $this->displayEventFactory->createEmpty();
                    $event->displayId = $display->displayId;
                    $event->start = $display->lastAccessed;
                    $event->save();
                }

                $dayPartId = $display->getSetting('dayPartId', null,['displayOverride' => true]);
                $operatingHours = true;

                if ($dayPartId !== null) {
                    try {
                        $dayPart = $this->dayPartFactory->getById($dayPartId);

                        $startTimeArray = explode(':', $dayPart->startTime);
                        $startTime = Date::now()->setTime(intval($startTimeArray[0]), intval($startTimeArray[1]));

                        $endTimeArray = explode(':', $dayPart->endTime);
                        $endTime = Date::now()->setTime(intval($endTimeArray[0]), intval($endTimeArray[1]));

                        $now = Date::now();

                        // exceptions
                        foreach ($dayPart->exceptions as $exception) {

                            // check if we are on exception day and if so override the start and endtime accordingly
                            if ($exception['day'] == Date::now()->format('D')) {

                                $exceptionsStartTime = explode(':', $exception['start']);
                                $startTime = Date::now()->setTime(intval($exceptionsStartTime[0]), intval($exceptionsStartTime[1]));

                                $exceptionsEndTime = explode(':', $exception['end']);
                                $endTime = Date::now()->setTime(intval($exceptionsEndTime[0]), intval($exceptionsEndTime[1]));
                            }
                        }

                        // check if we are inside the operating hours for this display - we use that flag to decide if we need to create a notification and send an email.
                        if (($now >= $startTime && $now <= $endTime)) {
                            $operatingHours = true;
                        } else {
                            $operatingHours = false;
                        }

                    } catch (NotFoundException $e) {
                        $this->getLog()->debug('Unknown dayPartId set on Display Profile for displayId ' . $display->displayId);
                    }
                }

                // Should we create a notification
                if ($emailAlerts && $display->emailAlert == 1 && ($displayOffline || $alwaysAlert)) {
                    // Alerts enabled for this display
                    // Display just gone offline, or always alert
                    // Fields for email

                    // for displays without dayPartId set, this is always true, otherwise we check if we are inside the operating hours set for this display
                    if ($operatingHours) {
                        $subject = sprintf(__("Alert for Display %s"), $display->display);
                        $body = sprintf(__("Display ID %d is offline since %s."), $display->displayId,
                            $this->getDate()->getLocalDate($display->lastAccessed));

                        // Add to system
                        $notification = $this->notificationFactory->createSystemNotification($subject, $body,
                            $this->getDate()->parse());

                        // Add in any displayNotificationGroups, with permissions
                        foreach ($this->userGroupFactory->getDisplayNotificationGroups($display->displayGroupId) as $group) {
                            $notification->assignUserGroup($group);
                        }

                        $notification->save();
                    } else {
                        $this->getLog()->info('Not sending email down alert for Display - ' . $display->display . ' we are outside of its operating hours');
                    }

                } else if ($displayOffline) {
                    $this->getLog()->info('Not sending an email for offline display - emailAlert = ' . $display->emailAlert . ', alwaysAlert = ' . $alwaysAlert);
                }
            }
        }
    }

    /**
     * Show the authorise form
     * @param $displayId
     * @throws NotFoundException
     */
    public function authoriseForm($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        $this->getState()->template = 'display-form-authorise';
        $this->getState()->setData([
            'display' => $display
        ]);
    }

    /**
     * Toggle Authorise on this Display
     * @param int $displayId
     *
     * @SWG\Put(
     *  path="/display/authorise/{displayId}",
     *  operationId="displayToggleAuthorise",
     *  tags={"display"},
     *  summary="Toggle authorised",
     *  description="Toggle authorised for the Display.",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     * @throws XiboException
     */
    public function toggleAuthorise($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        $display->licensed = ($display->licensed == 1) ? 0 : 1;
        $display->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Authorised set to %d for %s'), $display->licensed, $display->display),
            'id' => $display->displayId
        ]);
    }

    /**
     * @param $displayId
     * @throws NotFoundException
     */
    public function defaultLayoutForm($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        // Get the currently assigned default layout
        try {
            $layouts = (($display->defaultLayoutId != null) ? [$this->layoutFactory->getById($display->defaultLayoutId)] : []);
        } catch (NotFoundException $notFoundException) {
            $layouts = [];
        }

        $this->getState()->template = 'display-form-defaultlayout';
        $this->getState()->setData([
            'display' => $display,
            'layouts' => $layouts
        ]);
    }

    /**
     * Set the Default Layout for this Display
     * @param int $displayId
     *
     * @SWG\Put(
     *  path="/display/defaultlayout/{displayId}",
     *  operationId="displayDefaultLayout",
     *  tags={"display"},
     *  summary="Set Default Layout",
     *  description="Set the default Layout on this Display",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     * @throws XiboException
     */
    public function setDefaultLayout($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display))
            throw new AccessDeniedException();

        $layoutId = $this->getSanitizer()->getInt('layoutId');

        $layout = $this->layoutFactory->getById($layoutId);

        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        $display->defaultLayoutId = $layoutId;
        $display->save(['validate' => false]);
        if ($display->hasPropertyChanged('defaultLayoutId'))
            $display->notify();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Default Layout with name %s set for %s'), $layout->layout, $display->display),
            'id' => $display->displayId
        ]);
    }

    /**
     * @param $displayId
     * @throws NotFoundException
     */
    public function moveCmsForm($displayId)
    {
        if ($this->getUser()->twoFactorTypeId != 2) {
            throw new AccessDeniedException('This action requires active Google Authenticator Two Factor authentication');
        }

        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'display-form-moveCms';
        $this->getState()->setData([
            'display' => $display,
            'newCmsAddress' => $display->newCmsAddress,
            'newCmsKey' => $display->newCmsKey
        ]);

    }

    /**
     * @param $displayId
     * @throws NotFoundException
     * @throws \RobThree\Auth\TwoFactorAuthException
     * @throws InvalidArgumentException
     * @throws XiboException
     */
    public function moveCms($displayId)
    {
        if ($this->getUser()->twoFactorTypeId != 2) {
            throw new AccessDeniedException('This action requires active Google Authenticator Two Factor authentication');
        }

        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        // Two Factor Auth
        $issuerSettings = $this->getConfig()->getSetting('TWOFACTOR_ISSUER');
        $appName = $this->getConfig()->getThemeConfig('app_name');

        if ($issuerSettings !== '') {
            $issuer = $issuerSettings;
        } else {
            $issuer = $appName;
        }

        $authenticationCode = $this->getSanitizer()->getString('twoFactorCode', '');

        $tfa = new TwoFactorAuth($issuer);
        $result = $tfa->verifyCode($this->getUser()->twoFactorSecret, $authenticationCode);

        if ($result) {

            // get the new CMS Address and Key from the form.
            $newCmsAddress = $this->getSanitizer()->getString('newCmsAddress');
            $newCmsKey = $this->getSanitizer()->getString('newCmsKey');

            // validate the URL
            if (!v::url()->notEmpty()->validate(urldecode($newCmsAddress)) || !filter_var($newCmsAddress, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException(__('Provided CMS URL is invalid'), 'newCmsUrl');
            }

            if ($newCmsKey == '') {
                throw new InvalidArgumentException(__('Provided CMS Key is invalid'), 'newCmsKey');
            }

            // we are successfully authenticated, get new CMS address and Key and save the Display record.
            $display->newCmsAddress = $newCmsAddress;
            $display->newCmsKey = $newCmsKey;
            $display->save();

        } else {
            throw new InvalidArgumentException(__('Invalid Two Factor Authentication Code'), 'twoFactorCode');
        }
    }

    /**
     * @param $displayId
     * @throws NotFoundException
     */
    public function moveCmsCancelForm($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'display-form-moveCmsCancel';
        $this->getState()->setData([
            'display' => $display
        ]);
    }

    /**
     * @param $displayId
     * @throws NotFoundException
     * @throws XiboException
     */
    public function moveCmsCancel($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        $display->newCmsAddress = '';
        $display->newCmsKey = '';
        $display->save();

        $this->getState()->hydrate([
            'message' => sprintf(__('Cancelled CMS Transfer for %s'), $display->display),
            'id' => $display->displayId
        ]);
    }

    public function addViaCodeForm()
    {
        $this->getState()->template = 'display-form-addViaCode';
    }

    /**
     * @throws InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function addViaCode()
    {
        $user_code = $this->getSanitizer()->getString('user_code');
        $cmsAddress = (new HttpsDetect())->getUrl();
        $cmsKey = $this->getConfig()->getSetting('SERVER_KEY');

        if ($user_code == '') {
            throw new InvalidArgumentException('Code cannot be empty', 'code');
        }

        $guzzle = new Client();

        try {
            // When the valid code is submitted, it will be sent along with CMS Address and Key to Authentication Service maintained by Xibo Signage Ltd.
            // The Player will then call the service with the same code to retrieve the CMS details.
            // On success, the details will be removed from the Authentication Service.
            $request = $guzzle->request('POST', 'https://auth.signlicence.co.uk/addDetails',
                $this->getConfig()->getGuzzleProxy([
                    'form_params' => [
                        'user_code' => $user_code,
                        'cmsAddress' => $cmsAddress,
                        'cmsKey' => $cmsKey,
                    ]
                ]));

            $data = json_decode($request->getBody(), true);

            $this->getState()->hydrate([
                'message' => $data['message']
            ]);
        } catch (\Exception $e) {
            $this->getLog()->debug($e->getMessage());
            throw new InvalidArgumentException('Provided user_code does not exist', 'user_code');
        }
    }

    /**
     * Check commercial licence form
     * @param int $displayId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function checkLicenceForm($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkViewable($display)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'display-form-licence-check';
        $this->getState()->setData([
            'display' => $display
        ]);
    }

    /**
     * Check commercial licence
     *
     * @SWG\Put(
     *  summary="Licence Check",
     *  path="/display/licenceCheck/{displayId}",
     *  operationId="displayLicenceCheck",
     *  tags={"display"},
     *  description="Ask this Player to check its Commercial Licence",
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="path",
     *      description="The Display ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @param int $displayId
     * @throws \Xibo\Exception\ConfigurationException if XMR cannot be contacted
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\XiboException
     */
    public function checkLicence($displayId)
    {
        $display = $this->displayFactory->getById($displayId);

        if (!$this->getUser()->checkViewable($display)) {
            throw new AccessDeniedException();
        }

        if (empty($display->xmrChannel)) {
            throw new InvalidArgumentException('XMR is not configured for this Display', 'xmrChannel');
        }

        $this->playerAction->sendAction($display, new LicenceCheckAction());

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Request sent for %s'), $display->display),
            'id' => $display->displayId
        ]);
    }
}
