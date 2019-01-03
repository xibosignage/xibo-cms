<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (LayoutHelperTrait.php)
 */


namespace Xibo\Tests\Helper;


use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Exception\XiboApiException;

/**
 * Trait LayoutHelperTrait
 * @package Helper
 */
trait LayoutHelperTrait
{
    /**
     * @param int|null $status
     * @return XiboLayout
     */
    protected function createLayout($status = null)
    {
        // Create a Layout for us to work with.
        $layout = (new XiboLayout($this->getEntityProvider()))
            ->create(
                Random::generateString(),
                'Layout to test Cache Invalidation',
                '',
                9
            );

        $this->getLogger()->debug('Layout created with name ' . $layout->layout);

        if ($status !== null) {
            // Set the initial status of this Layout to Built
            $this->setLayoutStatus($layout, $status);
        }

        return $layout;
    }

    /**
     * @param XiboLayout $layout
     * @param int $status
     */
    protected function setLayoutStatus($layout, $status)
    {
        $layout->status = $status;
        $this->getStore()->update('UPDATE `layout` SET `status` = :status WHERE layoutId = :layoutId', ['layoutId' => $layout->layoutId, 'status' => $status]);
        $this->getStore()->commitIfNecessary();
    }

    /**
     * Build the Layout ready for XMDS
     * @param XiboLayout $layout
     */
    protected function buildLayout($layout)
    {
        // Call the status route
        $this->getEntityProvider()->get('/layout/status/' . $layout->layoutId);
    }

    /**
     * @param XiboLayout $layout
     */
    protected function deleteLayout($layout)
    {
        $layout->delete();
    }

    /**
     * @param XiboLayout $layout
     * @param int $status
     * @return bool
     */
    protected function layoutStatusEquals($layout, $status)
    {
        // Requery the Display
        try {
            $check = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);

            $this->getLogger()->debug('Tested Layout ' . $layout->layout . '. Status returned is ' . $check->status);

            return $check->status === $status;

        } catch (XiboApiException $xiboApiException) {
            $this->getLogger()->error('API exception for ' . $layout->layoutId . ': ' . $xiboApiException->getMessage());
            return false;
        }

    }
}