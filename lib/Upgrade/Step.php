<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Step.php)
 */


namespace Xibo\Upgrade;


interface Step
{
    public static function doStep();
}