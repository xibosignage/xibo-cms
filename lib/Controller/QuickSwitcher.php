<?php
namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Controller\Base;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Support\Exception\GeneralException;

/**
 * Class QuickSwitcher
 * Controller placed under lib/Controller so it behaves like other first-class controllers
 */
class QuickSwitcher extends Base
{
    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var DisplayFactory */
    private $displayFactory;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var CampaignFactory */
    private $campaignFactory;

    /** @var \Xibo\Factory\FolderFactory */
    private $folderFactory;

    public function __construct(
        $layoutFactory,
        $mediaFactory,
        $displayFactory,
        $playlistFactory,
        $campaignFactory,
        $folderFactory = null
    ) {
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->displayFactory = $displayFactory;
        $this->playlistFactory = $playlistFactory;
        $this->campaignFactory = $campaignFactory;
        $this->folderFactory = $folderFactory;
    }

    /**
     * Search endpoint for the QuickSwitcher frontend.
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws GeneralException
     */
    public function search(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $query = trim($sanitizedParams->getString('q', ['default' => '', 'defaultOnEmptyString' => true]));

        $results = [];

        if ($query === '') {
            return $response->withJson(['results' => $results]);
        }

        $searchFilter = str_replace(' ', ',', preg_replace('/\s+/', ' ', trim($query)));

        $navCandidates = [];
        $currentUser = $this->getUser();

        $addNav = function ($featureCheck, $label, $routeName, $hint = 'Navigation') use (&$navCandidates, $request, $currentUser) {
            try {
                if ($featureCheck === null || ($currentUser && $currentUser->featureEnabled($featureCheck))) {
                    $navCandidates[] = [
                        'type' => 'Navigation',
                        'label' => $label,
                        'hint' => $hint,
                        'route' => $routeName,
                    ];
                }
            } catch (\Throwable $e) {
            }
        };

        $addNav(null, 'Dashboard', 'home');
        $addNav('schedule.view', 'Schedule', 'schedule.view');
        $addNav('daypart.view', 'Dayparting', 'daypart.view');

        $addNav('campaign.view', 'Campaigns', 'campaign.view');
        $addNav('layout.view', 'Layouts', 'layout.view');
        $addNav('template.view', 'Templates', 'template.view');
        $addNav('resolution.view', 'Resolutions', 'resolution.view');

        $addNav('playlist.view', 'Playlists', 'playlist.view');
        $addNav('library.view', 'Media', 'library.view');
        $addNav('dataset.view', 'DataSets', 'dataset.view');
        $addNav('menuBoard.view', 'Menu Boards', 'menuBoard.view');

        $addNav('displays.view', 'Displays', 'display.view');
        $addNav('displaygroup.view', 'Display Groups', 'displaygroup.view');
        $addNav('display.syncView', 'Sync Groups', 'syncgroup.view');
        $addNav('displayprofile.view', 'Display Settings', 'displayprofile.view');
        $addNav('playersoftware.view', 'Player Versions', 'playersoftware.view');
        $addNav('command.view', 'Commands', 'command.view');

        $userMenuViewable = ($currentUser && $currentUser->featureEnabled('users.view') && ($currentUser->isGroupAdmin() || $currentUser->isSuperAdmin()));
        if ($userMenuViewable) {
            $addNav(null, 'Users', 'user.view');
        }
        $addNav('usergroup.view', 'User Groups', 'group.view');
        $addNav(null, 'Settings', 'admin.view');
        $addNav(null, 'Applications', 'application.view');
        $addNav('module.view', 'Modules', 'module.view');
        $addNav('transition.view', 'Transitions', 'transition.view');
        $addNav('task.view', 'Tasks', 'task.view');
        $addNav('tag.view', 'Tags', 'tag.view');
        $addNav('folders.view', 'Folders', 'folders.view');
        $addNav('font.view', 'Fonts', 'font.view');

        $addNav('report.view', 'All Reports', 'report.view');
        $addNav('report.scheduling', 'Report Schedules', 'reportschedule.view');
        $addNav('report.saving', 'Saved Reports', 'savedreport.view');
        $addNav('log.view', 'Log', 'log.view');
        $addNav('sessions.view', 'Sessions', 'sessions.view');
        $addNav('auditlog.view', 'Audit Trail', 'auditlog.view');
        $addNav('fault.view', 'Report Fault', 'fault.view');

        $addNav('developer.edit', 'Module Templates', 'developer.templates.view');

        $typeParam = $sanitizedParams->getString('type', ['default' => 'all']);
        $types = array_values(array_filter(
            array_map('trim', explode(',', strtolower($typeParam))),
            function ($t) {
                return $t !== '';
            }
        ));
        if (empty($types)) {
            $types = ['all'];
        }

        if ((in_array('all', $types) || in_array('navigation', $types)) && !empty($navCandidates)) {
            foreach ($navCandidates as $nav) {
                if ($query !== '' && stripos($nav['label'], $query) !== false) {
                    try {
                        $url = $this->urlFor($request, $nav['route']);
                    } catch (\Throwable $e) {
                        $url = '#';
                    }
                    $results[] = [
                        'type' => $nav['type'],
                        'label' => $nav['label'],
                        'hint' => $nav['hint'],
                        'url' => $url,
                    ];
                }
            }
        }

        $maxResults = 30;
        $perType = 10;

        if ((in_array('all', $types) || in_array('layout', $types)) && $this->layoutFactory !== null) {
            try {
                $layouts = $this->layoutFactory->query(null, [
                    'layout' => $searchFilter,
                    'start' => 0,
                    'length' => $perType
                ]);

                foreach ($layouts as $layout) {
                    $folderName = '';
                    if ($this->folderFactory !== null && !empty($layout->folderId)) {
                        try {
                            $f = $this->folderFactory->getById($layout->folderId);
                            $folderName = $f->folderName ?? ($f->text ?? '');
                        } catch (\Throwable $e) {
                            $folderName = '';
                        }
                    }

                    $results[] = [
                        'type' => 'Layout',
                        'label' => $layout->layout,
                        'hint' => $folderName ?: ($layout->owner ?? ''),
                        'url' => $this->urlFor($request, 'layout.view')
                            . '?layout=' . urlencode($layout->layout)
                            . '&folderId=' . urlencode((string)($layout->folderId ?? '')),
                    ];
                }
            } catch (\Throwable $e) {
                $this->getLog()->error('QuickSwitcher: Layout search failed. Error: ' . $e->getMessage());
            }
        }

        if ((in_array('all', $types) || in_array('display', $types)) && $this->displayFactory !== null) {
            try {
                $displays = $this->displayFactory->query(null, [
                    'display' => $searchFilter,
                    'start' => 0,
                    'length' => $perType
                ]);

                foreach ($displays as $display) {
                    $label = $display->display ?? '';
                    $hint = $display->deviceName ?? $display->address ?? '';
                    $results[] = [
                        'type' => 'Display',
                        'label' => $label,
                        'hint' => $hint,
                        'url' => $this->urlFor($request, 'display.view')
                            . '?display=' . urlencode($label)
                            . '&folderId=' . urlencode((string)($display->folderId ?? '')),
                        'id' => $display->displayId
                    ];
                }
            } catch (\Throwable $e) {
                $this->getLog()->error('QuickSwitcher: Display search failed. Error: ' . $e->getMessage());
            }
        }

        if ((in_array('all', $types) || in_array('media', $types)) && $this->mediaFactory !== null) {
            try {
                $mediaItems = $this->mediaFactory->query(null, [
                    'name' => $searchFilter,
                    'start' => 0,
                    'length' => $perType
                ]);

                foreach ($mediaItems as $media) {
                    $label = $media->name ?? '';
                    $hint = $media->mediaType ?? $media->fileName ?? '';
                    $results[] = [
                        'type' => 'Media',
                        'label' => $label,
                        'hint' => $hint,
                        'url' => $this->urlFor($request, 'library.view')
                            . '?media=' . urlencode($label)
                            . '&folderId=' . urlencode((string)($media->folderId ?? '')),
                        'id' => $media->mediaId
                    ];
                }
            } catch (\Throwable $e) {
                $this->getLog()->error('QuickSwitcher: Media search failed. Error: ' . $e->getMessage());
            }
        }

        if ((in_array('all', $types) || in_array('campaign', $types)) && $this->campaignFactory !== null) {
            try {
                $campaigns = $this->campaignFactory->query(null, [
                    'name' => $searchFilter,
                    'start' => 0,
                    'length' => $perType,
                    'isLayoutSpecific' => 0
                ]);

                foreach ($campaigns as $campaign) {
                    $label = $campaign->campaign ?? '';
                    $hint = $campaign->type ?? '';
                    $results[] = [
                        'type' => 'Campaign',
                        'label' => $label,
                        'hint' => ucfirst($hint),
                        'url' => $this->urlFor($request, 'campaign.view')
                            . '?name=' . urlencode($label)
                            . '&folderId=' . urlencode((string)($campaign->folderId ?? '')),
                        'id' => $campaign->campaignId
                    ];
                }
            } catch (\Throwable $e) {
                $this->getLog()->error('QuickSwitcher: Campaign search failed. Error: ' . $e->getMessage());
            }
        }

        if ((in_array('all', $types) || in_array('playlist', $types)) && $this->playlistFactory !== null) {
            try {
                $playlists = $this->playlistFactory->query(null, [
                    'name' => $searchFilter,
                    'start' => 0,
                    'length' => $perType,
                    'regionSpecific' => 0
                ]);

                foreach ($playlists as $playlist) {
                    $label = $playlist->name ?? '';

                    $folderName = '';
                    if ($this->folderFactory !== null && !empty($playlist->folderId)) {
                        try {
                            $f = $this->folderFactory->getById($playlist->folderId);
                            $folderName = $f->folderName ?? ($f->text ?? '');
                        } catch (\Throwable $e) {
                            $folderName = '';
                        }
                    }

                    $results[] = [
                        'type' => 'Playlist',
                        'label' => $label,
                        'hint' => $folderName ?: ($playlist->owner ?? ''),
                        'url' => $this->urlFor($request, 'playlist.view')
                            . '?name=' . urlencode($label)
                            . '&folderId=' . urlencode((string)($playlist->folderId ?? '')),
                        'id' => $playlist->playlistId
                    ];
                }
            } catch (\Throwable $e) {
                $this->getLog()->error('QuickSwitcher: Playlist search failed. Error: ' . $e->getMessage());
            }
        }

        if (count($results) > $maxResults) {
            $results = array_slice($results, 0, $maxResults);
        }

        return $response->withJson(['results' => $results]);
    }
}