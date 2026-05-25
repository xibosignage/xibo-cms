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

use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\TransitionFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Transition
 * @package Xibo\Controller
 */
class Transition extends Base
{
    /**
     * @var TransitionFactory
     */
    private TransitionFactory $transitionFactory;

    /**
     * Set common dependencies.
     * @param TransitionFactory $transitionFactory
     */
    public function __construct(TransitionFactory $transitionFactory)
    {
        $this->transitionFactory = $transitionFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $transitionSortQuery = $this->gridRenderSort(
            $sanitizedQueryParams,
            $this->isJson($request),
            'transition'
        );
        $transitionFilterQuery = $this->getTransitionFilterQuery($sanitizedQueryParams);

        $transitions = $this->transitionFactory->query($transitionSortQuery, $transitionFilterQuery);

        foreach ($transitions as $transition) {
            $transition->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($transition));
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->transitionFactory->countLast())
            ->withJson($transitions);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws NotFoundException
     * @throws InvalidArgumentException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $transition = $this->transitionFactory->getById($id);

        $transition->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($transition));

        return $response->withStatus(200)->withJson($transition);
    }
    /**
     * Transition Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function editForm(Request $request, Response $response, $id): Response|ResponseInterface
    {
        // TODO: Remove this once the layout editor is updated
        if (
            $this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 1 ||
            $this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked'
        ) {
            throw new AccessDeniedException(__('Transition Config Locked'));
        }

        $transition = $this->transitionFactory->getById($id);

        $this->getState()->template = 'transition-form-edit';
        $this->getState()->setData([
            'transition' => $transition,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Transition
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function edit(Request $request, Response $response, $id): Response|ResponseInterface
    {
        if (
            $this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 1 ||
            $this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked'
        ) {
            throw new AccessDeniedException(__('Transition Config Locked'));
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $transition = $this->transitionFactory->getById($id);
        $transition->availableAsIn = $sanitizedParams->getCheckbox('availableAsIn');
        $transition->availableAsOut = $sanitizedParams->getCheckbox('availableAsOut');
        $transition->save();

        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $transition->transition),
            'id' => $transition->transitionId,
            'data' => $transition
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get the transition filters
     * @param $sanitizedQueryParams
     * @return array
     */
    private function getTransitionFilterQuery($sanitizedQueryParams): array
    {
        return $this->gridRenderFilter([
            'transition' => $sanitizedQueryParams->getString('transition'),
            'code' => $sanitizedQueryParams->getString('code'),
            'availableAsIn' => $sanitizedQueryParams->getInt('availableAsIn'),
            'availableAsOut' => $sanitizedQueryParams->getInt('availableAsOut')
        ], $sanitizedQueryParams);
    }
}
