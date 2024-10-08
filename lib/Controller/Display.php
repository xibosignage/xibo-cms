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
use GeoJson\Feature\Feature;
use GeoJson\Feature\FeatureCollection;
use GeoJson\Geometry\Point;
use GuzzleHttp\Client;
use Intervention\Image\ImageManagerStatic as Img;
use Respect\Validation\Validator as v;
use RobThree\Auth\TwoFactorAuth;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayEventFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\DisplayTypeFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Environment;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\Random;
use Xibo\Helper\WakeOnLan;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\XMR\LicenceCheckAction;
use Xibo\XMR\PurgeAllAction;
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
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * @var DisplayTypeFactory
     */
    private $displayTypeFactory;

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
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param PlayerActionServiceInterface $playerAction
     * @param DisplayFactory $displayFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayTypeFactory $displayTypeFactory
     * @param LayoutFactory $layoutFactory
     * @param DisplayProfileFactory $displayProfileFactory
     * @param DisplayEventFactory $displayEventFactory
     * @param RequiredFileFactory $requiredFileFactory
     * @param TagFactory $tagFactory
     * @param NotificationFactory $notificationFactory
     * @param UserGroupFactory $userGroupFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param DayPartFactory $dayPartFactory
     */
    public function __construct($store, $pool, $playerAction, $displayFactory, $displayGroupFactory, $displayTypeFactory, $layoutFactory, $displayProfileFactory, $displayEventFactory, $requiredFileFactory, $tagFactory, $notificationFactory, $userGroupFactory, $playerVersionFactory, $dayPartFactory)
    {
        $this->store = $store;
        $this->pool = $pool;
        $this->playerAction = $playerAction;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayTypeFactory = $displayTypeFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->displayEventFactory = $displayEventFactory;
        $this->requiredFileFactory = $requiredFileFactory;
        $this->tagFactory = $tagFactory;
        $this->notificationFactory = $notificationFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->dayPartFactory = $dayPartFactory;
    }

    /**
     * @SWG\Get(
     *  path="/displayvenue",
     *  summary="Get Display Venues",
     *  tags={"displayVenue"},
     *  operationId="displayVenueSearch",
     *  @SWG\Response(
     *      response=200,
     *      description="a successful response",
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayVenue(Request $request, Response $response)
    {
        if (!file_exists(PROJECT_ROOT . '/openooh/specification.json')) {
            throw new GeneralException(__('OpenOOH specification missing'));
        }

        $content = file_get_contents(PROJECT_ROOT . '/openooh/specification.json');
        $data = json_decode($content, true);

        $taxonomy = [];
        $i = 0;
        foreach ($data['openooh_venue_taxonomy']['specification']['categories'] as $categories) {
            $taxonomy[$i]['venueId'] = $categories['enumeration_id'];
            $taxonomy[$i]['venueName'] = $categories['name'];

            $i++;
            foreach ($categories['children'] as $children) {
                $taxonomy[$i]['venueId'] = $children['enumeration_id'];
                $taxonomy[$i]['venueName'] = $categories['name'] . ' -> ' . $children['name'];
                $i++;

                if (isset($children['children'])) {
                    foreach ($children['children'] as $grandchildren) {
                        $taxonomy[$i]['venueId'] = $grandchildren['enumeration_id'] ;
                        $taxonomy[$i]['venueName'] = $categories['name'] . ' -> ' . $children['name'] .  ' -> ' . $grandchildren['name'] ;
                        $i++;
                    }
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = count($taxonomy);
        $this->getState()->setData($taxonomy);

        return $this->render($request, $response);
    }

    /**
     * Include display page template page based on sub page selected
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function displayPage(Request $request, Response $response)
    {
        // Build a list of display profiles
        $displayProfiles = $this->displayProfileFactory->query();
        $displayProfiles[] = ['displayProfileId' => -1, 'name' => __('Default')];

        // Call to render the template
        $this->getState()->template = 'display-page';

        $mapConfig = [
            'setArea' => [
                'lat' => $this->getConfig()->getSetting('DEFAULT_LAT'),
                'long' => $this->getConfig()->getSetting('DEFAULT_LONG'),
                'zoom' => 7
            ]
        ];

        $this->getState()->setData([
            'mapConfig' => $mapConfig,
            'displayProfiles' => $displayProfiles
        ]);

        return $this->render($request, $response);
    }

    /**
     * Display Management Page for an Individual Display
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function displayManage(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkViewable($display)) {
            throw new AccessDeniedException();
        }

        // Zero out some variables
        $dependencies = [];
        $layouts = [];
        $widgets = [];
        $widgetData = [];
        $media = [];
        $totalCount = 0;
        $completeCount = 0;
        $totalSize = 0;
        $completeSize = 0;

        // Show 4 widgets
        // Dependencies
        $sql = '
          SELECT `requiredfile`.*
              FROM `requiredfile`
           WHERE `requiredfile`.displayId = :displayId 
            AND `requiredfile`.type = :type
          ORDER BY fileType, path
        ';

        foreach ($this->store->select($sql, ['displayId' => $id, 'type' => 'P']) as $row) {
            $totalSize = $totalSize + $row['size'];
            $totalCount++;
            
            if (intval($row['complete']) === 1) {
                $completeSize = $completeSize + $row['size'];
                $completeCount = $completeCount + 1;
            }

            $row = $this->getSanitizer($row);

            $dependencies[] = [
                'path' => $row->getString('path'),
                'fileType' => $row->getString('fileType'),
                'bytesRequested' => $row->getInt('bytesRequested'),
                'complete' => $row->getInt('complete'),
            ];
        }

        // Layouts
        $sql = '
          SELECT layoutId, layout, `requiredfile`.*
              FROM `layout`
                INNER JOIN `requiredfile`
                ON `requiredfile`.itemId = `layout`.layoutId
           WHERE `requiredfile`.displayId = :displayId 
            AND `requiredfile`.type = :type
          ORDER BY layout
        ';

        foreach ($this->store->select($sql, ['displayId' => $id, 'type' => 'L']) as $row) {
            $rf = $this->requiredFileFactory->getByDisplayAndLayout($id, $row['layoutId']);

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

        foreach ($this->store->select($sql, ['displayId' => $id, 'type' => 'M']) as $row) {
            $rf = $this->requiredFileFactory->getByDisplayAndMedia($id, $row['mediaId']);

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
          SELECT `widget`.`type` AS widgetType,
                `widgetoption`.`value` AS widgetName,
                `widget`.`widgetId`,
                `requiredfile`.*
              FROM `widget`
                INNER JOIN `requiredfile`
                ON `requiredfile`.itemId = `widget`.widgetId
                LEFT OUTER JOIN `widgetoption`
                ON `widgetoption`.widgetId = `widget`.widgetId
                  AND `widgetoption`.option = \'name\'
           WHERE `requiredfile`.`displayId` = :displayId 
            AND `requiredfile`.`type` IN (\'W\', \'D\')
          ORDER BY `widgetoption`.value, `widget`.type, `widget`.widgetId
        ';

        foreach ($this->store->select($sql, ['displayId' => $id]) as $row) {
            $row = $this->getSanitizer($row);
            $entry = [];
            $entry['type'] = $row->getString('widgetType');
            $entry['widgetName'] = $row->getString('widgetName');
            $entry['widgetType'] = $row->getString('widgetType');

            if ($row->getString('type') === 'W') {
                $rf = $this->requiredFileFactory->getByDisplayAndWidget($id, $row->getInt('widgetId'));

                $totalCount++;

                if ($rf->complete) {
                    $completeCount = $completeCount + 1;
                }

                $widgets[] = array_merge($entry, $rf->toArray());
            } else {
                $entry['widgetId'] = $row->getInt('widgetId');
                $entry['bytesRequested'] = $row->getInt('bytesRequested');
                $widgetData[] = $entry;
            }
        }

        // Widget for file status
        // Decide what our units are going to be, based on the size
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');
        $base = (int)floor(log($totalSize) / log(1024));

        if ($base < 0) {
            $base = 0;
        }

        $units = $suffixes[$base] ?? '';
        $this->getLog()->debug(sprintf('Base for size is %d and suffix is %s', $base, $units));


        // Call to render the template
        $this->getState()->template = 'display-page-manage';
        $this->getState()->setData([
            'requiredFiles' => [],
            'display' => $display,
            'timeAgo' => Carbon::createFromTimestamp($display->lastAccessed)->diffForHumans(),
            'errorSearch' => http_build_query([
                'displayId' => $display->displayId,
                'type' => 'ERROR',
                'fromDt' => Carbon::now()->subHours(12)->format(DateFormatHelper::getSystemFormat()),
                'toDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
            ]),
            'inventory' => [
                'dependencies' => $dependencies,
                'layouts' => $layouts,
                'media' => $media,
                'widgets' => $widgets,
                'widgetData' => $widgetData,
            ],
            'status' => [
                'units' => $units,
                'countComplete' => $completeCount,
                'countRemaining' => $totalCount - $completeCount,
                'sizeComplete' => round((double)$completeSize / (pow(1024, $base)), 2),
                'sizeRemaining' => round((double)($totalSize - $completeSize) / (pow(1024, $base)), 2),
            ],
            'defaults' => [
                'fromDate' => Carbon::now()->startOfMonth()->format(DateFormatHelper::getSystemFormat()),
                'fromDateOneDay' => Carbon::now()->subDay()->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->endOfMonth()->format(DateFormatHelper::getSystemFormat())
            ]
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get display filters
     * @param SanitizerInterface $parsedQueryParams
     * @return array
     */
    public function getFilters(SanitizerInterface $parsedQueryParams): array
    {
        return [
            'displayId' => $parsedQueryParams->getInt('displayId'),
            'display' => $parsedQueryParams->getString('display'),
            'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
            'macAddress' => $parsedQueryParams->getString('macAddress'),
            'license' => $parsedQueryParams->getString('hardwareKey'),
            'displayGroupId' => $parsedQueryParams->getInt('displayGroupId'),
            'clientVersion' => $parsedQueryParams->getString('clientVersion'),
            'clientType' => $parsedQueryParams->getString('clientType'),
            'clientCode' => $parsedQueryParams->getString('clientCode'),
            'customId' => $parsedQueryParams->getString('customId'),
            'authorised' => $parsedQueryParams->getInt('authorised'),
            'displayProfileId' => $parsedQueryParams->getInt('displayProfileId'),
            'tags' => $parsedQueryParams->getString('tags'),
            'exactTags' => $parsedQueryParams->getCheckbox('exactTags'),
            'showTags' => true,
            'clientAddress' => $parsedQueryParams->getString('clientAddress'),
            'mediaInventoryStatus' => $parsedQueryParams->getInt('mediaInventoryStatus'),
            'loggedIn' => $parsedQueryParams->getInt('loggedIn'),
            'lastAccessed' => $parsedQueryParams->getDate('lastAccessed')?->format('U'),
            'displayGroupIdMembers' => $parsedQueryParams->getInt('displayGroupIdMembers'),
            'orientation' => $parsedQueryParams->getString('orientation'),
            'commercialLicence' => $parsedQueryParams->getInt('commercialLicence'),
            'folderId' => $parsedQueryParams->getInt('folderId'),
            'logicalOperator' => $parsedQueryParams->getString('logicalOperator'),
            'logicalOperatorName' => $parsedQueryParams->getString('logicalOperatorName'),
            'bounds' => $parsedQueryParams->getString('bounds'),
            'syncGroupId' => $parsedQueryParams->getInt('syncGroupId'),
            'syncGroupIdMembers' => $parsedQueryParams->getInt('syncGroupIdMembers'),
            'xmrRegistered' => $parsedQueryParams->getInt('xmrRegistered'),
            'isPlayerSupported' => $parsedQueryParams->getInt('isPlayerSupported'),
            'displayGroupIds' => $parsedQueryParams->getIntArray('displayGroupIds'),
        ];
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
     *      name="tags",
     *      in="query",
     *      description="Filter by tags",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="exactTags",
     *      in="query",
     *      description="A flag indicating whether to treat the tags filter as an exact match",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="logicalOperator",
     *      in="query",
     *      description="When filtering by multiple Tags, which logical operator should be used? AND|OR",
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
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="query",
     *      description="Filter by Folder ID",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *       name="xmrRegistered",
     *       in="query",
     *       description="Filter by whether XMR is registed (1 or 0)",
     *       type="integer",
     *       required=false
     *    ),
     *  @SWG\Parameter(
     *       name="isPlayerSupported",
     *       in="query",
     *       description="Filter by whether the player is supported (1 or 0)",
     *       type="integer",
     *       required=false
     *    ),
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
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());
        // Embed?
        $embed = ($parsedQueryParams->getString('embed') != null)
            ? explode(',', $parsedQueryParams->getString('embed'))
            : [];

        $filter = $this->getFilters($parsedQueryParams);

        // Get a list of displays
        $displays = $this->displayFactory->query(
            $this->gridRenderSort($parsedQueryParams),
            $this->gridRenderFilter($filter, $parsedQueryParams)
        );

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

            $display->setUnmatchedProperty(
                'bandwidthLimitFormatted',
                ByteFormatter::format($display->bandwidthLimit * 1024)
            );

            // Current layout from cache
            $display->getCurrentLayoutId($this->pool, $this->layoutFactory);

            if ($this->isApi($request)) {
                $display->lastAccessed =
                    Carbon::createFromTimestamp($display->lastAccessed)->format(DateFormatHelper::getSystemFormat());
                $display->auditingUntil = ($display->auditingUntil == 0)
                    ? 0
                    : Carbon::createFromTimestamp($display->auditingUntil)->format(DateFormatHelper::getSystemFormat());
                $display->storageAvailableSpace = ByteFormatter::format($display->storageAvailableSpace);
                $display->storageTotalSpace = ByteFormatter::format($display->storageTotalSpace);
                continue;
            }

            // use try and catch here to cover scenario
            // when there is no default display profile set for any of the existing display types.
            $displayProfileName = '';
            try {
                $defaultDisplayProfile = $this->displayProfileFactory->getDefaultByType($display->clientType);
                $displayProfileName = $defaultDisplayProfile->name;
            } catch (NotFoundException $e) {
                $this->getLog()->debug('No default Display Profile set for Display type ' . $display->clientType);
            }

            // Add in the display profile information
            $display->setUnmatchedProperty(
                'displayProfile',
                (!array_key_exists($display->displayProfileId, $displayProfiles))
                    ? $displayProfileName . __(' (Default)')
                    : $displayProfiles[$display->displayProfileId]
            );

            $display->includeProperty('buttons');

            // Format the storage available / total space
            $display->setUnmatchedProperty(
                'storageAvailableSpaceFormatted',
                ByteFormatter::format($display->storageAvailableSpace)
            );
            $display->setUnmatchedProperty(
                'storageTotalSpaceFormatted',
                ByteFormatter::format($display->storageTotalSpace)
            );
            $display->setUnmatchedProperty(
                'storagePercentage',
                ($display->storageTotalSpace == 0)
                    ? 0
                    : round($display->storageAvailableSpace / $display->storageTotalSpace * 100.0, 2)
            );

            // Set some text for the display status
            $display->setUnmatchedProperty('statusDescription', match ($display->mediaInventoryStatus) {
                1 => __('Display is up to date'),
                2 => __('Display is downloading new files'),
                3 => __('Display is out of date but has not yet checked in with the server'),
                default => __('Unknown Display Status'),
            });

            // Commercial Licence
            $display->setUnmatchedProperty('commercialLicenceDescription', match ($display->commercialLicence) {
                1 => __('Display is fully licensed'),
                2 => __('Display is on a trial licence'),
                default => __('Display is not licensed'),
            });

            if ($display->clientCode < 400) {
                $commercialLicenceDescription = $display->getUnmatchedProperty('commercialLicenceDescription');
                $commercialLicenceDescription .= ' ('
                    . __('The status will be updated with each Commercial Licence check') . ')';
                $display->setUnmatchedProperty('commercialLicenceDescription', $commercialLicenceDescription);
            }

            // Thumbnail
            $display->setUnmatchedProperty('thumbnail', '');
            // If we aren't logged in, and we are showThumbnail == 2, then show a circle
            if (file_exists($this->getConfig()->getSetting('LIBRARY_LOCATION') . 'screenshots/'
                . $display->displayId . '_screenshot.jpg')) {
                $display->setUnmatchedProperty(
                    'thumbnail',
                    $this->urlFor($request, 'display.screenShot', [
                        'id' => $display->displayId
                    ]) . '?' . Random::generateString()
                );
            }

            $display->setUnmatchedProperty(
                'teamViewerLink',
                (!empty($display->teamViewerSerial))
                    ? 'https://start.teamviewer.com/' . $display->teamViewerSerial
                    : ''
            );
            $display->setUnmatchedProperty(
                'webkeyLink',
                (!empty($display->webkeySerial))
                    ? 'https://device.webkeyapp.com/phone?publicid=' . $display->webkeySerial
                    : ''
            );

            // Is a transfer to another CMS in progress?
            $display->setUnmatchedProperty('isCmsTransferInProgress', (!empty($display->newCmsAddress)));

            // Edit and Delete buttons first
            if ($this->getUser()->featureEnabled('displays.modify')
                && $this->getUser()->checkEditable($display)
            ) {
                // Manage
                $display->buttons[] = [
                    'id' => 'display_button_manage',
                    'url' => $this->urlFor($request, 'display.manage', ['id' => $display->displayId]),
                    'text' => __('Manage'),
                    'external' => true
                ];

                $display->buttons[] = ['divider' => true];

                // Edit
                $display->buttons[] = [
                    'id' => 'display_button_edit',
                    'url' => $this->urlFor($request, 'display.edit.form', ['id' => $display->displayId]),
                    'text' => __('Edit')
                ];
            }

            // Delete
            if ($this->getUser()->featureEnabled('displays.modify')
                && $this->getUser()->checkDeleteable($display)
            ) {
                $deleteButton = [
                    'id' => 'display_button_delete',
                    'url' => $this->urlFor($request, 'display.delete.form', ['id' => $display->displayId]),
                    'text' => __('Delete')
                ];

                // We only include this in dev mode, because users have complained that it is too powerful a feature
                // to have in the core product.
                if (Environment::isDevMode()) {
                    $deleteButton['multi-select'] = true;
                    $deleteButton['dataAttributes'] = [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'display.delete',
                                ['id' => $display->displayId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'display_button_delete'],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'rowtitle', 'value' => $display->display]
                    ];
                }

                $display->buttons[] = $deleteButton;
            }

            if ($this->getUser()->featureEnabled('displays.modify')
                && ($this->getUser()->checkEditable($display) || $this->getUser()->checkDeleteable($display))
            ) {
                $display->buttons[] = ['divider' => true];
            }

            if ($this->getUser()->featureEnabled('displays.modify')
                && $this->getUser()->checkEditable($display)
            ) {
                // Authorise
                $display->buttons[] = [
                    'id' => 'display_button_authorise',
                    'url' => $this->urlFor($request, 'display.authorise.form', ['id' => $display->displayId]),
                    'text' => __('Authorise'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'auto-submit', 'value' => true],
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'display.authorise',
                                ['id' => $display->displayId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'put'],
                        ['name' => 'id', 'value' => 'display_button_authorise'],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'text', 'value' => __('Toggle Authorise')],
                        ['name' => 'rowtitle', 'value' => $display->display]
                    ]
                ];

                // Default Layout
                $display->buttons[] = [
                    'id' => 'display_button_defaultlayout',
                    'url' => $this->urlFor($request, 'display.defaultlayout.form', ['id' => $display->displayId]),
                    'text' => __('Default Layout'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'display.defaultlayout',
                                ['id' => $display->displayId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'put'],
                        ['name' => 'id', 'value' => 'display_button_defaultlayout'],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'text', 'value' => __('Set Default Layout')],
                        ['name' => 'rowtitle', 'value' => $display->display],
                        ['name' => 'form-callback', 'value' => 'setDefaultMultiSelectFormOpen']
                    ]
                ];

                if ($this->getUser()->featureEnabled('folder.view')) {
                    // Select Folder
                    $display->buttons[] = [
                        'id' => 'displaygroup_button_selectfolder',
                        'url' => $this->urlFor(
                            $request,
                            'displayGroup.selectfolder.form',
                            ['id' => $display->displayGroupId]
                        ),
                        'text' => __('Select Folder'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'displayGroup.selectfolder',
                                    ['id' => $display->displayGroupId]
                                )
                            ],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'displaygroup_button_selectfolder'],
                            ['name' => 'sort-group', 'value' => 2],
                            ['name' => 'text', 'value' => __('Move to Folder')],
                            ['name' => 'rowtitle', 'value' => $display->display],
                            ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                        ]
                    ];
                }

                if (in_array($display->clientType, ['android', 'lg', 'sssp', 'chromeOS'])) {
                    $display->buttons[] = array(
                        'id' => 'display_button_checkLicence',
                        'url' => $this->urlFor($request, 'display.licencecheck.form', ['id' => $display->displayId]),
                        'text' => __('Check Licence'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            ['name' => 'auto-submit', 'value' => true],
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'display.licencecheck',
                                    ['id' => $display->displayId]
                                )
                            ],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'display_button_checkLicence'],
                            ['name' => 'sort-group', 'value' => 2],
                            ['name' => 'text', 'value' => __('Check Licence')],
                            ['name' => 'rowtitle', 'value' => $display->display]
                        ]
                    );
                }

                $display->buttons[] = ['divider' => true];
            }

            // Schedule
            if ($this->getUser()->featureEnabled('schedule.add')
                && ($this->getUser()->checkEditable($display)
                    || $this->getConfig()->getSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 1)
            ) {
                $display->buttons[] = array(
                    'id' => 'display_button_schedule',
                    'url' => $this->urlFor(
                        $request,
                        'schedule.add.form',
                        ['id' => $display->displayGroupId, 'from' => 'DisplayGroup']
                    ),
                    'text' => __('Schedule')
                );
            }

            // Check if limited view access is allowed
            if (($this->getUser()->featureEnabled('displays.modify') && $this->getUser()->checkEditable($display))
                || $this->getUser()->featureEnabled('displays.limitedView')
            ) {
                if ($this->getUser()->checkEditable($display)) {
                    if ($this->getUser()->featureEnabled('layout.view')) {
                        $display->buttons[] = [
                            'id' => 'display_button_layouts_jump',
                            'linkType' => '_self',
                            'external' => true,
                            'url' => $this->urlFor($request, 'layout.view')
                                . '?activeDisplayGroupId=' . $display->displayGroupId,
                            'text' => __('Jump to Scheduled Layouts')
                        ];
                    }

                    // File Associations
                    $display->buttons[] = array(
                        'id' => 'displaygroup_button_fileassociations',
                        'url' => $this->urlFor($request, 'displayGroup.media.form', ['id' => $display->displayGroupId]),
                        'text' => __('Assign Files')
                    );

                    // Layout Assignments
                    $display->buttons[] = array(
                        'id' => 'displaygroup_button_layout_associations',
                        'url' => $this->urlFor($request, 'displayGroup.layout.form', ['id' => $display->displayGroupId]),
                        'text' => __('Assign Layouts')
                    );
                }

                // Screen Shot
                $display->buttons[] = [
                    'id' => 'display_button_requestScreenShot',
                    'url' => $this->urlFor($request, 'display.screenshot.form', ['id' => $display->displayId]),
                    'text' => __('Request Screen Shot'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'auto-submit', 'value' => true],
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'display.requestscreenshot',
                                ['id' => $display->displayId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'put'],
                        ['name' => 'sort-group', 'value' => 3],
                        ['name' => 'id', 'value' => 'display_button_requestScreenShot'],
                        ['name' => 'text', 'value' => __('Request Screen Shot')],
                        ['name' => 'rowtitle', 'value' => $display->display]
                    ]
                ];

                // Collect Now
                $display->buttons[] = [
                    'id' => 'display_button_collectNow',
                    'url' => $this->urlFor(
                        $request,
                        'displayGroup.collectNow.form',
                        ['id' => $display->displayGroupId]
                    ),
                    'text' => __('Collect Now'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'auto-submit', 'value' => true],
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'displayGroup.action.collectNow',
                                ['id' => $display->displayGroupId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'sort-group', 'value' => 3],
                        ['name' => 'id', 'value' => 'display_button_collectNow'],
                        ['name' => 'text', 'value' => __('Collect Now')],
                        ['name' => 'rowtitle', 'value' => $display->display]
                    ]
                ];

                if ($this->getUser()->checkEditable($display)) {
                    // Trigger webhook
                    $display->buttons[] = [
                        'id' => 'display_button_trigger_webhook',
                        'url' => $this->urlFor(
                            $request,
                            'displayGroup.trigger.webhook.form',
                            ['id' => $display->displayGroupId]
                        ),
                        'text' => __('Trigger a web hook'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'displayGroup.action.trigger.webhook',
                                    ['id' => $display->displayGroupId]
                                )
                            ],
                            ['name' => 'commit-method', 'value' => 'post'],
                            ['name' => 'id', 'value' => 'display_button_trigger_webhook'],
                            ['name' => 'sort-group', 'value' => 3],
                            ['name' => 'text', 'value' => __('Trigger a web hook')],
                            ['name' => 'rowtitle', 'value' => $display->display],
                            ['name' => 'form-callback', 'value' => 'triggerWebhookMultiSelectFormOpen']
                        ]
                    ];

                    if ($this->getUser()->isSuperAdmin()) {
                        $display->buttons[] = [
                            'id' => 'display_button_purgeAll',
                            'url' => $this->urlFor($request, 'display.purge.all.form', ['id' => $display->displayId]),
                            'text' => __('Purge All')
                        ];
                    }

                    $display->buttons[] = ['divider' => true];
                }
            }

            if ($this->getUser()->featureEnabled('displays.modify')
                && $this->getUser()->checkPermissionsModifyable($display)
            ) {
                // Display Groups
                $display->buttons[] = array(
                    'id' => 'display_button_group_membership',
                    'url' => $this->urlFor($request, 'display.membership.form', ['id' => $display->displayId]),
                    'text' => __('Display Groups')
                );

                // Permissions
                $display->buttons[] = [
                    'id' => 'display_button_group_permissions',
                    'url' => $this->urlFor(
                        $request,
                        'user.permissions.form',
                        ['entity' => 'DisplayGroup', 'id' => $display->displayGroupId]
                    ),
                    'text' => __('Share'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'user.permissions.multi',
                                ['entity' => 'DisplayGroup', 'id' => $display->displayGroupId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'display_button_group_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $display->display],
                        ['name' => 'sort-group', 'value' => 4],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        [
                            'name' => 'custom-handler-url',
                            'value' => $this->urlFor(
                                $request,
                                'user.permissions.multi.form',
                                ['entity' => 'DisplayGroup']
                            )
                        ],
                        ['name' => 'content-id-name', 'value' => 'displayGroupId']
                    ]
                ];
            }

            if ($this->getUser()->featureEnabled('displays.modify')
                && $this->getUser()->checkEditable($display)
            ) {
                if ($this->getUser()->checkPermissionsModifyable($display)) {
                    $display->buttons[] = ['divider' => true];
                }

                // Wake On LAN
                $display->buttons[] = array(
                    'id' => 'display_button_wol',
                    'url' => $this->urlFor($request, 'display.wol.form', ['id' => $display->displayId]),
                    'text' => __('Wake on LAN')
                );

                $display->buttons[] = [
                    'id' => 'displaygroup_button_command',
                    'url' => $this->urlFor($request, 'displayGroup.command.form', ['id' => $display->displayGroupId]),
                    'text' => __('Send Command'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'displayGroup.action.command',
                                ['id' => $display->displayGroupId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'displaygroup_button_command'],
                        ['name' => 'text', 'value' => __('Send Command')],
                        ['name' => 'sort-group', 'value' => 3],
                        ['name' => 'rowtitle', 'value' => $display->display],
                        ['name' => 'form-callback', 'value' => 'sendCommandMultiSelectFormOpen']
                    ]
                ];

                $display->buttons[] = ['divider' => true];

                $display->buttons[] = [
                    'id' => 'display_button_move_cms',
                    'url' => $this->urlFor($request, 'display.moveCms.form', ['id' => $display->displayId]),
                    'text' => __('Transfer to another CMS'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'display.moveCms',
                                ['id' => $display->displayId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'put'],
                        ['name' => 'id', 'value' => 'display_button_move_cms'],
                        ['name' => 'text', 'value' => __('Transfer to another CMS')],
                        ['name' => 'sort-group', 'value' => 5],
                        ['name' => 'rowtitle', 'value' => $display->display],
                        ['name' => 'form-callback', 'value' => 'setMoveCmsMultiSelectFormOpen']
                    ]
                ];

                $display->buttons[] = [
                    'multi-select' => true,
                    'multiSelectOnly' => true, // Show button only on multi-select menu
                    'id' => 'display_button_set_bandwidth',
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'display.setBandwidthLimitMultiple'
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'display_button_set_bandwidth'],
                        ['name' => 'text', 'value' => __('Set Bandwidth')],
                        ['name' => 'rowtitle', 'value' => $display->display],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        [
                            'name' => 'custom-handler-url',
                            'value' => $this->urlFor($request, 'display.setBandwidthLimitMultiple.form')
                        ],
                        ['name' => 'content-id-name', 'value' => 'displayId']
                    ]
                ];

                if ($display->getUnmatchedProperty('isCmsTransferInProgress', false)) {
                    $display->buttons[] = [
                        'id' => 'display_button_move_cancel',
                        'url' => $this->urlFor($request, 'display.moveCmsCancel.form', ['id' => $display->displayId]),
                        'text' => __('Cancel CMS Transfer'),
                    ];
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->displayFactory->countLast();
        $this->getState()->setData($displays);

        return $this->render($request, $response);
    }

    /**
     * Displays on map
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     */
    public function displayMap(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = $this->getFilters($parsedQueryParams);

        // Get a list of displays
        $displays = $this->displayFactory->query(null, $filter);
        $results = [];
        $status = [
            '1' => __('Up to date'),
            '2' => __('Downloading'),
            '3' => __('Out of date')
        ];

        // Get all Display Profiles
        $displayProfiles = [];
        foreach ($this->displayProfileFactory->query() as $displayProfile) {
            $displayProfiles[$displayProfile->displayProfileId] = $displayProfile->name;
        }

        foreach ($displays as $display) {
            // use try and catch here to cover scenario when there is no default display profile set for any of the existing display types.
            $displayProfileName = '';
            try {
                $defaultDisplayProfile = $this->displayProfileFactory->getDefaultByType($display->clientType);
                $displayProfileName = $defaultDisplayProfile->name;
            } catch (NotFoundException $e) {
                $this->getLog()->debug('No default Display Profile set for Display type ' . $display->clientType);
            }

            // Add in the display profile information
            $display->setUnmatchedProperty(
                'displayProfile',
                (!array_key_exists($display->displayProfileId, $displayProfiles))
                    ? $displayProfileName . __(' (Default)')
                    : $displayProfiles[$display->displayProfileId]
            );

            $properties = [
                'display' => $display->display,
                'status' => $display->mediaInventoryStatus ? $status[$display->mediaInventoryStatus] : __('Unknown'),
                'mediaInventoryStatus' => $display->mediaInventoryStatus,
                'orientation' => ucwords($display->orientation ?: __('Unknown')),
                'displayId' => $display->getId(),
                'licensed' => $display->licensed,
                'loggedIn' => $display->loggedIn,
                'displayProfile' => $display->getUnmatchedProperty('displayProfile'),
                'resolution' => $display->resolution,
                'lastAccessed' => $display->lastAccessed,
            ];

            if (file_exists($this->getConfig()->getSetting('LIBRARY_LOCATION') . 'screenshots/' . $display->displayId . '_screenshot.jpg')) {
                $properties['thumbnail'] = $this->urlFor($request, 'display.screenShot', ['id' => $display->displayId]) . '?' . Random::generateString();
            }

            $longitude = ($display->longitude) ?: $this->getConfig()->getSetting('DEFAULT_LONG');
            $latitude =  ($display->latitude) ?: $this->getConfig()->getSetting('DEFAULT_LAT');

            $geo = new Point([(double)$longitude, (double)$latitude]);

            $results[] = new Feature($geo, $properties);
        }

        return $response->withJson(new FeatureCollection($results));
    }

    /**
     * Edit Display Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function editForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id, true);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        // We have permission - load
        $display->load();

        // Dates
        $auditingUntilIso = !empty($display->auditingUntil)
            ? Carbon::createFromTimestamp($display->auditingUntil)->format(DateFormatHelper::getSystemFormat())
            : null;
        $display->setUnmatchedProperty('auditingUntilIso', $auditingUntilIso);

        // display profile dates
        $displayProfile = $display->getDisplayProfile();

        // Get the settings from the profile
        $profile = $display->getSettings();
        $displayTypes = $this->displayTypeFactory->query();

        $elevateLogsUntil = $displayProfile->getSetting('elevateLogsUntil');
        $elevateLogsUntilIso = !empty($elevateLogsUntil)
            ? Carbon::createFromTimestamp($elevateLogsUntil)->format(DateFormatHelper::getSystemFormat())
            : null;
        $displayProfile->setUnmatchedProperty('elevateLogsUntilIso', $elevateLogsUntilIso);

        // Get a list of timezones
        $timeZones = [];
        foreach (DateFormatHelper::timezoneList() as $key => $value) {
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
        $dayPartId = $display->getSetting('dayPartId', null, ['displayOnly' => true]);
        $profileDayPartId = $display->getDisplayProfile()->getSetting('dayPartId');
        $dayparts = [];

        // Get the Player Version for this display profile type
        if ($versionId !== null) {
            try {
                $playerVersions[] = $this->playerVersionFactory->getById($versionId);
            } catch (NotFoundException $e) {
                $this->getLog()->debug('Unknown versionId set on Display Profile for displayId ' . $display->displayId);
            }
        }

        if ($versionId !== $profileVersionId && $profileVersionId !== null) {
            try {
                $playerVersions[] = $this->playerVersionFactory->getById($profileVersionId);
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

        if ($dayPartId !== $profileDayPartId && $profileDayPartId !== null) {
            try {
                $dayparts[] = $this->dayPartFactory->getById($profileDayPartId);
            } catch (NotFoundException $e) {
                $this->getLog()->debug('Unknown dayPartId set on Display Profile for displayId ' . $display->displayId);
            }
        }

        // A list of languages
        // Build an array of supported languages
        $languages = [];
        $localeDir = PROJECT_ROOT . '/locale';
        foreach (array_map('basename', glob($localeDir . '/*.mo')) as $lang) {
            // Trim the .mo off the end
            $lang = str_replace('.mo', '', $lang);
            $languages[] = ['id' => $lang, 'value' => $lang];
        }

        $this->getState()->template = 'display-form-edit';
        $this->getState()->setData([
            'display' => $display,
            'displayProfile' => $displayProfile,
            'lockOptions' => json_decode($display->getDisplayProfile()->getSetting('lockOptions', '[]'), true),
            'layouts' => $layouts,
            'profiles' => $this->displayProfileFactory->query(null, array('type' => $display->clientType)),
            'settings' => $profile,
            'timeZones' => $timeZones,
            'displayLockName' => ($this->getConfig()->getSetting('DISPLAY_LOCK_NAME_TO_DEVICENAME') == 1),
            'versions' => $playerVersions,
            'displayTypes' => $displayTypes,
            'dayParts' => $dayparts,
            'languages' => $languages,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function deleteForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($display)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'display-form-delete';
        $this->getState()->setData([
            'display' => $display,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Display Edit
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
     *      name="languages",
     *      in="formData",
     *      description="An array of languages supported in this display location",
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
     *      name="displayTypeId",
     *      in="formData",
     *      description="The Display Type ID of this Display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="screenSize",
     *      in="formData",
     *      description="The screen size of this Display",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="venueId",
     *      in="formData",
     *      description="The Venue ID of this Display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="address",
     *      in="formData",
     *      description="The Location Address of this Display",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isMobile",
     *      in="formData",
     *      description="Is this Display mobile?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isOutdoor",
     *      in="formData",
     *      description="Is this Display Outdoor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="costPerPlay",
     *      in="formData",
     *      description="The Cost Per Play of this Display",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="impressionsPerPlay",
     *      in="formData",
     *      description="The Impressions Per Play of this Display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="customId",
     *      in="formData",
     *      description="The custom ID (an Id of any external system) of this Display",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref1",
     *      in="formData",
     *      description="Reference 1",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref2",
     *      in="formData",
     *      description="Reference 2",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref3",
     *      in="formData",
     *      description="Reference 3",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref4",
     *      in="formData",
     *      description="Reference 4",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref5",
     *      in="formData",
     *      description="Reference 5",
     *      type="string",
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
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Display")
     *  )
     * )
     */
    function edit(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id, true);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        // Update properties
        if ($this->getConfig()->getSetting('DISPLAY_LOCK_NAME_TO_DEVICENAME') == 0) {
            $display->display = $sanitizedParams->getString('display');
        }

        $display->load();

        $display->description = $sanitizedParams->getString('description');
        $display->displayTypeId = $sanitizedParams->getInt('displayTypeId');
        $display->venueId = $sanitizedParams->getInt('venueId');
        $display->address = $sanitizedParams->getString('address');
        $display->isMobile = $sanitizedParams->getCheckbox('isMobile');
        $languages = $sanitizedParams->getArray('languages');
        if (empty($languages)) {
            $display->languages = null;
        } else {
            $display->languages = implode(',', $languages);
        }
        $display->screenSize = $sanitizedParams->getInt('screenSize');
        $display->auditingUntil = $sanitizedParams->getDate('auditingUntil')?->format('U');
        $display->defaultLayoutId = $sanitizedParams->getInt('defaultLayoutId');
        $display->licensed = $sanitizedParams->getInt('licensed');
        $display->license = $sanitizedParams->getString('license');
        $display->incSchedule = $sanitizedParams->getInt('incSchedule');
        $display->emailAlert = $sanitizedParams->getInt('emailAlert');
        $display->alertTimeout = $sanitizedParams->getCheckbox('alertTimeout');
        $display->wakeOnLanEnabled = $sanitizedParams->getCheckbox('wakeOnLanEnabled');
        $display->wakeOnLanTime = $sanitizedParams->getString('wakeOnLanTime');
        $display->broadCastAddress = $sanitizedParams->getString('broadCastAddress');
        $display->secureOn = $sanitizedParams->getString('secureOn');
        $display->cidr = $sanitizedParams->getString('cidr');
        $display->latitude = $sanitizedParams->getDouble('latitude');
        $display->longitude = $sanitizedParams->getDouble('longitude');
        $display->timeZone = $sanitizedParams->getString('timeZone');
        $display->displayProfileId = $sanitizedParams->getInt('displayProfileId');
        $display->bandwidthLimit = $sanitizedParams->getInt('bandwidthLimit', ['default' => 0]);
        $display->teamViewerSerial = $sanitizedParams->getString('teamViewerSerial');
        $display->webkeySerial = $sanitizedParams->getString('webkeySerial');
        $display->folderId = $sanitizedParams->getInt('folderId', ['default' => $display->folderId]);
        $display->isOutdoor = $sanitizedParams->getCheckbox('isOutdoor');
        $display->costPerPlay = $sanitizedParams->getDouble('costPerPlay');
        $display->impressionsPerPlay = $sanitizedParams->getDouble('impressionsPerPlay');
        $display->customId = $sanitizedParams->getString('customId');
        $display->ref1 = $sanitizedParams->getString('ref1');
        $display->ref2 = $sanitizedParams->getString('ref2');
        $display->ref3 = $sanitizedParams->getString('ref3');
        $display->ref4 = $sanitizedParams->getString('ref4');
        $display->ref5 = $sanitizedParams->getString('ref5');

        // Get the display profile and use that to pull in any overrides
        // start with an empty config
        $display->overrideConfig = $this->editConfigFields(
            $display->getDisplayProfile(),
            $sanitizedParams,
            [],
            $display
        );

        // Tags are stored on the displaygroup, we're just passing through here
        if ($this->getUser()->featureEnabled('tag.tagging')) {
            if (is_array($sanitizedParams->getParam('tags'))) {
                $tags = $this->tagFactory->tagsFromJson($sanitizedParams->getArray('tags'));
            } else {
                $tags = $this->tagFactory->tagsFromString($sanitizedParams->getString('tags'));
            }

            $display->tags = $tags;
        }

        // Should we invalidate this display?
        if ($display->hasPropertyChanged('defaultLayoutId')) {
            $display->notify();
        } elseif ($sanitizedParams->getCheckbox('clearCachedData', ['default' => 1]) == 1) {
            // Remove the cache if the display licenced state has changed
            $this->pool->deleteItem($display->getCacheKey());
        }

        // Should we rekey?
        if ($sanitizedParams->getCheckbox('rekeyXmr', ['default' => 0]) == 1) {
            // Queue the rekey action first (before we clear the channel and key)
            $this->playerAction->sendAction($display, new RekeyAction());

            // Clear the config.
            $display->xmrChannel = null;
            $display->xmrPubKey = null;
        }

        $display->save();

        if ($this->isApi($request)) {
            $display->lastAccessed = Carbon::createFromTimestamp($display->lastAccessed)
                ->format(DateFormatHelper::getSystemFormat());
            $display->auditingUntil = ($display->auditingUntil == 0)
                    ? 0
                    : Carbon::createFromTimestamp($display->auditingUntil)->format(DateFormatHelper::getSystemFormat());
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $display->display),
            'id' => $display->displayId,
            'data' => $display
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete a display
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
    function delete(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($display)) {
            throw new AccessDeniedException();
        }

        if ($display->isLead()) {
            throw new InvalidArgumentException(
                __('Cannot delete a Lead Display of a Sync Group'),
            );
        }

        $display->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $display->display),
            'id' => $display->displayId,
            'data' => $display
        ]);

        return $this->render($request, $response);
    }

    /**
     * Member of Display Groups Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function membershipForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        // Groups we are assigned to
        $groupsAssigned = $this->displayGroupFactory->getByDisplayId($display->displayId);

        $this->getState()->template = 'display-form-membership';
        $this->getState()->setData([
            'display' => $display,
            'extra' => [
                'displayGroupsAssigned' => $groupsAssigned
            ],
        ]);

        return $this->render($request, $response);
    }

    /**
     * Set Bandwidth to one or more displays
     * @param Request $request
     * @param Response $response
     * @param $ids
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function setBandwidthLimitMultipleForm(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check if the array of ids is passed
        if ($sanitizedParams->getString('ids') == '') {
            throw new InvalidArgumentException(__('The array of ids is empty!'));
        }

        // Get array of ids
        $ids = $sanitizedParams->getString('ids');

        $this->getState()->template = 'display-form-set-bandwidth';
        $this->getState()->setData([
            'ids' => $ids,
        ]);

        return $this->render($request, $response);
    }

        /**
     * Set Bandwidth to one or more displays
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function setBandwidthLimitMultiple(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Get array of ids
        $ids = ($sanitizedParams->getString('ids') != '') ? explode(',', $sanitizedParams->getString('ids')) : [];
        $bandwidthLimit = intval($sanitizedParams->getString('bandwidthLimit'));
        $bandwidthLimitUnits = $sanitizedParams->getString('bandwidthLimitUnits');

        // Check if the array of ids is passed
        if (count($ids) == 0) {
            throw new InvalidArgumentException(__('The array of ids is empty!'));
        }

        // Check if the bandwidth value has something
        if ($bandwidthLimit == '') {
            throw new InvalidArgumentException(__('The array of ids is empty!'));
        }

        // convert bandwidth to kb based on form units
        if ($bandwidthLimitUnits == 'mb') {
            $bandwidthLimit = $bandwidthLimit * 1024;
        } elseif ($bandwidthLimitUnits == 'gb') {
            $bandwidthLimit = $bandwidthLimit * 1024 * 1024;
        }

        // display group ids to be updated
        $displayGroupIds = [];

        foreach ($ids as $id) {
            // get display
            $display = $this->displayFactory->getById($id);

            // check if the display is accessible by user
            if (!$this->getUser()->checkViewable($display)) {
                throw new AccessDeniedException();
            }

            $displayGroupIds[] = $display->displayGroupId;
        }

        // update bandwidth limit to the array of ids
        $this->displayGroupFactory->setBandwidth($bandwidthLimit, $displayGroupIds);

        // Audit Log message
        $this->getLog()->audit('DisplayGroup', 0, 'Batch update of bandwidth limit for ' . count($displayGroupIds) . ' items', [
            'bandwidthLimit' => $bandwidthLimit,
            'displayGroupIds' => $displayGroupIds
        ]);

        // Return
        $this->getState()->hydrate([
            'httpCode' => 204,
            'message' => __('Displays Updated')
        ]);

        return $this->render($request, $response);
    }


    /**
     * Assign Display to Display Groups
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function assignDisplayGroup(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        // Go through each ID to assign
        foreach ($sanitizedParams->getIntArray('displayGroupId', ['default' => []]) as $displayGroupId) {
            $displayGroup = $this->displayGroupFactory->getById($displayGroupId);
            $displayGroup->load();
            $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);

            if (!$this->getUser()->checkEditable($displayGroup)) {
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));
            }

            $displayGroup->assignDisplay($display);
            $displayGroup->save(['validate' => false]);
        }

        // Have we been provided with unassign id's as well?
        foreach ($sanitizedParams->getIntArray('unassignDisplayGroupId', ['default' => []]) as $displayGroupId) {
            $displayGroup = $this->displayGroupFactory->getById($displayGroupId);
            $displayGroup->load();
            $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);

            if (!$this->getUser()->checkEditable($displayGroup)) {
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));
            }

            $displayGroup->unassignDisplay($display);
            $displayGroup->save(['validate' => false]);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('%s assigned to Display Groups'), $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Output a screen shot
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function screenShot(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        // Allow limited view access
        if (!$this->getUser()->checkViewable($display) && !$this->getUser()->featureEnabled('displays.limitedView')) {
            throw new AccessDeniedException();
        }

        // The request will output its own content, disable framework
        $this->setNoOutput(true);

        // Output an image if present, otherwise not found image.
        $file = 'screenshots/' . $id . '_screenshot.jpg';

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
                ->rectangle(0, 0, 110, 15, function ($draw) {
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
        return $this->render($request, $response);
    }

    /**
     * Request ScreenShot form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function requestScreenShotForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        // Allow limited view access
        if (!$this->getUser()->checkViewable($display) && !$this->getUser()->featureEnabled('displays.limitedView')) {
            throw new AccessDeniedException();
        }

        // Work out the next collection time based on the last accessed date/time and the collection interval
        if ($display->lastAccessed == 0) {
            $nextCollect = __('once it has connected for the first time');
        } else {
            $collectionInterval = $display->getSetting('collectInterval', 300);
            $nextCollect = Carbon::createFromTimestamp($display->lastAccessed)
                ->addSeconds($collectionInterval)
                ->diffForHumans();
        }

        $this->getState()->template = 'display-form-request-screenshot';
        $this->getState()->autoSubmit = $this->getAutoSubmit('displayRequestScreenshotForm');
        $this->getState()->setData([
            'display' => $display,
            'nextCollect' => $nextCollect,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Request ScreenShot
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
    public function requestScreenShot(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        // Allow limited view access
        if (!$this->getUser()->checkViewable($display) && !$this->getUser()->featureEnabled('displays.limitedView')) {
            throw new AccessDeniedException();
        }

        $display->screenShotRequested = 1;
        $display->save(['validate' => false, 'audit' => false]);

        $xmrPubAddress = $this->getConfig()->getSetting('XMR_PUB_ADDRESS');

        if (!empty($display->xmrChannel) && !empty($xmrPubAddress) && $xmrPubAddress !== 'DISABLED') {
            $this->playerAction->sendAction($display, new ScreenShotAction());
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Request sent for %s'), $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Form for wake on Lan
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function wakeOnLanForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkViewable($display)) {
            throw new AccessDeniedException();
        }

        if ($display->macAddress == '') {
            throw new InvalidArgumentException(
                __('This display has no mac address recorded against it yet. Make sure the display is running.'),
                'macAddress'
            );
        }

        $this->getState()->template = 'display-form-wakeonlan';
        $this->getState()->setData([
            'display' => $display,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Wake this display using a WOL command
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
    public function wakeOnLan(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkViewable($display)) {
            throw new AccessDeniedException();
        }

        if ($display->macAddress == '' || $display->broadCastAddress == '') {
            throw new InvalidArgumentException(
                __('This display has no mac address recorded against it yet. Make sure the display is running.')
            );
        }

        $this->getLog()->notice(
            'About to send WOL packet to '
            . $display->broadCastAddress . ' with Mac Address ' . $display->macAddress
        );

        WakeOnLan::TransmitWakeOnLan(
            $display->macAddress,
            $display->secureOn,
            $display->broadCastAddress,
            $display->cidr,
            '9',
            $this->getLog()
        );

        $display->lastWakeOnLanCommandSent = Carbon::now()->format('U');
        $display->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Wake on Lan sent for %s'), $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Validate the display list
     * @param array[Display] $displays
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
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
            } else {
                $timeoutToTestAgainst = $globalTimeout;
            }

            // Store the time out to test against
            $timeOut = $display->lastAccessed + $timeoutToTestAgainst;

            // If the last time we accessed is less than now minus the time out
            if ($timeOut < Carbon::now()->format('U')) {
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
                    // eventTypeId 1 is for Display up/down events.
                    $event->eventTypeId = 1;
                    $event->save();
                }

                $dayPartId = $display->getSetting('dayPartId', null, ['displayOverride' => true]);
                $operatingHours = true;

                if ($dayPartId !== null) {
                    try {
                        $dayPart = $this->dayPartFactory->getById($dayPartId);

                        $startTimeArray = explode(':', $dayPart->startTime);
                        $startTime = Carbon::now()->setTime(intval($startTimeArray[0]), intval($startTimeArray[1]));

                        $endTimeArray = explode(':', $dayPart->endTime);
                        $endTime = Carbon::now()->setTime(intval($endTimeArray[0]), intval($endTimeArray[1]));

                        $now = Carbon::now();

                        // exceptions
                        foreach ($dayPart->exceptions as $exception) {
                            // check if we are on exception day and if so override the start and endtime accordingly
                            if ($exception['day'] == Carbon::now()->format('D')) {
                                $exceptionsStartTime = explode(':', $exception['start']);
                                $startTime = Carbon::now()->setTime(
                                    intval($exceptionsStartTime[0]),
                                    intval($exceptionsStartTime[1])
                                );

                                $exceptionsEndTime = explode(':', $exception['end']);
                                $endTime = Carbon::now()->setTime(
                                    intval($exceptionsEndTime[0]),
                                    intval($exceptionsEndTime[1])
                                );
                            }
                        }

                        // check if we are inside the operating hours for this display -
                        // we use that flag to decide if we need to create a notification and send an email.
                        if (($now >= $startTime && $now <= $endTime)) {
                            $operatingHours = true;
                        } else {
                            $operatingHours = false;
                        }
                    } catch (NotFoundException $e) {
                        $this->getLog()->debug(
                            'Unknown dayPartId set on Display Profile for displayId ' . $display->displayId
                        );
                    }
                }

                // Should we create a notification
                if ($emailAlerts && $display->emailAlert == 1 && ($displayOffline || $alwaysAlert)) {
                    // Alerts enabled for this display
                    // Display just gone offline, or always alert
                    // Fields for email

                    // for displays without dayPartId set, this is always true,
                    // otherwise we check if we are inside the operating hours set for this display
                    if ($operatingHours) {
                        $subject = sprintf(__('Alert for Display %s'), $display->display);
                        $body = sprintf(
                            __('Display ID %d is offline since %s.'),
                            $display->displayId,
                            Carbon::createFromTimestamp($display->lastAccessed)
                                ->format(DateFormatHelper::getSystemFormat())
                        );

                        // Add to system
                        $notification = $this->notificationFactory->createSystemNotification(
                            $subject,
                            $body,
                            Carbon::now(),
                            'display'
                        );

                        // Add in any displayNotificationGroups, with permissions
                        foreach ($this->userGroupFactory->getDisplayNotificationGroups($display->displayGroupId) as $group) {
                            $notification->assignUserGroup($group);
                        }

                        $notification->save();
                    } else {
                        $this->getLog()->info('Not sending email down alert for Display - ' . $display->display . ' we are outside of its operating hours');
                    }
                } elseif ($displayOffline) {
                    $this->getLog()->info('Not sending an email for offline display - emailAlert = ' . $display->emailAlert . ', alwaysAlert = ' . $alwaysAlert);
                }
            }
        }
    }

    /**
     * Show the authorise form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function authoriseForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'display-form-authorise';
        $this->getState()->autoSubmit = $this->getAutoSubmit('displayAuthoriseForm');
        $this->getState()->setData([
            'display' => $display
        ]);

        return $this->render($request, $response);
    }

    /**
     * Toggle Authorise on this Display
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
     */
    public function toggleAuthorise(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        $display->licensed = ($display->licensed == 1) ? 0 : 1;
        $display->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Authorised set to %d for %s'), $display->licensed, $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function defaultLayoutForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

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

        return $this->render($request, $response);
    }

    /**
     * Set the Default Layout for this Display
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
     */
    public function setDefaultLayout(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        $layoutId = $this->getSanitizer($request->getParams())->getInt('layoutId');

        $layout = $this->layoutFactory->getById($layoutId);

        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        $display->defaultLayoutId = $layoutId;
        $display->save(['validate' => false]);
        if ($display->hasPropertyChanged('defaultLayoutId')) {
            $display->notify();
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Default Layout with name %s set for %s'), $layout->layout, $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function moveCmsForm(Request $request, Response $response, $id)
    {
        if ($this->getUser()->twoFactorTypeId != 2) {
            throw new AccessDeniedException('This action requires active Google Authenticator Two Factor authentication');
        }

        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'display-form-moveCms';
        $this->getState()->setData([
            'display' => $display,
            'newCmsAddress' => $display->newCmsAddress,
            'newCmsKey' => $display->newCmsKey
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \RobThree\Auth\TwoFactorAuthException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function moveCms(Request $request, Response $response, $id)
    {
        if ($this->getUser()->twoFactorTypeId != 2) {
            throw new AccessDeniedException('This action requires active Google Authenticator Two Factor authentication');
        }

        $display = $this->displayFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

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

        $authenticationCode = $sanitizedParams->getString('twoFactorCode');

        $tfa = new TwoFactorAuth($issuer);
        $result = $tfa->verifyCode($this->getUser()->twoFactorSecret, $authenticationCode, 3);

        if ($result) {
            // get the new CMS Address and Key from the form.
            $newCmsAddress = $sanitizedParams->getString('newCmsAddress');
            $newCmsKey = $sanitizedParams->getString('newCmsKey');

            // validate the URL
            if (!v::url()->notEmpty()->validate(urldecode($newCmsAddress)) || !filter_var($newCmsAddress, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException(__('Provided CMS URL is invalid'), 'newCmsUrl');
            }

            if (!v::stringType()->length(1, 1000)->validate($newCmsAddress)) {
                throw new InvalidArgumentException(__('New CMS URL can have maximum of 1000 characters'), 'newCmsUrl');
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

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     */
    public function moveCmsCancelForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkEditable($display)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'display-form-moveCmsCancel';
        $this->getState()->setData([
            'display' => $display
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @param $id
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function moveCmsCancel(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

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

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function addViaCodeForm(Request $request, Response $response)
    {
        $this->getState()->template = 'display-form-addViaCode';

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function addViaCode(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $user_code = $sanitizedParams->getString('user_code');
        $cmsAddress = (new HttpsDetect())->getUrl();
        $cmsKey = $this->getConfig()->getSetting('SERVER_KEY');

        if ($user_code == '') {
            throw new InvalidArgumentException(__('Code cannot be empty'), 'code');
        }

        $guzzle = new Client();

        try {
            // When the valid code is submitted, it will be sent along with CMS Address and Key to Authentication Service maintained by Xibo Signage Ltd.
            // The Player will then call the service with the same code to retrieve the CMS details.
            // On success, the details will be removed from the Authentication Service.
            $guzzleRequest = $guzzle->request(
                'POST',
                'https://auth.signlicence.co.uk/addDetails',
                $this->getConfig()->getGuzzleProxy([
                    'form_params' => [
                        'user_code' => $user_code,
                        'cmsAddress' => $cmsAddress,
                        'cmsKey' => $cmsKey,
                    ]
                ])
            );

            $data = json_decode($guzzleRequest->getBody(), true);

            $this->getState()->hydrate([
                'message' => $data['message']
            ]);
        } catch (\Exception $e) {
            $this->getLog()->debug($e->getMessage());
            throw new InvalidArgumentException(__('Provided user_code does not exist'), 'user_code');
        }

        return $this->render($request, $response);
    }

    /**
     * Check commercial licence form
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function checkLicenceForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkViewable($display)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'display-form-licence-check';
        $this->getState()->autoSubmit = $this->getAutoSubmit('displayLicenceCheckForm');
        $this->getState()->setData([
            'display' => $display
        ]);

        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function checkLicence(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkViewable($display)) {
            throw new AccessDeniedException();
        }

        if (empty($display->xmrChannel)) {
            throw new InvalidArgumentException(__('XMR is not configured for this Display'), 'xmrChannel');
        }

        $this->playerAction->sendAction($display, new LicenceCheckAction());

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Request sent for %s'), $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/display/status/{id}",
     *  operationId="displayStatus",
     *  tags={"display"},
     *  summary="Display Status",
     *  description="Get the display status window for this Display.",
     *  @SWG\Parameter(
     *      name="id",
     *      in="path",
     *      description="Display Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(type="string")
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param int $id displayId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\AccessDeniedException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function statusWindow(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkViewable($display)) {
            throw new AccessDeniedException();
        }

        return $response->withJson($display->getStatusWindow($this->pool));
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function purgeAllForm(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkViewable($display) || !$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'display-form-purge-all';
        $this->getState()->setData([
            'display' => $display
        ]);

        return $this->render($request, $response);
    }


    /**
     * Purge All
     *
     * @SWG\Put(
     *  summary="Purge All",
     *  path="/display/purgeAll/{displayId}",
     *  operationId="displayPurgeAll",
     *  tags={"display"},
     *  description="Ask this Player to purge all Media from its local storage and request fresh files from CMS.",
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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function purgeAll(Request $request, Response $response, $id)
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkViewable($display) || !$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        if (empty($display->xmrChannel)) {
            throw new InvalidArgumentException(__('XMR is not configured for this Display'), 'xmrChannel');
        }

        $this->playerAction->sendAction($display, new PurgeAllAction());

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Request sent for %s'), $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }
}
