<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DayPart.php)
 */


namespace Xibo\Controller;

use Xibo\Factory\DayPartFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class DayPart
 * @package Xibo\Controller
 */
class DayPart extends Base
{
    /** @var  DayPartFactory */
    private $dayPartFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param DayPartFactory $dayPartFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dayPartFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dayPartFactory = $dayPartFactory;
    }

    /**
     * View Route
     */
    public function displayPage()
    {
        $this->getState()->template = 'daypart-page';
    }

    /**
     * Search
     */
    public function grid()
    {
        $filter = [
            'dayPartId' => $this->getSanitizer()->getInt('dayPartId'),
            'name' => $this->getSanitizer()->getString('name')
        ];

        $dayParts = $this->dayPartFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($dayParts as $dayPart) {
            /* @var \Xibo\Entity\DayPart $dayPart */

            if ($this->isApi())
                break;

            $dayPart->includeProperty('buttons');

            // Default Layout
            $dayPart->buttons[] = array(
                'id' => 'daypart_button_edit',
                'url' => $this->urlFor('daypart.edit.form', ['id' => $dayPart->dayPartId]),
                'text' => __('Edit')
            );

            if ($this->getUser()->checkDeleteable($dayPart)) {
                $dayPart->buttons[] = array(
                    'id' => 'daypart_button_delete',
                    'url' => $this->urlFor('daypart.delete.form', ['id' => $dayPart->dayPartId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('daypart.delete', ['id' => $dayPart->dayPartId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'daypart_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $dayPart->name)
                    )
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->dayPartFactory->countLast();
        $this->getState()->setData($dayParts);
    }
}