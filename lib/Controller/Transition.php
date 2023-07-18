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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\TransitionFactory;
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Class Transition
 * @package Xibo\Controller
 */
class Transition extends Base
{
    /**
     * @var TransitionFactory
     */
    private $transitionFactory;

    /**
     * Set common dependencies.
     * @param TransitionFactory $transitionFactory
     */
    public function __construct($transitionFactory)
    {
        $this->transitionFactory = $transitionFactory;
    }

    /**
     * No display page functionaility
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'transition-page';

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'transition' => $sanitizedQueryParams->getString('transition'),
            'code' => $sanitizedQueryParams->getString('code'),
            'availableAsIn' => $sanitizedQueryParams->getInt('availableAsIn'),
            'availableAsOut' => $sanitizedQueryParams->getInt('availableAsOut')
        ];

        $transitions = $this->transitionFactory->query($this->gridRenderSort($sanitizedQueryParams), $this->gridRenderFilter($filter, $sanitizedQueryParams));

        foreach ($transitions as $transition) {
            /* @var \Xibo\Entity\Transition $transition */

            // If the module config is not locked, present some buttons
            if ($this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') != 1 && $this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') != 'Checked' ) {

                // Edit button
                $transition->buttons[] = array(
                    'id' => 'transition_button_edit',
                    'url' => $this->urlFor($request,'transition.edit.form', ['id' => $transition->transitionId]),
                    'text' => __('Edit')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->transitionFactory->countLast();
        $this->getState()->setData($transitions);

        return $this->render($request, $response);
    }

    /**
     * Transition Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function editForm(Request $request, Response $response, $id)
    {
        if ($this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 1 || $this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked') {
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
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function edit(Request $request, Response $response, $id)
    {
        if ($this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 1 || $this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked') {
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
}
