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

use baseDAO;
use database;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\LayoutFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Preview
 * @package Xibo\Controller
 */
class Preview extends Base
{
    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param LayoutFactory $layoutFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $layoutFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->layoutFactory = $layoutFactory;
    }

    /**
     * Layout Preview
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
    public function show(Request $request, Response $response, $id )
    {
        $layout = $this->layoutFactory->getById($id);

        if (!$this->getUser($request)->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'layout-preview';
        $this->getState()->setData([
            'layout' => $layout,
            'previewOptions' => [
                'getXlfUrl' => $this->urlFor($request,'layout.getXlf', ['id' => $layout->layoutId]),
                'getResourceUrl' => $this->urlFor($request,'module.getResource'),
                'libraryDownloadUrl' => $this->urlFor($request,'library.download'),
                'layoutBackgroundDownloadUrl' => $this->urlFor($request,'layout.download.background'),
                'loaderUrl' => $this->getConfig()->uri('img/loader.gif')
            ]
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get the XLF for a Layout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\InvalidArgumentException
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\XiboException
     */
    function getXlf(Request $request, Response $response, $id )
    {
        $layout = $this->layoutFactory->getById($id);

        if (!$this->getUser($request)->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        echo file_get_contents($layout->xlfToDisk());

        $this->setNoOutput(true);
        return $this->render($request, $response);
    }
}
