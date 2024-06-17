<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Widget\DataType;

use Xibo\Widget\Definition\DataType;

/**
 * Social Media DataType
 */
class SocialMedia implements \JsonSerializable, DataTypeInterface
{
    public static $NAME = 'social-media';
    public $text;
    public $user;
    public $userProfileImage;
    public $userProfileImageMini;
    public $userProfileImageBigger;
    public $location;
    public $screenName;
    public $date;
    public $photo;

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'text' => $this->text,
            'user' => $this->user,
            'userProfileImage' => $this->userProfileImage,
            'userProfileImageMini' => $this->userProfileImageMini,
            'userProfileImageBigger' => $this->userProfileImageBigger,
            'location' => $this->location,
            'screenName' => $this->screenName,
            'date' => $this->date,
            'photo' => $this->photo,
        ];
    }

    public function getDefinition(): DataType
    {
        $dataType = new DataType();
        $dataType->id = self::$NAME;
        $dataType->name = __('Social Media');
        $dataType
            ->addField('text', __('Text'), 'text', true)
            ->addField('user', __('User'), 'text')
            ->addField('userProfileImage', __('Profile Image'), 'image')
            ->addField('userProfileImageMini', __('Mini Profile Image'), 'image')
            ->addField('userProfileImageBigger', __('Bigger Profile Image'), 'image')
            ->addField('location', __('Location'), 'text')
            ->addField('screenName', __('Screen Name'), 'text')
            ->addField('date', __('Date'), 'datetime')
            ->addField('photo', __('Photo'), 'image');
        return $dataType;
    }
}
