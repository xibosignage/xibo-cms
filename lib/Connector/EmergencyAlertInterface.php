<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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

namespace Xibo\Connector;

/**
 * Connector Interface for Emergency Alerts
 */
interface EmergencyAlertInterface
{
    /**
     * Represents the status when there is at least one alert of type "Actual".
     */
    public const ACTUAL_ALERT = 'actual_alerts';

    /**
     * Represents the status when there are no alerts of any type.
     */
    public const NO_ALERT = 'no_alerts';

    /**
     * Represents the status when there is at least one test alert
     * (e.g., Exercise, System, Test, Draft).
     */
    public const TEST_ALERT = 'test_alerts';
}
