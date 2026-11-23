<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
