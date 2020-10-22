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
use Xibo\Factory\FolderFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

class Folder extends Base
{
    /**
     * @var FolderFactory
     */
    private $folderFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param ConfigServiceInterface $config
     * @param FolderFactory $folderFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $config, $folderFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $config);

        $this->folderFactory = $folderFactory;
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
        $parsedParams = $this->getSanitizer($request->getParams());
        $treeJson = [];

        $folders = $this->folderFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([
            'folderId' => $parsedParams->getInt('folderId'),
            'folderName' => $parsedParams->getString('folderName'),
            'isRoot' => $parsedParams->getInt('isRoot'),
        ], $request));

        foreach ($folders as $folder) {
            if ($folder->id === 1) {
                $this->buildTreeView($folder);
                array_push($treeJson, $folder);
            }
        }

        return $response->withJson($treeJson);
    }

    /**
     * @param \Xibo\Entity\Folder $folder
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function buildTreeView(&$folder)
    {
        $children = array_filter(explode(',', $folder->children));
        $childrenDetails = [];

        foreach ($children as $childId) {
            $child = $this->folderFactory->getById($childId);

            if ($child->children != null) {
                $this->buildTreeView($child);
            }
            array_push($childrenDetails, $child);
        }

        $folder->children = $childrenDetails;
    }

    /**
     * Adds a Folder
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $folder = $this->folderFactory->createEmpty();
        $folder->text = $sanitizedParams->getString('text');
        $folder->parentId = $sanitizedParams->getString('parentId');

        $folder->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $folder->text),
            'id' => $folder->id,
            'data' => $folder
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edits a help link
     * @param Request $request
     * @param Response $response
     * @param $folderId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function edit(Request $request, Response $response, $folderId)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $folder = $this->folderFactory->getById($folderId);

        if ($folder->isRoot === 1) {
            throw new InvalidArgumentException(__('Cannot edit root Folder'), 'isRoot');
        }

        $folder->text = $sanitizedParams->getString('text');
        $folder->parentId = $sanitizedParams->getString('parentId', ['default' => $folder->parentId]);

        $folder->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $folder->text),
            'id' => $folder->id,
            'data' => $folder
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete
     * @param Request $request
     * @param Response $response
     * @param $folderId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function delete(Request $request, Response $response, $folderId)
    {
        $folder = $this->folderFactory->getById($folderId);

        if ($folder->isRoot === 1) {
            throw new InvalidArgumentException(__('Cannot remove root Folder'), 'isRoot');
        }

        $folder->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $folder->text)
        ]);

        return $this->render($request, $response);
    }

}