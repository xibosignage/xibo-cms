<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (IconDashboard.php)
 */


namespace Xibo\Controller;


class IconDashboard extends Base
{
    public function displayPage()
    {
        $this->getState()->template = 'dashboard-icon-page';
    }
}