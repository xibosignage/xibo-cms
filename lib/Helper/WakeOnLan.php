<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (WakeOnLan.php)
 */


namespace Xibo\Helper;


use Xibo\Service\LogServiceInterface;

class WakeOnLan
{
    /**
     * Wake On Lan Script
     * @param string $macAddress
     * @param string $secureOn
     * @param string $address
     * @param int $cidr
     * @param int $port
     * @param LogServiceInterface $logger
     * @version 2
     * @author DS508_customer (http://www.synology.com/enu/forum/memberlist.php?mode=viewprofile&u=12636)
     * Please inform the author of any suggestions on (the functionality, graphical design, ... of) this application.
     * More info: http://wolviaphp.sourceforge.net
     * @licence GPLv2.0
     * @throws \Exception
     *
     * Modified for use with the Xibo project by Dan Garner.
     */
    public static function TransmitWakeOnLan($macAddress, $secureOn, $address, $cidr, $port, $logger) {

        // Prepare magic packet: part 1/3 (defined constant)
        $buf = "";

        // the defined constant as represented in hexadecimal: FF FF FF FF FF FF (i.e., 6 bytes of hexadecimal FF)
        for ($a=0; $a<6; $a++) $buf .= chr(255);

        // Check whether $mac_address is valid
        $macAddress = strtoupper($macAddress);
        $macAddress = str_replace(":", "-", $macAddress);

        if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/",$macAddress)) || (strlen($macAddress) != 17))
        {
            throw new \Exception(__('Pattern of MAC-address is not "xx-xx-xx-xx-xx-xx" (x = digit or letter)'));
        }
        else
        {
            // Prepare magic packet: part 2/3 (16 times MAC-address)
            // Split MAC-address into an array of (six) bytes
            $addr_byte = explode('-', $macAddress);
            $hw_addr = "";

            // Convert MAC-address from bytes to hexadecimal to decimal
            for ($a=0; $a<6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a]));

            $hw_addr_string = "";

            for ($a=0; $a<16; $a++) $hw_addr_string .= $hw_addr;
            $buf .= $hw_addr_string;
        }

        if ($secureOn != "")
        {
            // Check whether $secureon is valid
            $secureOn = strtoupper($secureOn);
            $secureOn = str_replace(":", "-", $secureOn);

            if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/", $secureOn)) || (strlen($secureOn) != 17))
            {
                throw new \Exception(__('Pattern of SecureOn-password is not "xx-xx-xx-xx-xx-xx" (x = digit or CAPITAL letter)'));
            }
            else
            {
                // Prepare magic packet: part 3/3 (Secureon password)
                // Split MAC-address into an array of (six) bytes
                $addr_byte = explode('-', $secureOn);
                $hw_addr = "";

                // Convert MAC address from hexadecimal to decimal
                for ($a=0; $a<6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a]));
                $buf .= $hw_addr;
            }
        }

        // Fill $addr with client's IP address, if $addr is empty
        if ($address == "")
            throw new \Exception(__('No IP Address Specified'));

        // Resolve broadcast address
        if (filter_var ($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) // same as (but easier than):  preg_match("/\b(([01]?\d?\d|2[0-4]\d|25[0-5])\.){3}([01]?\d?\d|2[0-4]\d|25[0-5])\b/",$addr)
        {
            // $addr has an IP-adres format
        }
        else
        {
            throw new \Exception(__('IP Address Incorrectly Formed'));
        }

        // If $cidr is set, replace $addr for its broadcast address
        if ($cidr != "")
        {
            // Check whether $cidr is valid
            if ((!ctype_digit($cidr)) || ($cidr < 0) || ($cidr > 32))
            {
                throw new \Exception(__('CIDR subnet mask is not a number within the range of 0 till 32.'));
            }

            // Convert $cidr from one decimal to one inverted binary array
            $inverted_binary_cidr = "";

            // Build $inverted_binary_cidr by $cidr * zeros (this is the mask)
            for ($a=0; $a<$cidr; $a++) $inverted_binary_cidr .= "0";

            // Invert the mask (by postfixing ones to $inverted_binary_cidr untill 32 bits are filled/ complete)
            $inverted_binary_cidr = $inverted_binary_cidr.substr("11111111111111111111111111111111", 0, 32 - strlen($inverted_binary_cidr));

            // Convert $inverted_binary_cidr to an array of bits
            $inverted_binary_cidr_array = str_split($inverted_binary_cidr);

            // Convert IP address from four decimals to one binary array
            // Split IP address into an array of (four) decimals
            $addr_byte = explode('.', $address);
            $binary_addr = "";

            for ($a=0; $a<4; $a++)
            {
                // Prefix zeros
                $pre = substr("00000000",0,8-strlen(decbin($addr_byte[$a])));

                // Postfix binary decimal
                $post = decbin($addr_byte[$a]);
                $binary_addr .= $pre.$post;
            }

            // Convert $binary_addr to an array of bits
            $binary_addr_array = str_split($binary_addr);

            // Perform a bitwise OR operation on arrays ($binary_addr_array & $inverted_binary_cidr_array)
            $binary_broadcast_addr_array="";

            // binary array of 32 bit variables ('|' = logical operator 'or')
            for ($a=0; $a<32; $a++) $binary_broadcast_addr_array[$a] = ($binary_addr_array[$a] | $inverted_binary_cidr_array[$a]);

            // build binary address of four bundles of 8 bits (= 1 byte)
            $binary_broadcast_addr = chunk_split(implode("", $binary_broadcast_addr_array), 8, ".");

            // chop off last dot ('.')
            $binary_broadcast_addr = substr($binary_broadcast_addr,0,strlen($binary_broadcast_addr)-1);

            // binary array of 4 byte variables
            $binary_broadcast_addr_array = explode(".", $binary_broadcast_addr);
            $broadcast_addr_array = "";

            // decimal array of 4 byte variables
            for ($a=0; $a<4; $a++) $broadcast_addr_array[$a] = bindec($binary_broadcast_addr_array[$a]);

            // broadcast address
            $address = implode(".", $broadcast_addr_array);
        }

        // Check whether $port is valid
        if ((!ctype_digit($port)) || ($port < 0) || ($port > 65536))
            throw new \Exception(__('Port is not a number within the range of 0 till 65536. Port Provided: ' . $port));

        // Check whether UDP is supported
        if (!array_search('udp', stream_get_transports()))
            throw new \Exception(__('No magic packet can been sent, since UDP is unsupported (not a registered socket transport)'));

        // Ready to send the packet
        if (function_exists('fsockopen'))
        {
            // Try fsockopen function - To do: handle error 'Permission denied'
            $socket = fsockopen("udp://" . $address, $port, $errno, $errstr);

            if ($socket)
            {
                $socket_data = fwrite($socket, $buf);

                if ($socket_data)
                {
                    $function = "fwrite";
                    $sent_fsockopen = "A magic packet of ".$socket_data." bytes has been sent via UDP to IP address: ".$address.":".$port.", using the '".$function."()' function.";
                    $content = bin2hex($buf);

                    $sent_fsockopen = $sent_fsockopen."Contents of magic packet:".strlen($content)." ".$content;
                    fclose($socket);

                    unset($socket);

                    $logger->notice($sent_fsockopen, 'display', 'WakeOnLan');
                    return true;
                }
                else
                {
                    unset($socket);

                    throw new \Exception(__('Using "fwrite()" failed, due to error: ' . $errstr.  ' ("' . $errno . '")'));
                }
            }
            else
            {
                unset($socket);

                $logger->notice(__('Using fsockopen() failed, due to denied permission'));
            }
        }

        // Try socket_create function
        if (function_exists('socket_create'))
        {
            // create socket based on IPv4, datagram and UDP
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

            if ($socket)
            {
                // to enable manipulation of options at the socket level (you may have to change this to 1)
                $level = SOL_SOCKET;

                // to enable permission to transmit broadcast datagrams on the socket (you may have to change this to 6)
                $optname = SO_BROADCAST;

                $optval = true;
                $opt_returnvalue = socket_set_option($socket, $level, $optname, $optval);

                if ($opt_returnvalue < 0)
                {
                    throw new \Exception(__('Using "socket_set_option()" failed, due to error: ' . socket_strerror($opt_returnvalue)));
                }

                $flags = 0;

                // To do: handle error 'Operation not permitted'
                $socket_data = socket_sendto($socket, $buf, strlen($buf), $flags, $address, $port);

                if ($socket_data)
                {
                    $function = "socket_sendto";
                    $socket_create = "A magic packet of ". $socket_data . " bytes has been sent via UDP to IP address: ".$address.":".$port.", using the '".$function."()' function.<br>";

                    $content = bin2hex($buf);
                    $socket_create = $socket_create . "Contents of magic packet:" . strlen($content) ." " . $content;

                    socket_close($socket);
                    unset($socket);

                    $logger->notice($socket_create, 'display', 'WakeOnLan');
                    return true;
                }
                else
                {
                    $error = __('Using "socket_sendto()" failed, due to error: ' . socket_strerror(socket_last_error($socket)) . ' (' . socket_last_error($socket) . ')');
                    socket_close($socket);
                    unset($socket);

                    throw new \Exception($error);
                }
            }
            else
            {
                throw new \Exception(__('Using "socket_sendto()" failed, due to error: ' . socket_strerror(socket_last_error($socket)) . ' (' . socket_last_error($socket) . ')'));
            }
        }
        else
        {
            throw new \Exception(__('Wake On Lan Failed as there are no functions available to transmit it'));
        }
    }
}