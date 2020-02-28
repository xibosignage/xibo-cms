<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\HelpFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Help
 * @package Xibo\Controller
 */
class Help extends Base
{
    /**
     * @var HelpFactory
     */
    private $helpFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param HelpFactory $helpFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $helpFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->helpFactory = $helpFactory;
    }

    /**
     * Help Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'help-page';

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $helpLinks = $this->helpFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([], $request));

        foreach ($helpLinks as $row) {
            /* @var \Xibo\Entity\Help $row */

            // we only want to show certain buttons, depending on the user logged in
            if ($this->getUser()->userTypeId == 1) {

                // Edit
                $row->buttons[] = array(
                    'id' => 'help_button_edit',
                    'url' => $this->urlFor($request,'help.edit.form', ['id' => $row->helpId]),
                    'text' => __('Edit')
                );

                // Delete
                $row->buttons[] = array(
                    'id' => 'help_button_delete',
                    'url' => $this->urlFor($request,'help.delete.form', ['id' => $row->helpId]),
                    'text' => __('Delete')
                );

                // Test
                $row->buttons[] = array(
                    'id' => 'help_button_test',
                    'url' => $this->getHelp()->link($row->topic, $row->category),
                    'text' => __('Test')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->helpFactory->countLast();
        $this->getState()->setData($helpLinks);

        return $this->render($request, $response);
    }

    /**
     * Add Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function addForm(Request $request, Response $response)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'help-form-add';

        return $this->render($request, $response);
    }

    /**
     * Help Edit form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm(Request $request, Response $response, $id)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $help = $this->helpFactory->getById($id);

        $this->getState()->template = 'help-form-edit';
        $this->getState()->setData([
            'help' => $help
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Help Link Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $help = $this->helpFactory->getById($id);

        $this->getState()->template = 'help-form-delete';
        $this->getState()->setData([
            'help' => $help
        ]);

        return $this->render($request, $response);
    }

    /**
     * Adds a help link
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function add(Request $request, Response $response)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());
        $help = $this->helpFactory->createEmpty();
        $help->topic = $sanitizedParams->getString('topic');
        $help->category = $sanitizedParams->getString('category');
        $help->link = $sanitizedParams->getString('link');

        $help->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $help->topic),
            'id' => $help->helpId,
            'data' => $help
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edits a help link
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
     */
    public function edit(Request $request, Response $response, $id)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $help = $this->helpFactory->getById($id);
        $help->topic = $sanitizedParams->getString('topic');
        $help->category = $sanitizedParams->getString('category');
        $help->link = $sanitizedParams->getString('link');

        $help->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $help->topic),
            'id' => $help->helpId,
            'data' => $help
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
     */
    public function delete(Request $request, Response $response, $id)
    {
        if ($this->getUser()->userTypeId != 1) {
            throw new AccessDeniedException();
        }

        $help = $this->helpFactory->getById($id);
        $help->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $help->topic)
        ]);

        return $this->render($request, $response);
    }
}
