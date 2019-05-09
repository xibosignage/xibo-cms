<?php

namespace Xibo\Widget;

use Xibo\Factory\ModuleFactory;

/**
 * Class SavedReport
 * @package Xibo\Widget
 */
class SavedReport extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Saved Reports';
            $module->type = 'savedreport';
            $module->class = 'Xibo\Widget\SavedReport';
            $module->description = 'A saved report to be stored in the library';
            $module->enabled = 1;
            $module->previewEnabled = 0;
            $module->assignable = 0;
            $module->regionSpecific = 0;
            $module->renderAs = null;
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 10;
            $module->validExtensions = 'json';
            $module->settings = [];
            $module->installName = 'savedreport';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /** @inheritdoc */
    public function edit()
    {
        // Non-editable
    }

    /**
     * Preview code for a module
     * @param int $width
     * @param int $height
     * @param int $scaleOverride The Scale Override
     * @return string The Rendered Content
     */
    public function preview($width, $height, $scaleOverride = 0)
    {
        // Videos are never previewed in the browser.
        return $this->previewIcon();
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        $this->download();
    }

    /**
     * Is this module valid
     * @return int
     */
    public function isValid()
    {
        // Yes
        return 1;
    }
}
