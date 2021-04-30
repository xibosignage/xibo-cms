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

    /**
     * Get selected Menu Board Categories
     * @return MenuBoardCategory[]
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function menuBoardCategoriesSelected()
    {
        if ($this->getOption('menuId') == 0) {
            throw new InvalidArgumentException(__('Menu Board not selected'));
        }

        $categories = $this->menuBoardCategoryFactory->getByMenuId($this->getOption('menuId'));
        $categoriesSelected = [];
        $categoriesIds = explode(',', $this->getOption('categories'));

        // Cycle elements of the ordered category Ids array $categoriesIds
        foreach ($categoriesIds as $categoryId) {
            // Cycle Menu Board categories $categories
            foreach ($categories as $category) {
                // See if the element on the ordered list is the category
                if ($category->menuCategoryId == $categoryId) {
                    $categoriesSelected[] = $category;
                }
            }
        }

        return $categoriesSelected;
    }

    /**
     * Get Not selected Menu Board Categories
     * @return MenuBoardCategory[]
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function menuBoardCategoriesNotSelected()
    {
        if ($this->getOption('menuId') == 0) {
            throw new InvalidArgumentException(__('Menu Board not selected'));
        }

        $categories = $this->menuBoardCategoryFactory->getByMenuId($this->getOption('menuId'));

        $categoriesNotSelected = [];
        $categoriesIds = explode(',', $this->getOption('categories'));

        foreach ($categories as $category) {
            /* @var MenuBoardCategory $category */
            if (!in_array($category->menuCategoryId, $categoriesIds)) {
                $categoriesNotSelected[] = $category;
            }
        }

        return $categoriesNotSelected;
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

        $categories = $this->menuBoardCategoriesSelected();
        $numOfColumns = $this->getOption('numOfColumns');
        $categoriesInColumns = [];
        $notAssignedCategories = [];

        for ($i = 1; $i <= $numOfColumns; $i++) {
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

    /** @inheritdoc */
    public function getExtra()
    {
        $menuBoard = $this->menuBoardFactory->getById($this->getOption('menuId'));
        $menuBoardCategories = $this->getMenuBoardCategories();

        $selectedCategories = $this->menuBoardCategoriesSelected();
        $products = [];

        foreach ($selectedCategories as $category) {
            foreach ($category->getProducts() as $product) {
                if ($product->availability === 1) {
                    $products[] = $product;
                }
            }
        }

        return [
            'menuBoard' => $menuBoard,
            'menuCategories' => $menuBoardCategories,
            'templates' => $this->templatesAvailable(true),
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
        } elseif ($step == 2 || $this->getOption('categories') == '') {
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
        } elseif ($step == 2) {
            // Categories
            $categories = $sanitizedParams->getIntArray('menuBoardCategories', ['default' => []]);

            if (count($categories) == 0) {
                $this->setOption('categories', '');
                $this->clearColumnCategories();
            } else {
                if (implode(',', $categories) != $this->getOption('categories')) {
                    $this->clearColumnCategories();
                }
                $this->setOption('categories', implode(',', $categories));
            }

            $this->setOption('numOfColumns', $sanitizedParams->getInt('numOfColumns', ['default' => 1]));
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

            $this->setOption('templateId', $sanitizedParams->getString('templateId'));
            $this->setOption('overrideTemplate', $sanitizedParams->getCheckbox('overrideTemplate'));
            $this->setOption('showImagesFor', $sanitizedParams->getString('showImagesFor'));


            $this->setRawNode('noDataMessage', $request->getParam('noDataMessage', ''));
            $this->setOption('noDataMessage_advanced', $sanitizedParams->getCheckbox('noDataMessage_advanced'));
            $this->setRawNode('javaScript', $request->getParam('javaScript', ''));

            $this->setOption('showMenuBoardName', $sanitizedParams->getCheckbox('showMenuBoardName'));
            $this->setOption('showMenuCategoryName', $sanitizedParams->getCheckbox('showMenuCategoryName'));
            $this->setOption('showProductOptions', $sanitizedParams->getCheckbox('showProductOptions'));
            $this->setOption('showProductDescription', $sanitizedParams->getCheckbox('showProductDescription'));
            $this->setOption('showProductAllergyInformation', $sanitizedParams->getCheckbox('showProductAllergyInformation'));
            $this->setOption('showUnavailable', $sanitizedParams->getCheckbox('showUnavailable'));

            $this->setOption('backgroundColor', $sanitizedParams->getString('backgroundColor'));
            $this->setOption('fontColorMenu', $sanitizedParams->getString('fontColorMenu'));
            $this->setOption('fontColorCategory', $sanitizedParams->getString('fontColorCategory'));
            $this->setOption('fontColorProduct', $sanitizedParams->getString('fontColorProduct'));
            $this->setOption('fontColorUnavailableProduct', $sanitizedParams->getString('fontColorUnavailableProduct'));
            $this->setOption('fontColorHighlightProduct', $sanitizedParams->getString('fontColorHighlightProduct'));

            $this->setOption('fontFamily', $sanitizedParams->getString('fontFamily'));
            $this->setOption('fontSizeMenu', $sanitizedParams->getInt('fontSizeMenu'));
            $this->setOption('fontSizeCategory', $sanitizedParams->getInt('fontSizeCategory'));
            $this->setOption('fontSizeProduct', $sanitizedParams->getInt('fontSizeProduct'));
            $this->setOption('fontSizeProductDescription', $sanitizedParams->getInt('fontSizeProductDescription'));
            $this->setOption('fontSizeProductUnavailable', $sanitizedParams->getInt('fontSizeProductUnavailable'));
            $this->setOption('fontSizeProductHighlight', $sanitizedParams->getInt('fontSizeProductHighlight'));

            $this->setOption('numOfRows', $sanitizedParams->getInt('numOfRows', ['default' => 1]));
            $this->setOption('productsPerPage', $sanitizedParams->getInt('productsPerPage', ['default' => 0]));

            if ($this->getOption('overrideTemplate') == 1) {
                $this->setRawNode('styleSheet', $request->getParam('styleSheet', null));
            }

            for ($i = 1; $i <= $this->getOption('numOfColumns'); $i++) {
                $this->setOption('categories_' . $i, implode(',', $sanitizedParams->getIntArray('menuBoardCategories_' . $i, ['default' => []])));
            }

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
        $templateOverrode = false;
        if ($this->getOption('overrideTemplate', 1) == 0) {
            $template = $this->getTemplateById($this->getOption('templateId'));

            if (isset($template)) {
                $styleSheet = $template['css'];
            }
        } else {
            $styleSheet = $this->getRawNode('styleSheet', '');
            $templateOverrode = true;
        }

        $styleSheet = $this->parseLibraryReferences($this->isPreview(), $styleSheet);

        // If we have some options then add them to the end of the style sheet

        if ($this->getOption('backgroundColor') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardContainer { background-color: ' . $this->getOption('backgroundColor') . '; }';
        }

        if ($this->getOption('fontColorMenu') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardName { color: ' . $this->getOption('fontColorMenu') . '; }';
        }

        if ($this->getOption('fontColorCategory') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardCategoryContainer  { color: ' . $this->getOption('fontColorCategory') . '; }';
        }

        if ($this->getOption('fontColorProduct') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardProductContainer { color: ' . $this->getOption('fontColorProduct') . '; }';
        }

        if ($this->getOption('fontColorUnavailableProduct') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardProductContainer.product-unavailable { color: ' . $this->getOption('fontColorUnavailableProduct') . '; }';
        }

        if ($this->getOption('fontColorHighlightProduct') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardProductContainer.product-highlight { color: ' . $this->getOption('fontColorHighlightProduct') . '; }';
        }

        if ($this->getOption('fontFamily') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardContainer { font-family: ' . $this->getOption('fontFamily') . '; }';
        }

        if ($this->getOption('fontSizeMenu') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardName { font-size: ' . $this->getOption('fontSizeMenu') . 'px; }';
        }

        if ($this->getOption('fontSizeCategory') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardCategoryContainer { font-size: ' . $this->getOption('fontSizeCategory') . 'px; }';
        }

        if ($this->getOption('fontSizeProduct') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardProductContainer { font-size: ' . $this->getOption('fontSizeProduct') . 'px; }';
        }

        if ($this->getOption('fontSizeProductDescription') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardProductContainer.MenuBoardProductAllergyInfo { font-size: ' . $this->getOption('fontSizeProductDescription') . 'px; }';
            $styleSheet .= '.MenuBoardProductContainer.MenuBoardProductDescription { font-size: ' . $this->getOption('fontSizeProductDescription') . 'px; }';
        }

        if ($this->getOption('fontSizeProductUnavailable') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardProductContainer.product-unavailable { font-size: ' . $this->getOption('fontSizeProductUnavailable') . 'px; }';
        }

        if ($this->getOption('fontSizeProductHighlight') != '' && !$templateOverrode) {
            $styleSheet .= '.MenuBoardProductContainer.product-highlight { font-size: ' . $this->getOption('fontSizeProductHighlight') . 'px; }';
        }
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
                'maxPages' => $table['pages'],
                'productsPerPage' => $this->getOption('productsPerPage'),
                'originalWidth' => $this->region->width,
                'originalHeight' => $this->region->height,
                'generatedOn' => Carbon::now()->format('c'),
                'noDataMessage' => $this->noDataMessageOrDefault('')['html']
            ])
            ->appendJavaScript('
                $(document).ready(function() {
                    $("body").xiboLayoutScaler(options); 
                    const runOnVisible = function() { $(".MenuBoardContainer").menuBoardRender(options);  };
                    (xiboIC.checkVisible()) ? runOnVisible() : xiboIC.addToQueue(runOnVisible);
                    $(".MenuBoardContainer").find("img").xiboImageRender(options);
                });
            ')
            ->appendJavaScript($this->parseLibraryReferences($this->isPreview(), $this->getRawNode('javaScript', '')))
            ->appendBody($table['html']);


        return $this->finaliseGetResource();
    }

    private function menuBoardHtml($displayId = 0)
    {
        // Show a preview of the Menu Board
        if ($this->hasMenu()) {
            $menuId = $this->getOption('menuId');
            $menuBoard = $this->menuBoardFactory->getById($menuId);
        }

        $categories = $this->getOption('categories');

        if ($categories == '') {
            return $this->noDataMessageOrDefault(__('No categories selected'));
        }
        $table = '';
        $maxPages = 1;

        try {
            if ($this->getOption('showMenuBoardName') == 1 && $this->hasMenu()) {
                $table .= '<h1 id="MenuBoardName" class="MenuBoardName">' . $menuBoard->name . '</h1>';
            }

            $table .= '<div class="row" style="display: flex;">';

            for ($i = 1; $i <= $this->getOption('numOfColumns'); $i++) {
                $categoryIds = array_filter(explode(',', $this->getOption('categories_' . $i)));
                if ($categoryIds != []) {
                    $table .= '<div class="MenuBoardContainer" id="MenuBoardContainer_' . $i . '">';
                }

                foreach ($categoryIds as $categoryId) {
                    $rowCount = 1;
                    // Get the category
                    $category = $this->menuBoardCategoryFactory->getById((int)$categoryId);

                    // get category products, depending on the showUnavailable fetch all or only available products
                    if ($this->getOption('showUnavailable') == 0) {
                        $categoryProductsData = $category->getAvailableProducts();
                    } else {
                        $categoryProductsData = $category->getProducts();
                    }

                    $table .= '<div class="MenuBoardCategoryContainer">';

                    if ($this->getOption('showMenuCategoryName') == 1) {
                        $table .= '<h2 class="MenuBoardCategoryName">' . $category->name . '</h2>';
                    }

                    if (in_array($this->getOption('showImagesFor'), ['all', 'category'])) {
                        try {
                            $file = $this->mediaFactory->getById($category->mediaId);

                            // Already in the library - assign this mediaId to the Layout immediately.
                            $this->assignMedia($file->mediaId);

                            $replace = ($this->isPreview())
                                ? '<img src="' . $this->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                                : '<img src="' . $file->storedAs . '" />';

                            $table .= '<p class="MenuBoardMedia MenuBoardCategoryMedia" id="category_media_' . $category->menuCategoryId . '">' . $replace . '</p>';
                        } catch (NotFoundException $e) {
                            //
                        }
                    }

                    // close MenuBoardCategoryContainer
                    $table .= '</div>';
                    $table .= '<div class="ProductsContainer">';
                    $productPerRowCount = 1;

                    // if we should show specific number of products per page
                    // then calculate the max number of pages for each category products,
                    // this is then passed to jQuery cycle to calculate cycle timeout
                    if ($this->getOption('productsPerPage') > 0) {
                        $numberOfPages = ceil(count($categoryProductsData) / $this->getOption('productsPerPage'));

                        if ($numberOfPages > $maxPages) {
                            $maxPages = $numberOfPages;
                        }
                    }

                    foreach ($categoryProductsData as $categoryProduct) {
                        // paging, if we have more than one page to show for this category products then wrap products html in a div
                        if ($this->getOption('productsPerPage') > 0 && $rowCount === 1 && count($categoryProductsData) > $this->getOption('productsPerPage')) {
                            $table .= '<div class="page">';
                        }

                        // if we have more than one product to show in a row, then wrap required number of products in a div
                        if ($this->getOption('numOfRows') > 1 && $productPerRowCount == 1) {
                            $table .= '<div class="row" style="display: flex;">';
                        }

                        // depending on configured options, we will want to assign different css classes to the MenuBoardProductContainer
                        if ($categoryProduct->availability === 0 && $this->getOption('showUnavailable') == 0) {
                            continue;
                        } elseif ($categoryProduct->availability === 0 && $this->getOption('showUnavailable') == 1) {
                            $table .= '<div class="MenuBoardProductContainer product-unavailable">';
                        } elseif (in_array($categoryProduct->menuProductId, explode(',', $this->getOption('highlightProducts')))) {
                            $table .= '<div class="MenuBoardProductContainer product-highlight">';
                        } else {
                            $table .= '<div class="MenuBoardProductContainer">';
                        }

                        // Product name and price should always be visible.
                        $table .= '<div class="MenuBoardProduct MenuBoardProductName" id="productName_' . $i . '"><span class="MenuBoardProductSpan_' . $rowCount . '_' . $i . '" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $categoryProduct->name . '</span></div>';
                        $table .= '<div class="MenuBoardProduct MenuBoardProductPrice" id="productPrice_' . $i . '"><span class="MenuBoardProductSpan_' . $rowCount . '_' . $i . '" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $categoryProduct->price . '</span></div>';

                        // Product options, description, allergy info and images visibility depends on the Widget configuration
                        if ($this->getOption('showProductOptions') == 1) {
                            foreach ($categoryProduct->getOptions() as $productOption) {
                                $table .= '<div class="MenuBoardProduct MenuBoardProductOptions" id="productOptions_' . $i . '"><span class="MenuBoardProductSpan_' . $rowCount . '_' . $i . '" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $productOption->option . ' ' . $productOption->value . '</span></div>';
                            }
                        }

                        if ($this->getOption('showProductDescription')) {
                            $table .= '<div class="MenuBoardProduct MenuBoardProductDescription" id="productDescription_' . $i . '"><span class="MenuBoardProductSpan_' . $rowCount . '_' . $i . '" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $categoryProduct->description . '</span></div>';
                        }

                        if ($this->getOption('showProductAllergyInformation')) {
                            $table .= '<div class="MenuBoardProduct MenuBoardProductAllergyInfo" id="productAllergyInfo_' . $i . '"><span class="MenuBoardProductSpan_' . $rowCount . '_' . $i . '" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $categoryProduct->allergyInfo . '</span></div>';
                        }

                        if (in_array($this->getOption('showImagesFor'), ['all', 'product'])) {
                            try {
                                $file = $this->mediaFactory->getById($categoryProduct->mediaId);

                                // Already in the library - assign this mediaId to the Layout immediately.
                                $this->assignMedia($file->mediaId);

                                $replace = ($this->isPreview())
                                    ? '<img src="' . $this->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                                    : '<img src="' . $file->storedAs . '" />';


                                $table .= '<div class="MenuBoardMedia MenuBoardProductMedia" id="productMedia_' . $i . '"><span class="MenuBoardProductSpan_' . $rowCount . '_' . $i . '" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $replace . '</span></div>';
                            } catch (NotFoundException $e) {
                                $table .= '</div>';
                                continue;
                            }
                        }

                        // if we should show more than one product in a row, see if reached the requested number of products per row yet, if yes close the corresponding div
                        if ($this->getOption('numOfRows') > 1 && $this->getOption('numOfRows') == $productPerRowCount) {
                            $table .= '</div>';
                            $productPerRowCount = 0;
                        }

                        // if we have more than one page, then check how many products we already have, if it's equals to the productsPerPage then close the page div
                        if ($this->getOption('productsPerPage') > 0 && $rowCount == $this->getOption('productsPerPage')) {
                            $table .= '</div>';
                            $rowCount = 0;
                        }

                        $rowCount++;
                        $productPerRowCount++;

                        // close MenuBoardProductContainer
                        $table .= '</div>';
                    }
                    // close ProductsContainer
                    $table .= '</div>';

                    // if we have only one category to go through, close the MenuBoardContainer container
                    if (count($categoryIds) == 1) {
                        $table .= '</div>';
                    }
                }
                // with more than one category, close the row MenuBoardContainer here.
                if (count($categoryIds) > 1) {
                    $table .= '</div>';
                }
            }

            // close the row div
            $table .= '</div>';
            return [
                'html' => $table,
                'pages' => $maxPages
            ];
        } catch (NotFoundException $e) {
            $this->getLog()->info(sprintf('Request failed for MenuBoard id=%d. Widget=%d. Due to %s', $menuId, $this->getWidgetId(), $e->getMessage()));
            $this->getLog()->debug($e->getTraceAsString());

            return $this->noDataMessageOrDefault();
        }
    }

    /**
     * Does this module have a Menu yet?
     * @return bool
     */
    private function hasMenu()
    {
        return (v::notEmpty()->validate($this->getOption('menuId')));
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

        if ($this->getRawNode('noDataMessage') == '') {
            throw new NotFoundException($default);
        } else {
            return [
                'html' => $this->getRawNode('noDataMessage'),
                'countPages' => 1,
                'countRows' => 1
            ];
        }
    }

    private function clearColumnCategories()
    {
        for ($i = 1; $i <= $this->getOption('numOfColumns', 1); $i++) {
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
