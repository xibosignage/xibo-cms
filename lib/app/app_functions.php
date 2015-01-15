<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
 
define('VAR_FOR_SQL',1);

define('MSG_MODE_MANUAL',2);
define('MSG_MODE_AUTO',1);

define('AJAX_REDIRECT',3);
define('AJAX_SUCCESS_NOREDIRECT',4);
define('AJAX_SUCCESS_REFRESH',5);
define('AJAX_LOAD_FORM',6);

/**
 * Sets a message to display, this is checked when each page loads
 *
 * @param string $message
 */
function setMessage($message) {
	if (!isset($_SESSION['message'])) $_SESSION['message'] = "";
	$_SESSION['message'] = $message;
}

function listcontent($list_string, $list_name, $selected = "", $callback = "") 
{
	//generates a list based on a list option | value, list
	if ($list_string == "") return "Empty list content";
	
	$list_string = rtrim($list_string,","); //clean up
	
	$list_values = explode(",", $list_string); //gives us each option value pair
	
	$list = <<<END
	<select name="$list_name" id="$list_name" $callback>
END;
	foreach ($list_values as $list_option) 
	{
	
		$option = explode("|", $list_option);
		
		$col0 = $option[0];
		$col1 = $option[1];

		if ($col0 == $selected) 
		{
			$list .= "<option value='" . $col0 . "' selected>" . $col1 . "</option>\n";
		}
		else 
		{
			$list .= "<option value='" . $col0 . "'>" . $col1 . "</option>\n";
		}
	}
	$list .= "</select>\n";
		
	return $list;
}


/**
 * Sets a session variable from a javascript call (so when we XMLHTTPRequest we can set a sesson var)
 * @return 
 * @param $page Object
 * @param $var Object
 * @param $value Object
 */
function setSession($page, $var, $value) 
{
	$_SESSION[$page][$var] = $value;

	return true;
}

function sec2hms($sec, $padHours = false) 
{
	// holds formatted string
	$hms = "";

	// there are 3600 seconds in an hour, so if we
	// divide total seconds by 3600 and throw away
	// the remainder, we've got the number of hours
	$hours = intval(intval($sec) / 3600);

	// add to $hms, with a leading 0 if asked for
	$hms .= ($padHours) ? str_pad($hours, 2, "0", STR_PAD_LEFT) . ':':$hours . ':';

	// dividing the total seconds by 60 will give us
	// the number of minutes, but we're interested in
	// minutes past the hour: to get that, we need to
	// divide by 60 again and keep the remainder
	$minutes = intval(($sec / 60) % 60);

	// then add to $hms (with a leading 0 if needed)
	$hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT) . ':';

	// seconds are simple - just divide the total
	// seconds by 60 and keep the remainder
	$seconds = intval($sec % 60);

	// add to $hms, again with a leading 0 if needed
	$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

	// done!
	return $hms;
}

/**
 * Resizes the image
 * Based on code from the Web - cannot find source.
 * If this is your code please send a mail to info@xibo.org.uk
 * to arrange for correct acknowledgement to be printed.
 * @return 
 * @param $file Object The Source File
 * @param $target Object The Target File
 * @param $width Object[optional] The Width
 * @param $height Object[optional] The Height
 * @param $proportional Object[optional] Proportional Resize
 * @param $output Object[optional] file|browser|return
 * @param $delete_original Object[optional]
 * @param $use_linux_commands Object[optional]
 */
function ResizeImage( $file, $target = "", $width = 0, $height = 0, $proportional = false, $output = 'file', $delete_original = false, $use_linux_commands = false )
{
    if ( $height <= 0 && $width <= 0 ) 
	{
        return false;
    }

    $info = getimagesize($file);
    $image = '';

    $final_width = 0;
    $final_height = 0;
    list($width_old, $height_old) = $info;

    if ($proportional) 
	{
        if ($width == 0) $factor = $height/$height_old;
        elseif ($height == 0) $factor = $width/$width_old;
        else $factor = min ( $width / $width_old, $height / $height_old);   

        $final_width = round ($width_old * $factor);
        $final_height = round ($height_old * $factor);

    }
    else 
	{
        $final_width = ( $width <= 0 ) ? $width_old : $width;
        $final_height = ( $height <= 0 ) ? $height_old : $height;
    }

    switch ( $info[2] ) 
	{
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($file);
        break;
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($file);
        break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($file);
        break;
        default:
            return false;
    }
    
    $image_resized = imagecreatetruecolor( $final_width, $final_height );
            
    if ( ($info[2] == IMAGETYPE_GIF) || ($info[2] == IMAGETYPE_PNG) ) 
	{
        $trnprt_indx = imagecolortransparent($image);

        // If we have a specific transparent color
        if ($trnprt_indx >= 0) 
		{

            // Get the original image's transparent color's RGB values
            $trnprt_color    = imagecolorsforindex($image, $trnprt_indx);

            // Allocate the same color in the new image resource
            $trnprt_indx    = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);

            // Completely fill the background of the new image with allocated color.
            imagefill($image_resized, 0, 0, $trnprt_indx);

            // Set the background color for new image to transparent
            imagecolortransparent($image_resized, $trnprt_indx);
        } 
        // Always make a transparent background color for PNGs that don't have one allocated already
        elseif ($info[2] == IMAGETYPE_PNG) 
		{
            // Turn off transparency blending (temporarily)
            imagealphablending($image_resized, false);

            // Create a new transparent color for image
            $color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);

            // Completely fill the background of the new image with allocated color.
            imagefill($image_resized, 0, 0, $color);

            // Restore transparency blending
            imagesavealpha($image_resized, true);
        }
    }

    imagecopyresampled($image_resized, $image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);

    if ( $delete_original ) 
	{
        if ( $use_linux_commands )
            exec('rm '.$file);
        else
            @unlink($file);
    }
    
    switch ( strtolower($output) ) 
	{
        case 'browser':
            $mime = image_type_to_mime_type($info[2]);
            header("Content-type: $mime");
            $output = NULL;
        break;
        case 'file':
            $output = $target;
        break;
        case 'return':
            return $image_resized;
        break;
        default:
        break;
    }

    switch ( $info[2] ) {
        case IMAGETYPE_GIF:
            imagegif($image_resized, $output);
        break;
        case IMAGETYPE_JPEG:
            imagejpeg($image_resized, $output, 70);
        break;
        case IMAGETYPE_PNG:
            imagepng($image_resized, $output, 5);
        break;
        default:
            return false;
    }

    return true;
}

/**
* Creates a form token
* @return 
*/
function CreateFormToken($tokenName = "token")
{
	//Store in the users session
	$token = md5(uniqid()."xsmsalt".time());
	
	$_SESSION[$tokenName] = $token;
	$_SESSION[$tokenName.'_timeout'] = time();
	
	return $token;
}

/**
 * Checks a form token
 * @param string token
 * @return 
 */
function CheckFormToken($token, $tokenName = "token")
{
	global $db;
	
	if ($token == $_SESSION[$tokenName])
	{
		// See if its still in Date
		if (($_SESSION[$tokenName.'_timeout'] + 1200) <= time())
		{
			return false;
		}
		return true;
	}
	else
	{
		unset($_SESSION[$tokenName]);

		Debug::LogEntry('error', "Form token incorrect from: ". $_SERVER['REMOTE_ADDR']. " with token [$token] for session_id [" . session_id() . ']');
		return false;
	}
}

/**
 * Convert a shorthand byte value from a PHP configuration directive to an integer value
 * @param    string   $value
 * @return   int
 */
function convertBytes( $value ) 
{
    if ( is_numeric( $value ) ) 
	{
        return $value;
    } 
	else 
	{
        $value_length = strlen( $value );
        $qty = substr( $value, 0, $value_length - 1 );
        $unit = strtolower( substr( $value, $value_length - 1 ) );
        switch ( $unit ) 
		{
            case 'k':
                $qty *= 1024;
                break;
            case 'm':
                $qty *= 1048576;
                break;
            case 'g':
                $qty *= 1073741824;
                break;
        }
        return $qty;
    }
}
?>