<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner, Spring Signage Ltd
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

use Xibo\Entity\Media;
use Xibo\Entity\Module;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\LibraryFullException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Config;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;
use Xibo\Helper\XiboUploadHandler;
use Xibo\Storage\PDOConnect;


class Library extends Base
{
    /**
     * Displays the page logic
     */
    function displayPage()
    {
        // Default options
        if (Session::Get(get_class(), 'Filter') == 1) {
            $filter_pinned = 1;
            $filter_name = Session::Get('content', 'filter_name');
            $filter_type = Session::Get('content', 'filter_type');
            $filter_retired = Session::Get('content', 'filter_retired');
            $filter_owner = Session::Get('content', 'filter_owner');
            $filter_duration_in_seconds = Session::Get('content', 'filter_duration_in_seconds');
            $showTags = Session::Get('content', 'showTags');
            $filter_showThumbnail = Session::Get('content', 'filter_showThumbnail');
        } else {
            $filter_pinned = 0;
            $filter_name = NULL;
            $filter_type = NULL;
            $filter_retired = 0;
            $filter_owner = NULL;
            $filter_duration_in_seconds = 0;
            $filter_showThumbnail = 0;
            $showTags = 0;
        }

        $data = [
            'defaults' => [
                'name' => $filter_name,
                'type' => $filter_type,
                'retired' => $filter_retired,
                'owner' => $filter_owner,
                'durationInSeconds' => $filter_duration_in_seconds,
                'showTags' => $showTags,
                'showThumbnail' => $filter_showThumbnail,
                'filterPinned' => $filter_pinned
            ]
        ];

        // Users we have permission to see
        $users = $this->getUser()->userList();
        array_unshift($users, array('userid' => '', 'username' => 'All'));
        $data['users'] = $users;

        $types = ModuleFactory::query(['module'], ['regionSpecific' => 0, 'enabled' => 1]);
        array_unshift($types, array('moduleid' => '', 'module' => 'All'));
        $data['modules'] = $types;

        $this->getState()->template = 'library-page';
        $this->getState()->setData($data);
    }

    /**
     * Prints out a Table of all media items
     */
    function grid()
    {
        $user = $this->getUser();

        //Get the input params and store them
        $filter_type = Sanitize::getString('filter_type');
        $filter_name = Sanitize::getString('filter_name');
        $filter_userid = Sanitize::getInt('filter_owner');
        $filter_retired = Sanitize::getInt('filter_retired');
        $filter_duration_in_seconds = Sanitize::getCheckbox('filter_showThumbnail');
        $filter_showThumbnail = Sanitize::getCheckbox('filter_showThumbnail');
        $showTags = Sanitize::getCheckbox('showTags');

        Session::Set('content', 'filter_type', $filter_type);
        Session::Set('content', 'filter_name', $filter_name);
        Session::Set('content', 'filter_owner', $filter_userid);
        Session::Set('content', 'filter_retired', $filter_retired);
        Session::Set('content', 'filter_duration_in_seconds', $filter_duration_in_seconds);
        Session::Set('content', 'filter_showThumbnail', $filter_showThumbnail);
        Session::Set('content', 'showTags', $showTags);
        Session::Set('content', 'Filter', Sanitize::getCheckbox('XiboFilterPinned'));

        // Construct the SQL
        $mediaList = MediaFactory::query($this->gridRenderSort(), $this->gridRenderFilter([
            'type' => $filter_type,
            'name' => $filter_name,
            'ownerId' => $filter_userid,
            'retired' => $filter_retired,
            'showTags' => $showTags
        ]));

        // Add some additional row content
        foreach ($mediaList as $media) {
            /* @var \Xibo\Entity\Media $media */
            $media->revised = ($media->parentId != 0) ? 1 : 0;

            // Thumbnail URL
            $media->thumbnail = '';

            if ($media->mediaType == 'image') {
                $download = $this->urlFor('library.download', ['id' => $media->mediaId]) . '?preview=1';
                $media->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56" /></i></a>';
            }

            $media->buttons = array();

            // Buttons
            if ($user->checkEditable($media)) {
                // Edit
                $media->buttons[] = array(
                    'id' => 'content_button_edit',
                    'url' => $this->urlFor('library.edit.form', ['id' => $media->mediaId]),
                    'text' => __('Edit')
                );
            }

            if ($user->checkDeleteable($media)) {
                // Delete
                $media->buttons[] = array(
                    'id' => 'content_button_delete',
                    'url' => $this->urlFor('library.delete.form', ['id' => $media->mediaId]),
                    'text' => __('Delete')
                );
            }

            if ($user->checkPermissionsModifyable($media)) {
                // Permissions
                $media->buttons[] = array(
                    'id' => 'content_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Media', 'id' => $media->mediaId]),
                    'text' => __('Permissions')
                );
            }

            // Download
            $media->buttons[] = array(
                'id' => 'content_button_download',
                'linkType' => '_self', 'external' => true,
                'url' => $this->urlFor('library.download', ['id' => $media->mediaId]) . '?attachment=' . $media->fileName,
                'text' => __('Download')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($mediaList);
    }

    /**
     * Media Delete Form
     * @param int $mediaId
     */
    public function deleteForm($mediaId)
    {
        $media = MediaFactory::getById($mediaId);

        if (!$this->getUser()->checkDeleteable($media))
            throw new AccessDeniedException();

        $this->getState()->template = 'library-form-delete';
        $this->getState()->setData([
            'media' => $media,
            'help' => Help::Link('Library', 'Delete')
        ]);
    }

    /**
     * Delete Media
     * @param int $mediaId
     */
    public function delete($mediaId)
    {
        $media = MediaFactory::getById($mediaId);

        if (!$this->getUser()->checkDeleteable($media))
            throw new AccessDeniedException();

        // Delete
        $media->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $media->name)
        ]);
    }

    /**
     * Replace media in all layouts.
     * @param <type> $oldMediaId
     * @param <type> $newMediaId
     */
    private function ReplaceMediaInAllLayouts($replaceInLayouts, $replaceBackgroundImages, $oldMediaId, $newMediaId)
    {
        $count = 0;

        Log::notice(sprintf('Replacing mediaid %s with mediaid %s in all layouts', $oldMediaId, $newMediaId), 'module', 'ReplaceMediaInAllLayouts');

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            // Some update statements to use
            $sth = $dbh->prepare('SELECT lklayoutmediaid, regionid FROM lklayoutmedia WHERE mediaid = :media_id AND layoutid = :layout_id');
            $sth_update = $dbh->prepare('UPDATE lklayoutmedia SET mediaid = :media_id WHERE lklayoutmediaid = :lklayoutmediaid');

            // Loop through a list of layouts this user has access to
            foreach ($this->getUser()->LayoutList() as $layout) {
                $layoutId = $layout['layoutid'];

                // Does this layout use the old media id?
                $sth->execute(array(
                    'media_id' => $oldMediaId,
                    'layout_id' => $layoutId
                ));

                $results = $sth->fetchAll();

                if (count($results) <= 0)
                    continue;

                Log::notice(sprintf('%d linked media items for layoutid %d', count($results), $layoutId), 'module', 'ReplaceMediaInAllLayouts');

                // Create a region object for later use (new one each time)
                $layout = new Layout();
                $region = new region($this->db);

                // Loop through each media link for this layout
                foreach ($results as $row) {
                    // Get the LKID of the link between this layout and this media.. could be more than one?
                    $lkId = $row['lklayoutmediaid'];
                    $regionId = $row['regionid'];

                    if ($regionId == 'background') {

                        Log::debug('Replacing background image');

                        if (!$replaceBackgroundImages)
                            continue;

                        // Straight swap this background image node.
                        if (!$layout->EditBackgroundImage($layoutId, $newMediaId))
                            return false;
                    } else {

                        if (!$replaceInLayouts)
                            continue;

                        // Get the Type of this media
                        if (!$type = $region->GetMediaNodeType($layoutId, '', '', $lkId))
                            continue;

                        // Create a new media node use it to swap the nodes over
                        Log::notice('Creating new module with MediaID: ' . $newMediaId . ' LayoutID: ' . $layoutId . ' and RegionID: ' . $regionId, 'region', 'ReplaceMediaInAllLayouts');
                        try {
                            $module = ModuleFactory::createForMedia($type, $newMediaId, $this->db, $this->user);
                        } catch (Exception $e) {
                            Log::error($e->getMessage());
                            return false;
                        }

                        // Sets the URI field
                        if (!$module->SetRegionInformation($layoutId, $regionId))
                            return false;

                        // Get the media xml string to use in the swap.
                        $mediaXmlString = $module->AsXml();

                        // Swap the nodes
                        if (!$region->SwapMedia($layoutId, $regionId, $lkId, $oldMediaId, $newMediaId, $mediaXmlString))
                            return false;
                    }

                    // Update the LKID with the new media id
                    $sth_update->execute(array(
                        'media_id' => $newMediaId,
                        'lklayoutmediaid' => $row['lklayoutmediaid']
                    ));

                    $count++;
                }
            }
        } catch (Exception $e) {

            Log::error($e->getMessage());

            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));

            return false;
        }

        Log::notice(sprintf('Replaced media in %d layouts', $count), 'module', 'ReplaceMediaInAllLayouts');
    }

    /**
     * Displays the Library Assign form
     * @return
     */
    function LibraryAssignForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="content"><input type="hidden" name="q" value="LibraryAssignView">');
        Theme::Set('pager', ApplicationState::Pager($id, 'grid_pager'));

        // Module types filter
        $modules = $this->getUser()->ModuleAuth(0, '', 1);
        $types = array();

        foreach ($modules as $module) {
            $type['moduleid'] = $module['Module'];
            $type['module'] = $module['Name'];

            $types[] = $type;
        }

        array_unshift($types, array('moduleid' => '', 'module' => 'All'));
        Theme::Set('module_field_list', $types);

        // Call to render the template
        $output = Theme::RenderReturn('library_form_assign');

        // Input vars
        $layoutId = Sanitize::getInt('layoutid');
        $regionId = Sanitize::getString('regionid');

        // Construct the Response
        $response->html = $output;
        $response->success = true;
        $response->dialogSize = true;
        $response->dialogClass = 'modal-big';
        $response->dialogWidth = '780px';
        $response->dialogHeight = '580px';
        $response->dialogTitle = __('Assign an item from the Library');

        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Library', 'Assign') . '")');
        $response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutId . '&regionid=' . $regionId . '&q=RegionOptions")');
        $response->AddButton(__('Assign'), 'LibraryAssignSubmit("' . $layoutId . '","' . $regionId . '")');


    }

    /**
     * Show the library
     * @return
     */
    function LibraryAssignView()
    {

        $user = $this->getUser();
        $response = $this->getState();

        //Input vars
        $mediatype = Sanitize::getString('filter_type');
        $name = Sanitize::getString('filter_name');

        // Get a list of media
        $mediaList = $user->MediaList(NULL, array('type' => $mediatype, 'name' => $name));

        $rows = array();

        // Add some extra information
        foreach ($mediaList as $row) {

            $row['duration_text'] = sec2hms($row['duration']);
            $row['list_id'] = 'MediaID_' . $row['mediaid'];

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        // Render the Theme
        $response->SetGridResponse(Theme::RenderReturn('library_form_assign_list'));
        $response->callBack = 'LibraryAssignCallback';
        $response->pageSize = 5;

    }

    /**
     * Add a file to the library
     *  expects to be fed by the blueimp file upload handler
     * @throws \Exception
     */
    public function add()
    {
        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        $this->ensureLibraryExists();

        // Get Valid Extensions
        $validExt = ModuleFactory::getValidExtensions();

        $options = array(
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'oldMediaId' => Sanitize::getInt('oldMediaId'),
            'playlistId' => Sanitize::getInt('playlistId'),
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor('library.add'),
            'upload_url' => $this->urlFor('library.add'),
            'image_versions' => array(),
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i'
        );

        // Make sure there is room in the library
        $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

        if ($libraryLimit > 0 && $this->libraryUsage() > $libraryLimit)
            throw new LibraryFullException(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit));

        // Check for a user quota
        $this->getUser()->isQuotaFullByUser();

        try {
            // Hand off to the Upload Handler provided by jquery-file-upload
            new XiboUploadHandler($options);

        } catch (\Exception $e) {
            // We must not issue an error, the file upload return should have the error object already
            //TODO: for some reason this commits... it shouldn't
            $this->app->commit = false;
        }

        $this->setNoOutput(true);
    }

    /**
     * Edit Form
     * @param int $mediaId
     */
    public function editForm($mediaId)
    {
        $media = MediaFactory::getById($mediaId);

        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        $this->getState()->template = 'library-form-edit';
        $this->getState()->setData([
            'media' => $media,
            'help' => Help::Link('Library', 'Edit')
        ]);
    }

    /**
     * Edit Media
     * @param int $mediaId
     */
    public function edit($mediaId)
    {
        $media = MediaFactory::getById($mediaId);

        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        $media->name = Sanitize::getString('name');
        $media->duration = Sanitize::getInt('duration');
        $media->retired = Sanitize::getCheckbox('retired');
        $media->tags = TagFactory::tagsFromString(Sanitize::getString('tags'));
        $media->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $media->name),
            'id' => $media->mediaId,
            'data' => [$media]
        ]);
    }

    /**
     * Tidy Library
     */
    public function tidyForm()
    {
        if (Config::GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            throw new ConfigurationException(__('Sorry this function is disabled.'));

        // Work out how many files there are
        $media = MediaFactory::query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $size = ByteFormatter::format(array_sum(array_map(function ($element) {
            return $element['fileSize'];
        }, $media)));

        $this->getState()->template = 'library-form-tidy';
        $this->getState()->setData([
            'size' => $size,
            'quantity' => count($media),
            'help' => Help::Link('Content', 'TidyLibrary')
        ]);
    }

    /**
     * Tidies up the library
     */
    public function tidy()
    {
        if (Config::GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            throw new ConfigurationException(__('Sorry this function is disabled.'));

        // Get a list of media that is not in use (for this user)
        $media = MediaFactory::query(null, ['unusedOnly' => 1, 'ownerId' => $this->getUser()->userId]);

        $i = 0;
        foreach ($media as $item) {
            /* @var Media $item */
            $i++;
            $item->load();
            $item->delete();
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('Library Tidy Complete'),
            'countDeleted' => $i
        ]);
    }

    /**
     * Make sure the library exists
     * @throws ConfigurationException when the library is not writable
     */
    public static function ensureLibraryExists()
    {
        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Check that this location exists - and if not create it..
        if (!file_exists($libraryFolder))
            mkdir($libraryFolder, 0777, true);

        if (!file_exists($libraryFolder . '/temp'))
            mkdir($libraryFolder . '/temp', 0777, true);

        if (!file_exists($libraryFolder . '/cache'))
            mkdir($libraryFolder . '/cache', 0777, true);

        if (!file_exists($libraryFolder . '/screenshots'))
            mkdir($libraryFolder . '/screenshots', 0777, true);

        // Check that we are now writable - if not then error
        if (!is_writable($libraryFolder))
            throw new ConfigurationException(__('Library not writable'));
    }

    public static function getLibraryCacheUri()
    {
        return Config::GetSetting('LIBRARY_LOCATION') . '/cache';
    }

    /**
     * Library Usage
     * @return int
     */
    public static function libraryUsage()
    {
        $results = PDOConnect::select('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', array());

        return Sanitize::int($results[0]['SumSize']);
    }

    /**
     * Gets a file from the library
     * @param int $mediaId
     */
    public function download($mediaId)
    {
        $media = MediaFactory::getById($mediaId);

        if (!$this->getUser()->checkViewable($media))
            throw new AccessDeniedException();

        // Make a media module
        $widget = ModuleFactory::createWithMedia($media);
        $widget->GetResource();

        $this->setNoOutput(true);
    }

    /**
     * Installs fonts
     */
    public function installFonts()
    {
        $fontTemplate = '
@font-face {
    font-family: \'[family]\';
    src: url(\'[url]\');
}
        ';

        // Save a fonts.css file to the library for use as a module
        $fonts = MediaFactory::getByMediaType('font');

        if (count($fonts) < 1)
            return;

        $css = '';
        $localCss = '';
        $ckEditorString = '';

        foreach ($fonts as $font) {
            /* @var Media $font */

            // Css for the client contains the actual stored as location of the font.
            $css .= str_replace('[url]', $font['storedAs'], str_replace('[family]', $font['name'], $fontTemplate));

            // Css for the local CMS contains the full download path to the font
            $url = $this->urlFor('module.getResource', ['type' => 'font', 'id' => $font->mediaId]) . '?download=1&downloadFromLibrary=1';
            $localCss .= str_replace('[url]', $url, str_replace('[family]', $font['name'], $fontTemplate));

            // CKEditor string
            $ckEditorString .= $font['name'] . '/' . $font['name'] . ';';
        }

        file_put_contents('modules/preview/fonts.css', $css);

        // Install it (doesn't expire, isn't a system file, force update)
        $media = MediaFactory::createModuleFile('fonts.css', 'modules/preview/fonts.css');
        $media->expires = 0;
        $media->moduleSystemFile = true;
        $media->force = true;
        $media->save();

        // Generate a fonts.css file for use locally (in the CMS)
        file_put_contents('modules/preview/fonts.css', $localCss);

        // Edit the CKEditor file
        $ckEditor = file_get_contents('theme/default/libraries/ckeditor/config.js');
        $replace = "/*REPLACE*/ config.font_names = '" . $ckEditorString . "' + config.font_names; /*ENDREPLACE*/";

        $ckEditor = preg_replace('/\/\*REPLACE\*\/.*?\/\*ENDREPLACE\*\//', $replace, $ckEditor);

        file_put_contents('theme/default/libraries/ckeditor/config.js', $ckEditor);
    }

    /**
     * Installs all files related to the enabled modules
     */
    public static function installAllModuleFiles()
    {
        // Do this for all enabled modules
        foreach (ModuleFactory::query() as $module) {
            /* @var Module $module */

            // Install Files for this module
            $moduleObject = ModuleFactory::create($module->name);
            $moduleObject->InstallFiles();
        }
    }

    /**
     * Removes all expired media files
     */
    public function removeExpiredFiles()
    {
        // Get a list of all expired files and delete them
        foreach (MediaFactory::query(null, array('expires' => time(), 'allModules' => 1)) as $entry) {
            /* @var \Xibo\Entity\Media $entry */
            // If the media type is a module, then pretend its a generic file
            $entry->load();
            $entry->delete();
        }
    }
}
