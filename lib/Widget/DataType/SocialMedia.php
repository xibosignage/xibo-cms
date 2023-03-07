<?php
/*
 * Copyright (c) 2023  Xibo Signage Ltd
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
 *
 */

namespace Xibo\Widget\DataType;

/**
 * Social Media DataType
 */
class SocialMedia implements \JsonSerializable
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
    public function jsonSerialize()
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

    public static function getSnippets(): array
    {
        return [
            'text',
            'user',
            'userProfileImage',
            'userProfileImageMini',
            'userProfileImageBigger',
            'location',
            'screenName',
            'date',
            'photo',
        ];
    }
}
