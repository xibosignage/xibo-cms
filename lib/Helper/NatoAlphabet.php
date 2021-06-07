<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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
