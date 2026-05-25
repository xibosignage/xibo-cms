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

use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Service\DownloadService;
use Xibo\Service\MediaService;
use Xibo\Service\MediaServiceInterface;
use Xibo\Service\UploadService;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\SanitizerInterface;

class PlayerSoftware extends Base
{
    public function __construct(
        private readonly PoolInterface $pool,
        private readonly PlayerVersionFactory $playerVersionFactory,
        private readonly DisplayProfileFactory $displayProfileFactory,
        private readonly DisplayFactory $displayFactory,
        private readonly MediaServiceInterface $mediaService,
    ) {
    }

    #[OA\Get(
        path: '/playersoftware',
        operationId: 'playerSoftwareSearch',
        description: 'Search Player Versions',
        summary: 'Search Player Versions',
        tags: ['Player Software']
    )]
    #[OA\Parameter(
        name: 'playerType',
        description: 'Filter by player type',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'playerVersion',
        description: 'Filter by player version',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'playerCode',
        description: 'Filter by player code',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'versionId',
        description: 'Filter by version ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'playerShowVersion',
        description: 'Filter by player show version name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Keyword search ( playerShowVersion, versionId )',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'versionId', 'type', 'version', 'code',
                'playerShowVersion', 'fileName', 'size',
                'createdAt', 'modifiedAt', 'modifiedBy',
            ]
        )
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Sort direction',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'])
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
            items: new OA\Items(ref: '#/components/schemas/PlayerVersion')
        )
    )]
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getParams());

        $versions = $this->playerVersionFactory->query(
            $this->gridRenderSort($sanitizedQueryParams, $this->isJson($request), 'code', 'desc'),
            $this->gridRenderFilter($this->getPlayerVersionFilters($sanitizedQueryParams), $sanitizedQueryParams)
        );

        foreach ($versions as $version) {
            $version->setUnmatchedProperty('fileSizeFormatted', ByteFormatter::format($version->size));
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->playerVersionFactory->countLast())
            ->withJson($versions);
    }

    private function getPlayerVersionFilters(SanitizerInterface $params): array
    {
        return [
            'playerType'        => $params->getString('playerType'),
            'playerVersion'     => $params->getString('playerVersion'),
            'playerCode'        => $params->getInt('playerCode'),
            'versionId'         => $params->getInt('versionId'),
            'useRegexForName'   => $params->getCheckbox('useRegexForName'),
            'playerShowVersion' => $params->getString('playerShowVersion'),
            'keyword'           => $params->getString('keyword'),
        ];
    }

    #[OA\Get(
        path: '/playersoftware/{versionId}',
        operationId: 'playerSoftwareSearchById',
        description: 'Get the Player Version object specified by the provided versionId',
        summary: 'Search Player Version by ID',
        tags: ['Player Software']
    )]
    #[OA\Parameter(
        name: 'versionId',
        description: 'Numeric ID of the Player Version to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/PlayerVersion')
    )]
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $version = $this->playerVersionFactory->getById($id);
        return $response->withStatus(200)->withJson($version);
    }

    #[OA\Get(
        path: '/playersoftware/meta',
        operationId: 'playerSoftwareMeta',
        description: 'Returns distinct player types, versions and valid upload extensions for use in filter dropdowns and upload configuration', // phpcs:ignore
        summary: 'Player Software Metadata',
        tags: ['Player Software']
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation'
    )]
    public function metaData(Request $request, Response $response): Response|ResponseInterface
    {
        return $response->withStatus(200)->withJson([
            'types'    => $this->playerVersionFactory->getDistinctType(),
            'versions' => $this->playerVersionFactory->getDistinctVersion(),
            'validExt' => $this->getValidExtensions(),
        ]);
    }

    #[OA\Delete(
        path: '/playersoftware/{versionId}',
        operationId: 'playerSoftwareDelete',
        description: 'Delete Version file from the Library and Player Versions table',
        summary: 'Delete Version',
        tags: ['Player Software']
    )]
    #[OA\Parameter(
        name: 'versionId',
        description: 'The Version ID to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    public function delete(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $version = $this->playerVersionFactory->getById($id);

        $version->load();

        // Unset player version from Display Profile
        $displayProfiles = $this->displayProfileFactory->query();

        foreach ($displayProfiles as $displayProfile) {
            if (in_array($displayProfile->type, ['android', 'lg', 'sssp'])) {
                $currentVersionId = $displayProfile->getSetting('versionMediaId');

                if ($currentVersionId === $version->versionId) {
                    $displayProfile->setSetting('versionMediaId', null);
                    $displayProfile->save();
                }
            } else if ($displayProfile->type === 'chromeOS') {
                $currentVersionId = $displayProfile->getSetting('playerVersionId');

                if ($currentVersionId === $version->versionId) {
                    $displayProfile->setSetting('playerVersionId', null);
                    $displayProfile->save();
                }
            }
        }

        $version->delete();

        return $response->withStatus(204);
    }

    #[OA\Put(
        path: '/playersoftware/{versionId}',
        operationId: 'playersoftwareEdit',
        description: 'Edit a Player Version file information',
        summary: 'Edit Player Version',
        tags: ['Player Software']
    )]
    #[OA\Parameter(
        name: 'versionId',
        description: 'The Version ID to Edit',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'playerShowVersion',
                        description: 'The Name of the player version application, this will be displayed in Version dropdowns in Display Profile and Display', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(property: 'version', description: 'The Version number', type: 'string'),
                    new OA\Property(property: 'code', description: 'The Code number', type: 'integer')
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/PlayerVersion')
    )]
    public function edit(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $version = $this->playerVersionFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $version->version = $sanitizedParams->getString('version');
        $version->code = $sanitizedParams->getInt('code');
        $version->playerShowVersion = $sanitizedParams->getString('playerShowVersion');
        $version->modifiedBy = $this->getUser()->userName;

        $version->save();

        return $response->withStatus(200)->withJson($version);
    }

    #[OA\Post(
        path: '/playersoftware',
        operationId: 'playersoftwareUpload',
        description: 'Upload a new Player version file',
        summary: 'Player Software Upload',
        tags: ['Player Software']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['files'],
                properties: [
                    new OA\Property(
                        property: 'files',
                        description: 'The Uploaded File',
                        type: 'string',
                        format: 'binary'
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'successful operation')]
    public function add(Request $request, Response $response): Response|ResponseInterface
    {
        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        MediaService::ensureLibraryExists($libraryFolder);
        $validExt = $this->getValidExtensions();

        $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = [
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => (
                $libraryLimit > 0
                && $this->mediaService->setUser($this->getUser())->libraryUsage() > $libraryLimit
            ),
        ];

        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        $uploadService = new UploadService($libraryFolder . 'temp/', $options, $this->getLog(), $this->getState());
        $uploadHandler = $uploadService->createUploadHandler();

        $uploadHandler->setPostProcessor(function ($file, $uploadHandler) use ($libraryFolder, $request) {
            if (!empty($file->error)) {
                $this->getState()->setCommitState(false);
                return $file;
            }

            $this->getUser()->isQuotaFullByUser(true);

            $filePath = $libraryFolder . 'temp/' . $file->fileName;

            $playerSoftware = $this->playerVersionFactory->createEmpty();
            $playerSoftware->modifiedBy = $this->getUser()->userName;

            // SoC players have issues parsing fileNames with spaces in them
            $playerSoftware->fileName = preg_replace('/[^a-zA-Z0-9_.]+/', '-', $file->fileName);
            $playerSoftware->size = filesize($filePath);
            $playerSoftware->md5 = md5_file($filePath);
            $playerSoftware->decorateRecord();

            if (!empty($file->name)) {
                $playerSoftware->playerShowVersion = $file->name;
            }

            $playerSoftware->save();

            if ($file->size != $playerSoftware->size) {
                throw new InvalidArgumentException(
                    __('Sorry this is a corrupted upload, the file size doesn\'t match what we\'re expecting.'),
                    'size'
                );
            }

            rename($filePath, $libraryFolder . 'playersoftware/' . $playerSoftware->fileName);

            $playerSoftware->unpack($libraryFolder, $request);

            $file->id = $playerSoftware->versionId;
            $file->md5 = $playerSoftware->md5;
            $file->name = $playerSoftware->fileName;

            return $file;
        });

        $uploadHandler->post();

        return $response->withHeader('Content-Type', 'application/json');
    }

    #[OA\Get(
        path: '/playersoftware/download/{id}',
        operationId: 'playersoftwareDownload',
        description: 'Download Player Version file',
        summary: 'Download Player Version file',
        tags: ['Player Software']
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'The Player Version ID to Download',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'X-Sendfile',
                description: 'Apache Send file header - if enabled.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Header(
                header: 'X-Accel-Redirect',
                description: 'nginx send file header - if enabled.',
                schema: new OA\Schema(type: 'string')
            )
        ],
        content: new OA\MediaType(
            mediaType: 'application/octet-stream',
            schema: new OA\Schema(type: 'string', format: 'binary')
        )
    )]
    public function download(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $playerVersion = $this->playerVersionFactory->getById($id);

        $this->getLog()->debug('Download request for player software versionId: ' . $id);

        $library = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $sendFileMode = $this->getConfig()->getSetting('SENDFILE_MODE');
        $libraryPath = $library . 'playersoftware' . DIRECTORY_SEPARATOR . $playerVersion->fileName;
        $attachmentName = urlencode($playerVersion->fileName);

        $downLoadService = new DownloadService($libraryPath, $sendFileMode);
        $downLoadService->useLogger($this->getLog()->getLoggerInterface());

        return $downLoadService->returnFile(
            $response,
            $attachmentName,
            '/download/playersoftware/' . $playerVersion->fileName
        );
    }

    public function getSsspInstall(Request $request, Response $response): Response|ResponseInterface
    {
        $profile = $this->displayProfileFactory->getDefaultByType('sssp');
        $versionId = $profile->getSetting('versionMediaId');

        if ($versionId !== null) {
            $version = $this->playerVersionFactory->getById($versionId);
            $xml = $this->outputSsspXml($version->version . '.' . $version->code, $version->size);
            $response = $response
                ->withHeader('Content-Type', 'application/xml')
                ->write($xml);
        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput(true);
        return $this->render($request, $response);
    }

    public function getSsspInstallDownload(Request $request, Response $response): Response|ResponseInterface
    {
        $profile = $this->displayProfileFactory->getDefaultByType('sssp');
        $versionId = $profile->getSetting('versionMediaId');

        if ($versionId !== null) {
            $response = $this->download($request, $response, $versionId);
        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput();
        return $this->render($request, $response);
    }

    public function getSssp(Request $request, Response $response, string $nonce): Response|ResponseInterface
    {
        $cache = $this->pool->getItem('/playerVersion/' . $nonce);

        if ($cache->isMiss()) {
            $response = $response->withStatus(404);
            $this->setNoOutput(true);
            return $this->render($request, $response);
        }

        $displayId = $cache->get();
        $display = $this->displayFactory->getById($displayId);

        if ($display->clientType != 'sssp') {
            throw new InvalidArgumentException(__('File available only for SSSP displays'), 'clientType');
        }

        $response = $response->withHeader('content-type', 'application/xml');
        $versionId = $display->getSetting('versionMediaId', null, ['displayOverride' => true]);

        if ($versionId !== null) {
            $versionInformation = $this->playerVersionFactory->getById($versionId);
            $xml = $this->outputSsspXml(
                $versionInformation->version . '.' . $versionInformation->code,
                $versionInformation->size
            );
            $response = $response->write($xml);
        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput(true);
        return $this->render($request, $response);
    }

    public function getVersionFile(Request $request, Response $response, string $nonce): Response|ResponseInterface
    {
        $cache = $this->pool->getItem('/playerVersion/' . $nonce);

        if ($cache->isMiss()) {
            $response = $response->withStatus(404);
            $this->setNoOutput(true);
            return $this->render($request, $response);
        }

        $displayId = $cache->get();
        $display = $this->displayFactory->getById($displayId);
        $versionId = $display->getSetting('versionMediaId', null, ['displayOverride' => true]);

        if ($versionId !== null) {
            $response = $this->download($request, $response, $versionId);
        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput(true);
        return $this->render($request, $response);
    }

    private function outputSsspXml(string $version, int $size): string
    {
        $ssspDocument = new \DOMDocument('1.0', 'UTF-8');
        $versionNode = $ssspDocument->createElement('widget');
        $version = $ssspDocument->createElement('ver', $version);
        $size = $ssspDocument->createElement('size', $size);
        $name = $ssspDocument->createElement('widgetname', 'sssp_dl');

        $ssspDocument->appendChild($versionNode);
        $versionNode->appendChild($version);
        $versionNode->appendChild($size);
        $versionNode->appendChild($name);
        $versionNode->appendChild($ssspDocument->createElement('webtype', 'tizen'));
        $ssspDocument->formatOutput = true;

        return $ssspDocument->saveXML();
    }

    /** @return string[] */
    private function getValidExtensions(): array
    {
        return ['apk', 'ipk', 'wgt', 'chrome'];
    }
}
