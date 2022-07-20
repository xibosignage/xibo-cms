<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

namespace Xibo\Widget;

use Carbon\Carbon;
use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\MenuBoardCategory;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class MenuBoard extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /**
     * @inheritDoc
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Menu Board';
            $module->type = 'menuboard';
            $module->class = 'Xibo\Widget\MenuBoard';
            $module->description = 'Module for displaying Menu Boards';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->validExtensions = '';
            $module->settings = [];
            $module->installName = 'menuboard';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * @inheritDoc
     */
    public function installFiles()
    {
        // Extends parent's method
        parent::installFiles();

        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-cycle-2.1.6.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-menuboard-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
    }

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        return 'menuboard-designer-javascript';
    }

    /**
     * Get MenuBoard object, used by TWIG template.
     *
     * @return array
     * @throws NotFoundException
     */
    public function getMenuBoard()
    {
        if ($this->getOption('menuId') != 0) {
            return [$this->menuBoardFactory->getById($this->getOption('menuId'))];
        } else {
            return null;
        }
    }

    /**
     * Get Menu Board Categories
     * @return MenuBoardCategory[]
     * @throws NotFoundException
     */
    public function getMenuBoardCategories()
    {
        return $this->menuBoardCategoryFactory->getByMenuId($this->getOption('menuId'));
    }

    public function getTemplatesWithInfo()
    {
        // Get templates with filter option
        $templates = $this->templatesAvailable(true);

        foreach ($templates as $template) {
            $template['orientation'] = $this->getTemplateOrientation($template['id']);
        }

        return $templates;
    }

    public function menuBoardCategoriesSelectedAssigned($columnId)
    {
        if ($this->getOption('menuId') == 0) {
            throw new InvalidArgumentException(__('Menu Board not selected'));
        }

        $return = [];

        $categories = $this->menuBoardCategoryFactory->getByMenuId($this->getOption('menuId'));
        $categoriesInColumn = explode(',', $this->getOption('categories_' . $columnId));

        foreach ($categories as $category) {
            if (in_array($category->menuCategoryId, $categoriesInColumn)) {
                $return[] = $category;
            }
        }

        return $return;
    }

    public function menuBoardCategoriesSelectedNotAssigned()
    {
        if ($this->getOption('menuId') == 0) {
            throw new InvalidArgumentException(__('Menu Board not selected'));
        }

        $categories = $this->getMenuBoardCategories();
        $categoriesInColumns = [];
        $notAssignedCategories = [];

        for ($i = 1; $i <= $this->getOption('templateZones'); $i++) {
            foreach (array_filter(explode(',', $this->getOption('categories_' . $i))) as $categoryId) {
                $categoriesInColumns[] = $categoryId;
            }
        }

        foreach ($categories as $category) {
            if (!in_array($category->menuCategoryId, $categoriesInColumns)) {
                $notAssignedCategories[] = $category;
            }
        }

        return $notAssignedCategories;
    }

    private function getTemplateInfo($templateId = null)
    {
        $templateInfo = [];
        $templateId = ($templateId) ? $templateId : $this->getOption('templateId');
        $template = $this->getTemplateById($templateId);

        if (isset($template)) {
            $templateInfo = array_key_exists('info', $template) ? $template['info'] : [];
        }

        return $templateInfo;
    }

    private function getTemplateOrientation($templateId = null)
    {
        $templateOrientation = 'landscape';
        $templateId = ($templateId) ? $templateId : $this->getOption('templateId');
        $template = $this->getTemplateById($templateId);

        if (isset($template)) {
            $templateOrientation = array_key_exists('orientation', $template) ? $template['orientation'] : $templateOrientation;
        }

        return $templateOrientation;
    }

    /** @inheritdoc */
    public function getExtra()
    {
        $menuBoard = $this->menuBoardFactory->getById($this->getOption('menuId'));
        $menuBoardCategories = $this->getMenuBoardCategories();
        
        $products = [];
        foreach ($menuBoardCategories as $category) {
            foreach ($category->getProducts() as $product) {
                if ($product->availability === 1) {
                    $products[] = $product;
                }
            }
        }

        // Get selected template info
        $templateInfo = $this->getTemplateInfo();
        $templateOptions = array_key_exists('options', $templateInfo) ? $templateInfo['options'] : [];
        $templateGrid = array_key_exists('grid-template', $templateInfo) ? $templateInfo['grid-template'] : '';
        $templateFlex = array_key_exists('flex-template', $templateInfo) ? $templateInfo['flex-template'] : '';
        $templateFlexSize = array_key_exists('flex-size', $templateInfo) ? $templateInfo['flex-size'] : '';

        // Translate name for each option
        foreach ($templateOptions as $key => $option) {
            $templateOptions[$key]['name'] = __($option['name']);
        }

        return [
            'menuBoard' => $menuBoard,
            'menuCategories' => $menuBoardCategories,
            'templateOptions' => $templateOptions,
            'gridTemplate' => $templateGrid,
            'flexTemplate' => $templateFlex,
            'flexTemplateSize' => $templateFlexSize,
            'products' => $products,
            'highlightProducts' => explode(',', $this->getOption('highlightProducts'))
        ];
    }

    /**
     * Validate
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::intType()->min(1, true)->validate($this->getDuration())) {
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');
        }
    }


    /** @inheritdoc @override */
    public function editForm(Request $request)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Do we have a step provided?
        $step = $sanitizedParams->getInt('step', ['default' => 3]);

        if ($step == 1 || !$this->hasMenu()) {
            return 'menuboard-designer-form-edit-step1';
        } elseif ($step == 2 || !$this->hasCategoriesAssigned()) {
            return 'menuboard-designer-form-edit-step2';
        } else {
            return 'menuboard-designer-form-edit';
        }
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?menuboard",
     *  operationId="WidgetMenuBoardEdit",
     *  tags={"widget"},
     *  summary="Edit a Menu Board Widget",
     *  description="Edit Menu Board Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Widget Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="Select only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The Widget Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="alignH",
     *      in="formData",
     *      description="Horizontal alignment - left, center, bottom",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="alignV",
     *      in="formData",
     *      description="Vertical alignment - top, middle, bottom",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Do we have a step provided?
        $step = $sanitizedParams->getInt('step', ['default' => 3]);

        if ($step == 1) {
            // Read in the menuId, validate and store it
            $menuId = $sanitizedParams->getInt('menuId');

            // Do we already have a Menu?
            if ($this->hasMenu() && $menuId != $this->getOption('menuId')) {
                // Reset the fields that are dependent on the menuId
                $this->setOption('categories', '');
                $this->_clearColumnCategories();
            }

            $this->setOption('menuId', $menuId);

            // Validate Menu Board Selected
            if ($menuId == 0) {
                throw new InvalidArgumentException(__('Please select a Menu Board'), 'menuId');
            }

            // Check we have permission to use this menuId
            if (!$this->getUser()->checkViewable($this->menuBoardFactory->getById($this->getOption('menuId')))) {
                throw new InvalidArgumentException(__('You do not have permission to use that Menu Board'), 'menuId');
            }

            // Template
            $template = $sanitizedParams->getString('templateId');

            // Validate Menu Board Selected
            if (!$template) {
                throw new InvalidArgumentException(__('Please select a template'), 'templateId');
            }

            // Did we change template?
            if ($template != $this->getOption('templateId')) {
                $this->_clearColumnCategories();
            }
            $this->setOption('templateId', $template);

            $this->setOption('orientation', $sanitizedParams->getString('orientation'));

            // Set template zones and layout structure
            $templateInfo = $this->getTemplateInfo();
            $templateZones = array_key_exists('zones', $templateInfo) ? $templateInfo['zones'] : 1;
            $this->setOption('templateZones', $templateZones);
        } elseif ($step == 2) {
            $categoriesAssigned = '';
            // Categories
            for ($i = 1; $i <= $this->getOption('templateZones'); $i++) {
                $this->setOption('categories_' . $i, implode(',', $sanitizedParams->getIntArray('menuBoardCategories_' . $i, ['default' => []])));
                $categoriesAssigned .= $this->getOption('categories_' . $i);
            }

            // Validate Menu Board Selected
            if ($categoriesAssigned == '') {
                throw new InvalidArgumentException(__('Please assign some Categories to the Menu structure'), 'structure');
            }

            // Store all categories assigned
            $this->setOption('categoriesAssigned', $categoriesAssigned);
        } else {
            $highlightProducts = $sanitizedParams->getIntArray('productsHighlight', ['default' => []]);

            if (count($highlightProducts) == 0) {
                $this->setOption('highlightProducts', '');
            } else {
                $this->setOption('highlightProducts', implode(',', $highlightProducts));
            }

            // Other properties
            $this->setOption('name', $sanitizedParams->getString('name'));
            $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
            $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
            $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
            $this->setOption('updateInterval', $sanitizedParams->getInt('updateInterval', ['default' => 120]));
            $this->setOption('alignH', $sanitizedParams->getString('alignH', ['default' => 'center']));
            $this->setOption('alignV', $sanitizedParams->getString('alignV', ['default' => 'middle']));

            // Template options
            $templateInfo = $this->getTemplateInfo();
            $templateOptions = array_key_exists('options', $templateInfo) ? $templateInfo['options'] : [];

            foreach ($templateOptions as $key => $option) {
                if ($option['type'] === 'checkbox' || $option['type'] === 'switch') {
                    $optionValue = $sanitizedParams->getCheckbox($key);
                } elseif ($sanitizedParams->getString($key) == '') {
                    $optionValue = $option['default'];
                } else {
                    $optionValue = $sanitizedParams->getString($key);
                }

                $this->setOption($key, $optionValue);
            }

            $this->setOption('showUnavailable', $sanitizedParams->getCheckbox('showUnavailable'));
            $this->setOption('fontFamily', $sanitizedParams->getString('fontFamily'));

            // Validate
            $this->isValid();
        }

        // Save the widget
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width)
            ->appendJavaScriptFile('vendor/jquery.min.js')
            ->appendJavaScriptFile('vendor/jquery-cycle-2.1.6.min.js')
            ->appendJavaScriptFile('xibo-layout-scaler.js')
            ->appendJavaScriptFile('xibo-menuboard-render.js')
            ->appendJavaScriptFile('xibo-image-render.js')
            ->appendJavaScript('var xiboICTargetId = ' . $this->getWidgetId() . ';')
            ->appendJavaScriptFile('xibo-interactive-control.min.js')
            ->appendJavaScript('xiboIC.lockAllInteractions()')
            ->appendFontCss()
            ->appendCss(file_get_contents($this->getConfig()->uri('css/client.css', true)));

        // Get CSS from the original template or from the input field
        $styleSheet = '';
        $template = $this->getTemplateById($this->getOption('templateId'));

        if (!$template) {
            return;
        }

        $widgetOriginalWidth = null;
        $widgetOriginalHeight = null;
        $styleSheet = $template['css'];

        $widgetOriginalWidth = $template['widgetOriginalWidth'];
        $widgetOriginalHeight = $template['widgetOriginalHeight'];

        $styleSheet = $this->parseLibraryReferences($this->isPreview(), $styleSheet);

        // Parse stylesheet values using options
        $styleSheet = $this->parseCSSProperties($styleSheet);

        // Calculate duration
        $duration = $this->getCalculatedDurationForGetResource();

        // Generate the table
        $table = $this->menuBoardHtml($displayId);

        // Replace and Control Meta options
        $this
            ->appendControlMeta('DURATION', $duration)
            ->appendCss($styleSheet)
            ->appendOptions([
                'type' => $this->getModuleType(),
                'duration' => $duration,
                'originalWidth' => $this->region->width,
                'originalHeight' => $this->region->height,
                'widgetDesignWidth' => $widgetOriginalWidth,
                'widgetDesignHeight' => $widgetOriginalHeight,
                'generatedOn' => Carbon::now()->format('c'),
                'alignmentH' => $this->getOption('alignH'),
                'alignmentV' => $this->getOption('alignV')
            ])
            ->appendJavaScript('
                $(document).ready(function() {
                    $("body").xiboLayoutScaler(options); 
                    const runOnVisible = function() { $(".menu-board-parent-container").menuBoardRender(options);  };
                    (xiboIC.checkVisible()) ? runOnVisible() : xiboIC.addToQueue(runOnVisible);
                    $(".menu-board-parent-container").find("img").xiboImageRender(options);
                });
            ')->appendBody($table['html']);


        return $this->finaliseGetResource();
    }

    private function menuBoardHtml($displayId = 0)
    {
        // Show a preview of the Menu Board
        if ($this->hasMenu()) {
            $menuId = $this->getOption('menuId');
            $menuBoard = $this->menuBoardFactory->getById($menuId);
        } else {
            return $this->noDataMessageOrDefault('No menu selected');
        }

        if (!$this->hasCategoriesAssigned()) {
            return $this->noDataMessageOrDefault('No categories selected');
        }

        $menu = '';

        try {
            // Main menu container
            $menu .= '<div class="menu-board-parent-container">';

            // Menu board name
            $menu .= '<div id="menuBoardName" class="menu-board-name"><div class="menu-board-name-text">' . $menuBoard->name . '</div></div>';

            // Get template option property
            $templateInfo = $this->getTemplateInfo();
            $gridTemplate = array_key_exists('grid-template', $templateInfo) ? $templateInfo['grid-template'] : '';
            $templateFlex = array_key_exists('flex-template', $templateInfo) ? $templateInfo['flex-template'] : '';
            $templateFlexSize = array_key_exists('flex-size', $templateInfo) ? $templateInfo['flex-size'] : '';
            $legacyTemplate = array_key_exists('legacy', $templateInfo) ? $templateInfo['legacy'] : false;

            // Menu categories container
            $menu .= "<div class='menu-board-categories-container' ";
            if ($gridTemplate) {
                $menu .= "style='display: grid; grid-template: " . $gridTemplate . "; flex-grow: 1; overflow: hidden;'";
            }
            if ($templateFlex) {
                $menu .= "style='" . $templateFlex . "'";
            }
            $menu .= ' >';

            // Create zones
            for ($i = 1; $i <= $this->getOption('templateZones'); $i++) {
                $categoryIds = array_filter(explode(',', $this->getOption('categories_' . $i)));
        
                $menu .= '<div class="menu-board-zone" id="menuBoardZone_' . $i . '" ';
                if ($gridTemplate) {
                    $menu .= 'style="grid-area: z' . $i . '";';
                }
                if ($templateFlex) {
                    $menu .= 'style="flex:' . $templateFlexSize[$i] . '";';
                }
                $menu .= ' >';

                foreach ($categoryIds as $categoryId) {
                    // Get the category
                    $category = $this->menuBoardCategoryFactory->getById((int)$categoryId);

                    // get category products, depending on the showUnavailable fetch all or only available products
                    if ($this->getOption('showUnavailable') == 0) {
                        $categoryProductsData = $category->getAvailableProducts(['name']);
                    } else {
                        $categoryProductsData = $category->getProducts(['name']);
                    }

                    // Category header
                    $menu .= '<div class="menu-board-category-header">';
                    $menu .= '  <div class="menu-board-category-header-name">' . $category->name . '</div>';
                    
                    // Product image
                    $showImagesForCategories = array_key_exists('categoryImage', $templateInfo) ? $templateInfo['categoryImage'] : false;
                    if ($showImagesForCategories) {
                        try {
                            $file = $this->mediaFactory->getById($category->mediaId);

                            // Already in the library - assign this mediaId to the Layout immediately.
                            $this->assignMedia($file->mediaId);

                            $menu .= ($this->isPreview())
                                ? '<img class="menu-board-category-image" src="' . $this->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                                : '<img class="menu-board-category-image" src="' . $file->storedAs . '" />';
                        } catch (NotFoundException $e) {
                            $this->getLog()->debug('Image for category ' . $category->mediaId . ' failed!');
                        }
                    }

                    $menu .= '</div>';

                    // Products
                    $menu .= '<div class="menu-board-products-container">';

                    // Create products
                    foreach ($categoryProductsData as $key => $categoryProduct) {
                        // depending on configured options, we will want to assign different css classes to the MenuBoardProductContainer
                        if ($categoryProduct->availability === 0 && $this->getOption('showUnavailable') == 0) {
                            continue;
                        } elseif ($categoryProduct->availability === 0 && $this->getOption('showUnavailable') == 1) {
                            $menu .= '<div class="menu-board-product product-unavailable">';
                        } elseif (in_array($categoryProduct->menuProductId, explode(',', $this->getOption('highlightProducts')))) {
                            $menu .= '<div class="menu-board-product product-highlight">';
                        } else {
                            $menu .= '<div class="menu-board-product">';
                        }

                        // Product image
                        $showImagesForProducts = array_key_exists('productImage', $templateInfo) ? $templateInfo['productImage'] : false;
                        if ($showImagesForProducts) {
                            try {
                                $file = $this->mediaFactory->getById($categoryProduct->mediaId);

                                // Already in the library - assign this mediaId to the Layout immediately.
                                $this->assignMedia($file->mediaId);

                                $menu .= ($this->isPreview())
                                    ? '<img class="menu-board-product-image" src="' . $this->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                                    : '<img class="menu-board-product-image" src="' . $file->storedAs . '" />';
                            } catch (NotFoundException $e) {
                                $this->getLog()->debug('Image for product ' . $categoryProduct->mediaId . ' failed!');
                            }
                        }

                        // On legacy templates, we group the info items together
                        if ($legacyTemplate) {
                            $menu .= '<div class="menu-board-product-info">';
                        }

                        // Product name and price should always be visible.
                        $menu .= '<div class="menu-board-product-name" id="productName_' . $i . '"><span>' . $categoryProduct->name . '</span></div>';
                        $menu .= '<div class="menu-board-product-price" id="productPrice_' . $i . '"><span>' . $categoryProduct->price . '</span></div>';

                        // Product options
                        $productOptions = $categoryProduct->getOptions();
                        if ($productOptions) {
                            $menu .= '<div class="menu-board-product-options-container">';
                            foreach ($productOptions as $productOption) {
                                $menu .= '<div class="menu-board-product-options" id="productOptions_' . $i . '"><span>' . $productOption->option . ': ' . $productOption->value . '</span></div>';
                            }
                            // Close menu-board-product-options-container
                            $menu .= '</div>';
                        }

                        // Description
                        if ($categoryProduct->description) {
                            $menu .= '<div class="menu-board-product-description" id="productDescription_' . $i . '"><span>' . $categoryProduct->description . '</span></div>';
                        }

                        // Allergy
                        if ($categoryProduct->allergyInfo) {
                            $menu .= '<div class="menu-board-product-allergy" id="productAllergyInfo_' . $i . '"><span>' . $categoryProduct->allergyInfo . '</span></div>';
                        }

                        // close info group
                        if ($legacyTemplate) {
                            $menu .= '</div>';
                        }

                        // Close menu-board-product
                        $menu .= '</div>';
                    }

                    // Close menu-board-products-container
                    $menu .= '</div>';
                }

                // Close menu-board-zone
                $menu .= '</div>';
            }

            // Close menu-board-categories-container
            $menu .= '</div>';

            // Close menu-board-parent-container
            $menu .= '</div>';

            return [
                'html' => $menu
            ];
        } catch (NotFoundException $e) {
            $this->getLog()->info(sprintf('Request failed for MenuBoard id=%d. Widget=%d. Due to %s', $menuId, $this->getWidgetId(), $e->getMessage()));
            $this->getLog()->debug($e->getTraceAsString());

            return $this->noDataMessageOrDefault();
        }
    }

    /**
     * Parse CSS properties and build CSS based on them
     * @return string
     */
    private function parseCSSProperties($css)
    {
        // Get template option property
        $templateInfo = $this->getTemplateInfo();
        $templateOptions = array_key_exists('options', $templateInfo) ? $templateInfo['options'] : [];

        // We've got something at least, so prepare the template
        $matches = [];
        preg_match_all('/\[.*?\]/', $css, $matches);

        // Run through all [] substitutes in $matches for colors
        foreach ($matches[0] as $sub) {
            // Get option name
            $option = substr($sub, 1, -1);

            // Value from form options or default values from the template
            $value = $this->getOption($option) ? $this->getOption($option) : $templateOptions[$option]['default'];

            // Substitute the replacement we have found (it might be '')
            $css = str_replace($sub, $value, $css);
        }

        // Build override rules based on other options
        foreach ($templateOptions as $key => $option) {
            if (!array_key_exists('rule', $option)) {
                continue;
            }

            $query = array_key_exists('query', $option) ? $option['query'] : ('.' . $key);

            if ($option['rule'] == 'display' && $this->getOption($key, $option['default']) == 0) {
                $css .= ' ' . $query . ' { display: none; } ';
            }
        }

        // Font family
        if ($this->getOption('fontFamily') != '') {
            $css .= ' .menu-board-parent-container { font-family: ' . $this->getOption('fontFamily') . '; }';
        }

        return $css;
    }

    /**
     * Does this module have a Menu yet?
     * @return bool
     */
    private function hasMenu()
    {
        return (v::notEmpty()->validate($this->getOption('menuId')));
    }

    /**
     * Does this module have assigned categories?
     * @return bool
     */
    private function hasCategoriesAssigned()
    {
        return (v::notEmpty()->validate($this->getOption('categoriesAssigned')));
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0) {
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');
        }

        return ($this->hasMenu()) ? self::$STATUS_VALID : self::$STATUS_INVALID;
    }

    /** @inheritdoc */
    public function getModifiedDate($displayId)
    {
        $widgetModifiedDt = $this->widget->modifiedDt;

        if ($this->hasMenu()) {
            $menuId = $this->getOption('menuId');
            $menuBoard = $this->menuBoardFactory->getById($menuId);

            // Set the timestamp
            $widgetModifiedDt = ($menuBoard->modifiedDt > $widgetModifiedDt) ? $menuBoard->modifiedDt : $widgetModifiedDt;

            $menuBoard->setActive();
        }

        return Carbon::createFromTimestamp($widgetModifiedDt);
    }

    /**
     * @param string|null $default
     * @return array
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function noDataMessageOrDefault($default = null)
    {
        if ($default === null) {
            $default = __('Empty Result Set with filter criteria.');
        }

        throw new NotFoundException($default);
    }

    /**
     * Clear all the assigned categories from the manu structure
     */
    private function _clearColumnCategories()
    {
        $this->setOption('categoriesAssigned', '');

        for ($i = 1; $i <= $this->getOption('templateZones'); $i++) {
            $this->setOption('categories_' . $i, '');
        }
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        return 1;
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        // MenuBoards are display specific
        return $this->getWidgetId() . '_' . $displayId;
    }

    /** @inheritdoc */
    public function isCacheDisplaySpecific()
    {
        return true;
    }

    /** @inheritdoc */
    public function getLockKey()
    {
        return $this->getWidgetId() . '_' . $this->getOption('menuId');
    }

    /** @inheritDoc */
    public function hasTemplates()
    {
        return true;
    }

    /** @inheritDoc */
    public function hasHtmlEditor()
    {
        return true;
    }

    /** @inheritDoc */
    public function getHtmlWidgetOptions()
    {
        return ['template'];
    }
}
