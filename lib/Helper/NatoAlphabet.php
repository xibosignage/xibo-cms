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

namespace Xibo\Helper;

class NatoAlphabet
{
    public static function convertToNato($word) {

        $replacement =  [
            "a"=>"Alpha", "b"=>"Bravo", "c"=>"Charlie",
            "d"=>"Delta", "e"=>"Echo", "f"=>"Foxtrot",
            "g"=>"Golf", "h"=>"Hotel", "i"=>"India",
            "j"=>"Juliet", "k"=>"Kilo", "l"=>"Lima",
            "m"=>"Mike", "n"=>"November", "o"=>"Oscar",
            "p"=>"Papa", "q"=>"Quebec", "r"=>"Romeo",
            "s"=>"Sierra", "t"=>"Tango", "u"=>"Uniform",
            "v"=>"Victor", "w"=>"Whiskey", "x"=>"X-Ray",
            "y"=>"Yankee", "z"=>"Zulu", "0"=>"Zero",
            "1"=>"One", "2"=>"Two", "3"=>"Three",
            "4"=>"Four", "5"=>"Five", "6"=>"Six",
            "7"=>"Seven", "8"=>"Eight", "9"=>"Nine",
            "-"=>"Dash", " "=>"(Space)"
        ];

        $converted = [];

        for ($i=0; $i < strlen($word); $i++) {
            $currentLetter  = substr($word, $i, 1);

            if (!empty($replacement[$currentLetter])) {
                $convertedWord = strtolower($replacement[$currentLetter]);
            } elseif (!empty($replacement[strtolower($currentLetter)])) {
                $convertedWord = $replacement[strtolower($currentLetter)];
            } else {
                $convertedWord = $currentLetter;
            }
            $converted[] = $convertedWord;
        }

        return implode(' ', $converted);
    }
}
