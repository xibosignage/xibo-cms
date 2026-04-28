<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
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

    private StorageServiceInterface $store;
    private PoolInterface $pool;
    private PlayerActionServiceInterface $playerAction;
    private DayPartFactory $dayPartFactory;
    private DisplayFactory $displayFactory;
    private DisplayGroupFactory $displayGroupFactory;
    private LayoutFactory $layoutFactory;
    private DisplayProfileFactory $displayProfileFactory;
    private DisplayTypeFactory $displayTypeFactory;
    private DisplayEventFactory $displayEventFactory;
    private PlayerVersionFactory $playerVersionFactory;
    private RequiredFileFactory $requiredFileFactory;
    private TagFactory $tagFactory;
    private NotificationFactory $notificationFactory;
    private UserGroupFactory $userGroupFactory;

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
    public function __construct(
        StorageServiceInterface $store,
        PoolInterface $pool,
        PlayerActionServiceInterface $playerAction,
        DisplayFactory $displayFactory,
        DisplayGroupFactory $displayGroupFactory,
        DisplayTypeFactory $displayTypeFactory,
        LayoutFactory $layoutFactory,
        DisplayProfileFactory $displayProfileFactory,
        DisplayEventFactory $displayEventFactory,
        RequiredFileFactory $requiredFileFactory,
        TagFactory $tagFactory,
        NotificationFactory $notificationFactory,
        UserGroupFactory $userGroupFactory,
        PlayerVersionFactory $playerVersionFactory,
        DayPartFactory $dayPartFactory
    ) {
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

    #[OA\Get(
        path: '/displayvenue',
        operationId: 'displayVenueSearch',
        summary: 'Get Display Venues',
        tags: ['displayVenue']
    )]
    #[OA\Response(
        response: 200,
        description: 'a successful response',
        headers: [
            new OA\Header(
                header: 'X-Total-Count',
                description: 'The total number of records',
                schema: new OA\Schema(type: 'integer')
            )
        ],
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayVenue(Request $request, Response $response): Response|ResponseInterface
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
                        $taxonomy[$i]['venueName'] =
                            $categories['name'] . ' -> ' . $children['name'] .  ' -> ' . $grandchildren['name'] ;
                        $i++;
                    }
                }
            }
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', count($taxonomy))
            ->withJson($taxonomy);
    }

    /**
     * Get the list of supported locale languages
     * @param Response $response
     * @return Response
     */
    public function getLocaleLanguages(Response $response): Response
    {
        $languages = [];
        $localeDir = PROJECT_ROOT . '/locale';
        foreach (array_map('basename', glob($localeDir . '/*.mo') ?: []) as $lang) {
            $lang = str_replace('.mo', '', $lang);
            $languages[] = ['id' => $lang, 'value' => $lang];
        }
        return $response->withJson($languages);
    }

    /**
     * Display Management Page for an Individual Display
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayManage(Request $request, Response $response, int $id): Response|ResponseInterface
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
            'keyword' => $parsedQueryParams->getString('keyword'),
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

    #[OA\Get(
        path: '/display',
        operationId: 'displaySearch',
        description: 'Search Displays for this User',
        summary: 'Display Search',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'Filter by Display Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by Display Name, ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'displayGroupId',
        description: 'Filter by DisplayGroup Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'display',
        description: 'Filter by Display Name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'tags',
        description: 'Filter by tags',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'exactTags',
        description: 'A flag indicating whether to treat the tags filter as an exact match',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'logicalOperator',
        description: 'When filtering by multiple Tags, which logical operator should be used? AND|OR',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'macAddress',
        description: 'Filter by Mac Address',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'hardwareKey',
        description: 'Filter by Hardware Key',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'clientVersion',
        description: 'Filter by Client Version',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'clientType',
        description: 'Filter by Client Type',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'clientCode',
        description: 'Filter by Client Code',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'embed',
        description: 'Embed related data, namely displaygroups. A comma separated list of child objects to embed.', // phpcs:ignore
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'authorised',
        description: 'Filter by authorised flag',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'displayProfileId',
        description: 'Filter by Display Profile',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'mediaInventoryStatus',
        description: 'Filter by Display Status ( 1 - up to date, 2 - downloading, 3 - Out of date)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'loggedIn',
        description: 'Filter by Logged In flag',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'lastAccessed',
        description: 'Filter by Display Last Accessed date, expects date in Y-m-d H:i:s format',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'folderId',
        description: 'Filter by Folder ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'xmrRegistered',
        description: 'Filter by whether XMR is registed (1 or 0)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'isPlayerSupported',
        description: 'Filter by whether the player is supported (1 or 0)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'displayId',
                'display',
                'clientType',
                'clientCode',
                'clientVersion',
                'mediaInventoryStatus',
                'clientAddress',
                'licensed',
                'loggedIn',
                'deviceName',
                'address',
                'storageAvailableSpace',
                'storageTotalSpace',
                'description',
                'orientation',
                'resolution',
                'defaultLayout',
                'incSchedule',
                'emailAlert',
                'lastAccessed',
                'macAddress',
                'timeZone',
                'languages',
                'latitude',
                'longitude',
                'screenShotRequested',
                'bandwidthLimit',
                'lastCommandSuccess',
                'commercialLicence',
                'groupsWithPermissions',
                'screenSize',
                'isMobile',
                'isOutdoor',
                'ref1',
                'ref2',
                'ref3',
                'ref4',
                'ref5',
                'customId',
                'costPerPlay',
                'impressionsPerPlay',
                'createdDt',
                'modifiedDt',
                'countFaults',
                'osVersion',
                'osSdk',
                'manufacturer',
                'brand',
                'model',
                'cmsTransfer',
                'xmrRegistered',
            ]
        )
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Sort direction',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'X-Total-Count',
                description: 'The total number of records',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Display')
        )
    )]
    /**
     * Grid of Displays
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = $this->getFilters($parsedQueryParams);

        // Get a list of displays
        $displays = $this->displayFactory->query(
            $this->gridRenderSort($parsedQueryParams, $this->isJson($request)),
            $this->gridRenderFilter($filter, $parsedQueryParams)
        );

        // validate displays so we get a realistic view of the table
        $this->validateDisplays($displays);

        foreach ($displays as $display) {
            $this->decorateDisplayProperties($parsedQueryParams, $display, $request);
        }

        if ($this->isJson($request) || $this->isApi($request)) {
            return $response
                ->withStatus(200)
                ->withHeader('X-Total-Count', $this->displayFactory->countLast())
                ->withJson($displays);
        }

        // TODO remove when no longer needed.
        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->displayFactory->countLast();
        $this->getState()->setData($displays);

        return $this->render($request, $response);
    }

    #[OA\Get(
        path: '/display/{displayId}',
        operationId: 'DisplaySearchById',
        description: 'Get the Display object specified by the provided displayId',
        summary: 'Display search by ID',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'Numeric ID of the Display to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Display')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $params = $this->getSanitizer($request->getQueryParams());
        $display = $this->displayFactory->getById($id, false, false);

        $this->decorateDisplayProperties($params, $display, $request);

        return $response
            ->withStatus(200)
            ->withJson($display);
    }

    /**
     * Displays on map
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws NotFoundException
     */
    public function displayMap(Request $request, Response $response): Response|ResponseInterface
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

            if (file_exists(
                $this->getConfig()->getSetting('LIBRARY_LOCATION') .
                'screenshots/' . $display->displayId . '_screenshot.jpg'
            )) {
                $properties['thumbnail'] = $this->urlFor(
                    $request,
                    'display.screenShot',
                    ['id' => $display->displayId]
                ) . '?' . Random::generateString();
            }

            $longitude = ($display->longitude) ?: $this->getConfig()->getSetting('DEFAULT_LONG');
            $latitude =  ($display->latitude) ?: $this->getConfig()->getSetting('DEFAULT_LAT');

            $geo = new Point([(double)$longitude, (double)$latitude]);

            $results[] = new Feature($geo, $properties);
        }

        return $response->withJson(new FeatureCollection($results));
    }

    #[OA\Put(
        path: '/display/{displayId}',
        operationId: 'displayEdit',
        description: 'Edit a Display',
        summary: 'Display Edit',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'The Display ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: [
                    'display',
                    'defaultLayoutId',
                    'licensed',
                    'license',
                    'incSchedule',
                    'emailAlert',
                    'wakeOnLanEnabled'
                ],
                properties: [
                    new OA\Property(property: 'display', description: 'The Display Name', type: 'string'),
                    new OA\Property(property: 'description', description: 'A description of the Display', type: 'string'), // phpcs:ignore
                    new OA\Property(
                        property: 'tags',
                        description: 'A comma separated list of tags for this item',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'auditingUntil',
                        description: 'A date this Display records auditing information until.',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'defaultLayoutId',
                        description: 'A Layout ID representing the Default Layout for this Display.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'licensed',
                        description: 'Flag indicating whether this display is licensed.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'license',
                        description: 'The hardwareKey to use as the licence key for this Display',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'incSchedule',
                        description: 'Flag indicating whether the Default Layout should be included in the Schedule', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'emailAlert',
                        description: 'Flag indicating whether the Display generates up/down email alerts.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'alertTimeout',
                        description: 'How long in seconds should this display wait before alerting when it hasn\'t connected. Override for the collection interval.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'wakeOnLanEnabled',
                        description: 'Flag indicating if Wake On LAN is enabled for this Display',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'wakeOnLanTime',
                        description: 'A h:i string representing the time that the Display should receive its Wake on LAN command', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'broadCastAddress',
                        description: 'The BroadCast Address for this Display - used by Wake On LAN',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'secureOn',
                        description: 'The secure on configuration for this Display',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'cidr',
                        description: 'The CIDR configuration for this Display',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'latitude',
                        description: 'The Latitude of this Display',
                        type: 'number'
                    ),
                    new OA\Property(
                        property: 'longitude',
                        description: 'The Longitude of this Display',
                        type: 'number'
                    ),
                    new OA\Property(
                        property: 'timeZone',
                        description: 'The timezone for this display, or empty to use the CMS timezone',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'languages',
                        description: 'An array of languages supported in this display location',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'displayProfileId',
                        description: 'The Display Settings Profile ID',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'displayTypeId',
                        description: 'The Display Type ID of this Display',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'screenSize',
                        description: 'The screen size of this Display',
                        type: 'number'
                    ),
                    new OA\Property(
                        property: 'venueId',
                        description: 'The Venue ID of this Display',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'address',
                        description: 'The Location Address of this Display',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'isMobile',
                        description: 'Is this Display mobile?',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'isOutdoor',
                        description: 'Is this Display Outdoor?',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'costPerPlay',
                        description: 'The Cost Per Play of this Display',
                        type: 'number'
                    ),
                    new OA\Property(
                        property: 'impressionsPerPlay',
                        description: 'The Impressions Per Play of this Display',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'customId',
                        description: 'The custom ID (an Id of any external system) of this Display',
                        type: 'string'
                    ),
                    new OA\Property(property: 'ref1', description: 'Reference 1', type: 'string'),
                    new OA\Property(property: 'ref2', description: 'Reference 2', type: 'string'),
                    new OA\Property(property: 'ref3', description: 'Reference 3', type: 'string'),
                    new OA\Property(property: 'ref4', description: 'Reference 4', type: 'string'),
                    new OA\Property(property: 'ref5', description: 'Reference 5', type: 'string'),
                    new OA\Property(
                        property: 'clearCachedData',
                        description: 'Clear all Cached data for this display',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'rekeyXmr',
                        description: 'Clear the cached XMR configuration and send a rekey',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'teamViewerSerial',
                        description: 'The TeamViewer serial number for this Display, if applicable',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'webkeySerial',
                        description: 'The Webkey serial number for this Display, if applicable',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'folderId',
                        description: 'Folder ID to which this object should be assigned to',
                        type: 'integer'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Display')
    )]
    /**
     * Display Edit
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function edit(Request $request, Response $response, int $id): Response|ResponseInterface
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

        // Wake on Lan
        if (defined('ACCOUNT_ID')) {
            // WOL is not allowed on a Xibo Cloud CMS
            // Force disable, but leave the other settings as they are.
            $display->wakeOnLanEnabled = 0;
        } else {
            $display->wakeOnLanEnabled = $sanitizedParams->getCheckbox('wakeOnLanEnabled');
            $display->wakeOnLanTime = $sanitizedParams->getString('wakeOnLanTime');
            $display->broadCastAddress = $sanitizedParams->getString('broadCastAddress');
            $display->secureOn = $sanitizedParams->getString('secureOn');
            $display->cidr = $sanitizedParams->getString('cidr');
        }

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

    #[OA\Delete(
        path: '/display/{displayId}',
        operationId: 'displayDelete',
        description: 'Delete a Display',
        summary: 'Display Delete',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'The Display ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Delete a display
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function delete(Request $request, Response $response, int $id): Response|ResponseInterface
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
        return $response->withStatus(204);
    }

        /**
     * Set Bandwidth to one or more displays
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function setBandwidthLimitMultiple(Request $request, Response $response): Response|ResponseInterface
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
        $this->getLog()->audit(
            'DisplayGroup',
            0,
            'Batch update of bandwidth limit for ' . count($displayGroupIds) . ' items',
            ['bandwidthLimit' => $bandwidthLimit, 'displayGroupIds' => $displayGroupIds]
        );

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
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function assignDisplayGroup(Request $request, Response $response, $id): Response|ResponseInterface
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

        // Queue display to check for cache updates
        $display->notify();

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
     * @return ResponseInterface|Response
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
        $library = $this->getConfig()->getSetting('LIBRARY_LOCATION');
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
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Disable any buffering to prevent OOM errors.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $response->write($img->encode());
        $response = $response->withHeader('Content-Type', $img->mime());
        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/display/requestscreenshot/{displayId}',
        operationId: 'displayRequestScreenshot',
        description: 'Notify the display that the CMS would like a screen shot to be sent.',
        summary: 'Request Screen Shot',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'The Display ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Display')
    )]
    /**
     * Request ScreenShot
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function requestScreenShot(Request $request, Response $response, $id): Response
    {
        $display = $this->displayFactory->getById($id);

        // Allow limited view access
        if (!$this->getUser()->checkViewable($display) && !$this->getUser()->featureEnabled('displays.limitedView')) {
            throw new AccessDeniedException();
        }

        $display->screenShotRequested = 1;
        $display->save(['validate' => false, 'audit' => false]);

        if (!empty($display->xmrChannel)) {
            $this->playerAction->sendAction($display, new ScreenShotAction());
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Request sent for %s'), $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }

    #[OA\Post(
        path: '/display/wol/{displayId}',
        operationId: 'displayWakeOnLan',
        description: 'Send a Wake On LAN packet to this Display',
        summary: 'Issue WOL',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'The Display ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Wake this display using a WOL command
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function wakeOnLan(Request $request, Response $response, $id): Response|ResponseInterface
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
     * @param \Xibo\Entity\Display[] $displays
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function validateDisplays(array $displays): void
    {
        // Get the global time out (overrides the alert time out on the display if 0)
        $globalTimeout = $this->getConfig()->getSetting('MAINTENANCE_ALERT_TOUT') * 60;
        $emailAlerts = ($this->getConfig()->getSetting('MAINTENANCE_EMAIL_ALERTS') == 1);
        $alwaysAlert = ($this->getConfig()->getSetting('MAINTENANCE_ALWAYS_ALERT') == 1);

        foreach ($displays as $display) {
            $timeOut = $this->getDisplayTimeout($globalTimeout, $display);

            // If the last time we accessed is less than now minus the timeout
            if ($timeOut < Carbon::now()->format('U')) {
                $this->getLog()->debug('Timed out display. Last Accessed: '
                    . date('Y-m-d h:i:s', $display->lastAccessed) .
                    '. Time out: ' . date('Y-m-d h:i:s', $timeOut));

                $this->updateTimedOutDisplay($display, $emailAlerts, $alwaysAlert);
            }
        }
    }

    #[OA\Put(
        path: '/display/authorise/{displayId}',
        operationId: 'displayToggleAuthorise',
        description: 'Toggle authorised for the Display.',
        summary: 'Toggle authorised',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'The Display ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Toggle Authorise on this Display
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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

    #[OA\Put(
        path: '/display/defaultlayout/{displayId}',
        operationId: 'displayDefaultLayout',
        description: 'Set the default Layout on this Display',
        summary: 'Set Default Layout',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'The Display ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['layoutId'],
                properties: [
                    new OA\Property(property: 'layoutId', description: 'The Layout ID', type: 'integer')
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Set the Default Layout for this Display
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \RobThree\Auth\TwoFactorAuthException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function moveCms(Request $request, Response $response, $id): Response|ResponseInterface
    {
        if ($this->getUser()->twoFactorTypeId != 2) {
            throw new AccessDeniedException(
                'This action requires active Google Authenticator Two Factor authentication'
            );
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
            if (!v::url()->notEmpty()->validate(urldecode($newCmsAddress)) ||
                !filter_var($newCmsAddress, FILTER_VALIDATE_URL)
            ) {
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
     * @return ResponseInterface|Response
     * @param $id
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function moveCmsCancel(Request $request, Response $response, $id): Response|ResponseInterface
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
     * @return ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function addViaCode(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $user_code = $sanitizedParams->getString('user_code');
        $cmsAddress = (new HttpsDetect())->getBaseUrl($request);
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
            throw new InvalidArgumentException(
                __(
                    'The code provided does not match.
                     Please double-check the code shown on the device you are trying to connect.'
                ),
                'user_code'
            );
        }

        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/display/licenceCheck/{displayId}',
        operationId: 'displayLicenceCheck',
        description: 'Ask this Player to check its Commercial Licence',
        summary: 'Licence Check',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'The Display ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Check commercial licence
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
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
            throw new InvalidArgumentException(
                __('XMR is not configured for this Display'),
                'xmrChannel'
            );
        }

        $this->playerAction->sendAction($display, new LicenceCheckAction());

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Request sent for %s'), $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }

    #[OA\Get(
        path: '/display/status/{id}',
        operationId: 'displayStatus',
        description: 'Get the display status window for this Display.',
        summary: 'Display Status',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'Display Id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'string')
        )
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id displayId
     * @return ResponseInterface|Response
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

    #[OA\Put(
        path: '/display/purgeAll/{displayId}',
        operationId: 'displayPurgeAll',
        description: 'Ask this Player to purge all Media from its local storage and request fresh files from CMS.', // phpcs:ignore
        summary: 'Purge All',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'displayId',
        description: 'The Display ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Purge All
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function purgeAll(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $display = $this->displayFactory->getById($id);

        if (!$this->getUser()->checkViewable($display) || !$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        if (empty($display->xmrChannel)) {
            throw new InvalidArgumentException(
                __('XMR is not configured for this Display'),
                'xmrChannel'
            );
        }

        $this->playerAction->sendAction($display, new PurgeAllAction());

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Request sent for %s'), $display->display),
            'id' => $display->displayId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get the display timeout value
     * @param $globalTimeout
     * @param $display
     * @return float
     */
    private function getDisplayTimeout($globalTimeout, $display): float
    {
        // Should we test against the collection interval or the preset alert timeout?
        $timeoutToTestAgainst = $globalTimeout;

        if ($display->alertTimeout == 0 && $display->clientType != '') {
            $timeoutToTestAgainst = ((double)$display->getSetting('collectInterval', $globalTimeout)) * 1.1;
        }

        // Store the timeout to test against
        return $display->lastAccessed + $timeoutToTestAgainst;
    }

    /**
     * Update the displays that have timed out
     * @param $display
     * @param $emailAlerts
     * @param $alwaysAlert
     * @return void
     */
    private function updateTimedOutDisplay($display, $emailAlerts, $alwaysAlert): void
    {
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

        $dayPartId = $display->getSetting('dayPartId');
        $isWithinOperatingHours = true;

        if ($dayPartId !== null) {
            $isWithinOperatingHours = $this->getIsWithinOperatingHours($dayPartId, $display);
        }

        // Should we create a notification
        if ($emailAlerts && $display->emailAlert == 1 && ($displayOffline || $alwaysAlert)) {
            $this->createSystemNotification($isWithinOperatingHours, $display);
        } elseif ($displayOffline) {
            $this->getLog()->info('Not sending an email for offline display - emailAlert = '
                . $display->emailAlert . ', alwaysAlert = ' . $alwaysAlert);
        }
    }

    /**
     * Check if display is within operating hours
     * @param $dayPartId
     * @param $display
     * @return bool
     */
    private function getIsWithinOperatingHours($dayPartId, $display): bool
    {
        $isWithinOperatingHours = true;

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
            $isWithinOperatingHours = ($now >= $startTime && $now <= $endTime);
        } catch (NotFoundException) {
            $this->getLog()->debug(
                'Unknown dayPartId set on Display Profile for displayId ' . $display->displayId
            );
        }

        return $isWithinOperatingHours;
    }

    /**
     * Creates system or email notifications
     * @param $isWithinOperatingHours
     * @param $display
     * @return void
     */
    private function createSystemNotification($isWithinOperatingHours, $display): void
    {
        // Alerts enabled for this display
        // Display just gone offline, or always alert
        // Fields for email

        // for displays without dayPartId set, this is always true,
        // otherwise we check if we are inside the operating hours set for this display
        if ($isWithinOperatingHours) {
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
            foreach ($this->userGroupFactory
                         ->getDisplayNotificationGroups($display->displayGroupId) as $group) {
                $notification->assignUserGroup($group);
            }

            $notification->save();
        } else {
            $this->getLog()->info('Not sending email down alert for Display - ' . $display->display
                . ' we are outside of its operating hours');
        }
    }

    /**
     * @param SanitizerInterface $parsedQueryParams
     * @param \Xibo\Entity\Display $display
     * @param Request $request
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    private function decorateDisplayProperties(
        SanitizerInterface $parsedQueryParams,
        \Xibo\Entity\Display $display,
        Request $request,
    ): void {
        // Get all Display Profiles
        $displayProfiles = [];
        foreach ($this->displayProfileFactory->query() as $displayProfile) {
            $displayProfiles[$displayProfile->displayProfileId] = $displayProfile->name;
        }

        // Embed?
        $embed = ($parsedQueryParams->getString('embed') != null)
            ? explode(',', $parsedQueryParams->getString('embed'))
            : [];

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

        $display->lastAccessed =
            Carbon::createFromTimestamp($display->lastAccessed)->format(DateFormatHelper::getSystemFormat());
        $display->auditingUntil = ($display->auditingUntil == 0)
            ? 0
            :Carbon::createFromTimestamp($display->auditingUntil)->format(DateFormatHelper::getSystemFormat());

        // use try and catch here to cover scenario
        // when there is no default display profile set for any of the existing display types.
        $displayProfileName = '';
        try {
            $defaultDisplayProfile = $this->displayProfileFactory->getDefaultByType($display->clientType);
            $displayProfileName = $defaultDisplayProfile->name;
        } catch (NotFoundException) {
            $this->getLog()->debug('No default Display Profile set for Display type ' . $display->clientType);
        }

        // Add in the display profile information
        $display->setUnmatchedProperty(
            'displayProfile',
            (!array_key_exists($display->displayProfileId, $displayProfiles))
                ? $displayProfileName . __(' (Default)')
                : $displayProfiles[$display->displayProfileId]
        );

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
        $display->setUnmatchedProperty(
            'isPlayerSupported',
            $display->clientCode > Environment::$PLAYER_SUPPORT
                ? 1
                : 0
        );

        // Is a transfer to another CMS in progress?
        $display->setUnmatchedProperty('isCmsTransferInProgress', (!empty($display->newCmsAddress)));

        // permissions
        $display->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($display));
    }
}
