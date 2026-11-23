<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Controller;

use Carbon\Carbon;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\ItemInterface;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Display;
use Xibo\Event\DisplayLimitEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\DisplayClaimGroup;
use Xibo\Helper\HttpsDetect;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Add Display endpoints: licence usage, connect details, connect code, connect status, connect apply.
 *
 * These are session-only (consumed by the React frontend) and registered in routes-react.php.
 */
class DisplayClaim extends Base
{
    use BuildDisplayClaimTrait;
    use NotifyPendingConnectTrait;

    /**
     * Characters used for manual-connect codes.
     *
     * Deliberately excludes 0/O/1/I to stop the operator mis-typing a code they are reading off
     * one screen and keying into another, often with a remote control.
     */
    private const MANUAL_CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(
        private readonly DisplayFactory $displayFactory,
        private readonly PoolInterface $pool,
        private readonly ConfigServiceInterface $config,
        private readonly NotificationFactory $notificationFactory,
        private readonly UserGroupFactory $userGroupFactory,
        private readonly DisplayGroupFactory $displayGroupFactory,
        private readonly FolderFactory $folderFactory,
    ) {
    }

    #[OA\Get(
        path: '/display/licence/usage',
        operationId: 'displayLicenceUsage',
        description: 'Current display licence consumption',
        summary: 'Licence Usage',
        tags: ['display']
    )]
    #[OA\Response(response: 200, description: 'successful operation')]
    public function licenceUsage(Request $request, Response $response): Response|ResponseInterface
    {
        $event = new DisplayLimitEvent();
        $this->getDispatcher()->dispatch($event, DisplayLimitEvent::$NAME);
        $maxLicensed = $event->getLimit();
        $currentlyLicensed = $event->getUsed() ?? $this->displayFactory->countLicensed();

        $this->getState()->hydrate([
            'data' => [
                // null means unlimited
                'maxLicensed' => $maxLicensed,
                'currentlyLicensed' => $currentlyLicensed,
                // null means unlimited
                'available' => $maxLicensed !== null ? max(0, $maxLicensed - $currentlyLicensed) : null,
            ]
        ]);

        return $this->render($request, $response);
    }

    #[OA\Get(
        path: '/display/connect/details',
        operationId: 'displayConnectDetails',
        description: 'The CMS Address and Key a Player needs to be configured manually',
        summary: 'Display Connect Details',
        tags: ['display']
    )]
    #[OA\Response(response: 200, description: 'successful operation')]
    /**
     * The CMS Address and Key an operator has to type into a Player when configuring it by hand.
     */
    public function connectDetails(Request $request, Response $response): Response|ResponseInterface
    {
        $cmsAddress = (new HttpsDetect($this->config))->getBaseUrl($request);

        $this->getState()->hydrate([
            'data' => [
                'cmsAddress' => $cmsAddress,
                'cmsKey' => $this->config->getSetting('SERVER_KEY'),
            ]
        ]);

        return $this->render($request, $response);
    }

    #[OA\Post(
        path: '/display/connect/code',
        operationId: 'displayConnectCode',
        description: 'Issue a one-time code identifying a Player about to be configured by hand',
        summary: 'Display Connect Code',
        tags: ['display']
    )]
    #[OA\Response(response: 200, description: 'successful operation')]
    /**
     * Issue a one-time code for a manually configured Player.
     */
    public function connectCode(Request $request, Response $response): Response|ResponseInterface
    {
        $code = null;

        // Reissue on collision: a code already in flight belongs to somebody else's Player.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = '';
            for ($i = 0; $i < 4; $i++) {
                $candidate .= self::MANUAL_CODE_ALPHABET[random_int(0, strlen(self::MANUAL_CODE_ALPHABET) - 1)];
            }

            if ($this->pool->getItem(Display::getManualConnectCacheKey($candidate))->isMiss()) {
                $code = $candidate;
                break;
            }
        }

        if ($code === null) {
            throw new GeneralException(__('Unable to issue a connection code, please try again'));
        }

        // The code only identifies the Player. It carries no settings: the Player registers with the
        // CMS defaults, and the Add Display form applies the operator's choices once it has connected.
        $claimData = [
            'userId' => $this->getUser()->userId,
            'displayId' => null,
            'notificationId' => $this->notifyPendingConnect(
                $code,
                Carbon::now()->addMinutes(30),
                (new HttpsDetect($this->config))->getBaseUrl($request) . '/displays/all-displays'
            ),
        ];

        $item = $this->pool->getItem(Display::getManualConnectCacheKey($code));
        $item->set($claimData);
        $item->expiresAfter(new \DateInterval('PT30M'));
        $this->pool->save($item);

        $this->getState()->hydrate([
            'data' => [
                'code' => $code,
                'expiresInMinutes' => 30,
            ]
        ]);

        return $this->render($request, $response);
    }

    #[OA\Get(
        path: '/display/connect/status',
        operationId: 'displayConnectStatus',
        description: 'Has the Player holding this one-time code reached the CMS yet?',
        summary: 'Display Connect Status',
        tags: ['display']
    )]
    #[OA\Response(response: 200, description: 'successful operation')]
    /**
     * Report whether the Player holding a one-time code has registered.
     */
    public function connectStatus(Request $request, Response $response): Response|ResponseInterface
    {
        $code = $this->getSanitizer($request->getQueryParams())->getString('code');

        if (empty($code)) {
            throw new InvalidArgumentException(__('Code cannot be empty'), 'code');
        }

        $item = null;
        $pending = null;

        // The code is either one we issued for manual configuration, or the activation code the
        // operator typed in. Both record which display claimed them, so both answer here.
        // Only a well-formed manual code is looked up as one, since it is part of that cache key.
        // The activation-code key is hashed, so any input is safe there.
        $manualCode = Display::normaliseManualCode($code);
        if ($manualCode !== null) {
            $item = $this->pool->getItem(Display::getManualConnectCacheKey($manualCode));
            $pending = $item->get();
        }

        if ($item === null || $item->isMiss() || !is_array($pending)) {
            $item = $this->pool->getItem(Display::getAddViaCodeCacheKey($code));
            $pending = $item->get();
        }

        if ($item->isMiss() || !is_array($pending)) {
            $this->getState()->hydrate([
                'data' => [
                    'expired' => true,
                    'connected' => false,
                ]
            ]);

            return $this->render($request, $response);
        }

        // A code belongs to the session that asked for it.
        if (intval($pending['userId'] ?? 0) !== $this->getUser()->userId) {
            $this->getState()->hydrate([
                'data' => [
                    'expired' => true,
                    'connected' => false,
                ]
            ]);

            return $this->render($request, $response);
        }

        $displayId = intval($pending['displayId'] ?? 0);
        $display = null;

        if ($displayId > 0) {
            try {
                $display = $this->displayFactory->getById($displayId);
            } catch (NotFoundException $e) {
                $displayId = 0;
            }
        }

        $existing = $displayId > 0 && !empty($pending['existing']);

        $this->getState()->hydrate([
            'data' => [
                'expired' => false,
                'connected' => $displayId > 0,
                'displayId' => $displayId > 0 ? $displayId : null,
                // A display that was already registered can be one this user has no rights over. A new
                // one was added by this user's own code, even if it landed in a folder they can't see.
                'display' => $display !== null && (!$existing || $this->getUser()->checkViewable($display))
                    ? $display->display
                    : null,
                // The Player was already registered, so it wasn't added and no settings were applied.
                'existing' => $existing,
            ]
        ]);

        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/display/connect/{code}',
        operationId: 'displayConnectApply',
        description: 'Apply the Add Display settings to the Player that connected with this one-time code',
        summary: 'Display Connect Apply',
        tags: ['display']
    )]
    #[OA\Parameter(
        name: 'code',
        description: 'The one-time code issued by Display Connect Code',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'displayName', description: 'Display name', type: 'string'),
                    new OA\Property(property: 'folderId', description: 'Folder to put the display in', type: 'integer'),
                    new OA\Property(
                        property: 'displayGroupId',
                        description: 'Display group to add the display to',
                        type: 'integer'
                    ),
                    new OA\Property(property: 'authorised', description: 'Authorise the display', type: 'integer'),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'successful operation')]
    /**
     * Apply the operator's settings to the display a manual-connect code was claimed by.
     *
     * The Player registered with the CMS defaults, often in a folder the operator can't see or edit,
     * so the usual display endpoints can't be used. Holding the code that brought this display in is
     * what authorises the change, as with an activation code, and the same rules decide what the
     * operator may set. It applies once, and only to a display the code added.
     */
    public function connectApply(Request $request, Response $response, string $code): Response|ResponseInterface
    {
        $manualCode = Display::normaliseManualCode($code);
        $item = $manualCode !== null ? $this->pool->getItem(Display::getManualConnectCacheKey($manualCode)) : null;
        $pending = $item?->get();

        // Someone else's code is reported the same as an unknown one.
        if ($item === null
            || $item->isMiss()
            || !is_array($pending)
            || intval($pending['userId'] ?? 0) !== $this->getUser()->userId
        ) {
            throw new NotFoundException(__('This connection code has expired. Please start again.'));
        }

        if (empty($pending['displayId'])) {
            throw new InvalidArgumentException(__('The Player has not connected yet.'), 'code');
        }

        if (!empty($pending['existing'])) {
            throw new InvalidArgumentException(
                __('This Player was already registered, so its settings cannot be changed here.'),
                'code'
            );
        }

        if (!empty($pending['applied'])) {
            throw new InvalidArgumentException(__('These settings have already been saved.'), 'code');
        }

        // Mark the code used straight away, so a second request arriving later (a double submit)
        // is refused. This narrows the window rather than closing it: Stash has no compare-and-set,
        // so two requests that both read before either writes will both apply. Kept rather than
        // dropped, so connect status still answers for this code.
        $this->saveManualClaim($item, array_merge($pending, ['applied' => true]));

        try {
            $sanitizedParams = $this->getSanitizer($request->getParams());
            $claim = $this->buildDisplayClaim($sanitizedParams);

            $display = $this->displayFactory->getById(intval($pending['displayId']));

            if (!empty($claim['display'])) {
                $display->display = $claim['display'];
            }
            if (!empty($claim['folderId'])) {
                $display->folderId = $claim['folderId'];
            }
            // Set either way: the CMS may have authorised it automatically, and the operator turned that off.
            $display->licensed = empty($claim['licensed']) ? 0 : 1;
            $display->save();
        } catch (\Throwable $e) {
            // Nothing was saved (e.g. a folder they can't use, or no licence left), so the
            // operator can correct the form and try again.
            $this->saveManualClaim($item, $pending);
            throw $e;
        }

        $groupAssigned = DisplayClaimGroup::assign(
            $this->displayGroupFactory,
            $this->getDispatcher(),
            $this->getLog(),
            $display,
            intval($claim['displayGroupId'] ?? 0)
        );

        // Read back, since the permissions come from the folder the display has just moved to.
        $display = $this->displayFactory->getById($display->displayId);

        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $display->display),
            'id' => $display->displayId,
            'data' => [
                'displayId' => $display->displayId,
                'display' => $display->display,
                'licensed' => intval($display->licensed),
                'folderId' => $display->folderId,
                // The display can end up in a folder this user can't see, e.g. the default folder
                // when they could not choose one.
                'viewable' => $this->getUser()->checkViewable($display),
                // The rest of the settings stand even if the group could not be assigned.
                'displayGroupAssigned' => $groupAssigned,
            ]
        ]);

        return $this->render($request, $response);
    }

    /**
     * Write a manual-connect claim back to its cache item, for the rest of the code's 30 minutes.
     */
    private function saveManualClaim(ItemInterface $item, array $claim): void
    {
        $item->set($claim);
        $item->expiresAfter(new \DateInterval('PT30M'));
        $this->pool->save($item);
    }
}
