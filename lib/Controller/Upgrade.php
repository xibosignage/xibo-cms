<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (Upgrade.php) is part of Xibo.
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
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\UpgradeFactory;
use Xibo\Helper\Date;
use Xibo\Helper\Log;

class Upgrade extends Base
{
    public $errorMessage;

    public function displayPage()
    {
        // Assume we will show the upgrade page
        $this->getState()->template = 'upgrade-page';

        // Is there a pending upgrade (i.e. are there any pending upgrade steps).
        $steps = UpgradeFactory::getIncomplete();

        if (count($steps) <= 0) {
            // No pending steps, check to see if we need to insert them
            if (DBVERSION === WEBSITE_VERSION) {
                $this->getState()->template = 'upgrade-not-required-page';
                return;
            }

            if ($this->getUser()->userTypeId != 1) {
                $this->getState()->template = 'upgrade-in-progress-page';
                return;
            }

            // Insert pending upgrade steps.
            $steps = UpgradeFactory::createSteps(DBVERSION, WEBSITE_VERSION);

            foreach ($steps as $step) {
                /* @var \Xibo\Entity\Upgrade $step */
                $step->save();
            }

        }

        // We have pending steps to process, show them in a list
        $this->getState()->setData([
           'steps' => $steps
        ]);
    }

    /**
     * Do Step
     * @param int $stepId
     * @throws \Exception
     */
    public function doStep($stepId)
    {
        // Check we are a super admin
        if ($this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        // Get upgrade step
        $upgradeStep = UpgradeFactory::getByStepId($stepId);

        try {
            $upgradeStep->doStep();
            $upgradeStep->complete = 1;
            $upgradeStep->lastTryDate = Date::getLocalDate();
            $upgradeStep->save();
        }
        catch (\Exception $e) {
            $upgradeStep->lastTryDate = Date::getLocalDate();
            $upgradeStep->save();
            Log::error('Unable to run upgrade step. Message = %s', $e->getMessage());

            throw $e;
        }

        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Complete')
        ]);
    }
}