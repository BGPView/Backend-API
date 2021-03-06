<?php

// We need to also include the netdns2 PEAR lib
require_once 'Net/DNS2.php';

$dnsResolver = new Net_DNS2_Resolver(['nameservers' => ['127.0.0.1']]);

define( 'WHOIS_EOL', "\r\n" );
define( 'WHOIS_PORT', 43 );

// Needs to be in CDIR format (same as BGP announcement)
$input = $_GET['input'];

// Cleanup input
$inputParts = explode("/", $input);
// Check if IP
if(filter_var($inputParts[0], FILTER_VALIDATE_IP)) {
    if (isset($inputParts[1]) === true) {
        $finalInput = $inputParts[0] . "/" . $inputParts[1];
    } else {
        if (!filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            $finalInput = $inputParts[0] . "/48";
        } else {
            $finalInput = $inputParts[0] . "/24";
        }
    }
} else {
    $finalInput = "AS".str_ireplace("as", "", $inputParts[0]);
}

$whois_server = strtolower($_GET['whois_server']);

switch (strtolower($whois_server)) {
    case 'whois.ripe.net':
    case 'whois.afrinic.net';
        $finalInput .= " -B";
        break;
    case 'whois.arin.net':
        if (stristr($finalInput, 'as')) {
            $finalInput = "a + ".str_ireplace('as', '', $finalInput);
        } else {
            $finalInput = "r + ".$finalInput;
        }
        break;
}

$rand_ip = '[2a06:9f81:'.rand(1, 9999).':f001:e141:f435:1010:deed]';
$socket_options = array( 'socket' => array('bindto' => $rand_ip.':0') );
$socket_context = stream_context_create($socket_options);


echo "Source: ".$rand_ip.PHP_EOL;

// $dns = dns_get_record($whois_server, DNS_AAAA);
$dnsResults = $dnsResolver->query($whois_server, 'AAAA');
$whois_server_ip = null;
foreach ($dnsResults->answer as $answer) {
    if ($answer->type === 'AAAA') {
        $whois_server_ip = $answer->address;
        break;
    }
}

if (empty($whois_server_ip) === true) {
    die('Could not get an AAAA for '.$whois_server);
}

$whois_server_ip = "[" . $whois_server_ip . "]";

if ($whois_server == 'whois.apnic.net') {
    $whois_server_ip = '[2001:dc0:2001:11::220]';
}


$fp = stream_socket_client( 'tcp://'.$whois_server_ip.':'.WHOIS_PORT, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $socket_context );
$out = "";
if( $fp ) {
    fwrite( $fp, $finalInput.WHOIS_EOL );
    while( !feof($fp) )
        $out .= fgets($fp);
    fclose( $fp );
}

if (strstr($input, ":")) {
    $minCidr = 48;
} else {
    $minCidr = 24;
}


// Here we are making sure only the smallest subnet gets whois's
if ($whois_server == 'whois.arin.net') {
    $outParts = explode("# end", $out);
    $currentInt = 0;
    $currentKey = 0;
    foreach ($outParts as $key => $part) {
        $rawLines = explode("\n", $part);
        foreach ($rawLines as $line) {
            $lineParts = explode(":", $line, 2);
            if (strtolower($lineParts[0]) == 'cidr') {
                $nets = explode(",", trim($lineParts[1]));
                foreach ($nets as $net) {
                    $netMask = explode("/", trim($net))[1];
                    if ($netMask > $currentInt && $netMask <= $minCidr) {
                        $currentKey = $key;
                        $currentInt = $netMask;
                    }
                }
            }
        }

    }
    $out = trim($outParts[$currentKey]);
} else if ($whois_server == 'whois.apnic.net') {
    // ASN Specific
    if (stristr($input, "as")) {
        $outParts = explode("% Information related to", $out);
        foreach ($outParts as $key => $part) {
            // If there is 'aut-num' AND 'as-block' lets remove the AS-BLOCK whois block
            if (stristr($part, "as-block:") && stristr($out, 'aut-num')) {
                unset($outParts[$key]);
            }
        }
        $out = implode("% Information related to", $outParts);
        if (substr_count($out, 'as-block:')) {
            $outParts = explode("% Information related to", $out);
            $out = "% Information related to" . end($outParts);
        }
    } else if (stristr($out, 'inetnum:') || stristr($out, 'inet6num:')) {
        $outParts = explode("% Information related to", $out);
        foreach ($outParts as $outPart) {
            if (stristr($outPart, 'inetnum:') || stristr($outPart, 'inet6num:')) {
                $out = "% Information related to" . $outPart;
            }
        }

    }

    // remove the "route"
    $outParts = explode("\n", $out);
    foreach ($outParts as $outPart) {
        if (stristr($outPart, "% Information related to '") && strstr($outPart, "AS") ) {
            $newParts = explode($outPart, $out);
            foreach ($newParts as $key => $val) {
                $val = trim($val);
                if (strrpos($val, "route", -strlen($val)) !== false) {
                    unset($newParts[$key]);
                }
            }
            $out = implode($outPart, $newParts);
            break;
        }
    }

} else if ($whois_server == 'whois.afrinic.net') {
    // ASN Specific
    if (stristr($input, "as")) {
        $outParts = explode("% Information related to", $out);
        foreach ($outParts as $key => $part) {
            // If there is 'aut-num' AND 'as-block' lets remove the AS-BLOCK whois block
            if (stristr($part, "as-block:") && stristr($out, 'aut-num')) {
                unset($outParts[$key]);
            }
        }
        $out = implode("% Information related to", $outParts);
        if (substr_count($out, 'as-block:')) {
            $outParts = explode("% Information related to", $out);
            $out = "% Information related to" . end($outParts);
        }
    }

    // remove the "route"
    $outParts = explode("\n", $out);
    foreach ($outParts as $outPart) {
        if (stristr($outPart, "% Information related to '") && strstr($outPart, "AS") ) {
            $newParts = explode($outPart, $out);
            foreach ($newParts as $key => $val) {
                $val = trim($val);
                if (strrpos($val, "route", -strlen($val)) !== false) {
                    unset($newParts[$key]);
                }
            }
            $out = implode($outPart, $newParts);
            break;
        }
    }
} else if ($whois_server == 'whois.ripe.net') {
    // ASN Specific
    if (stristr($input, "as")) {
        $outParts = explode("% Information related to", $out);
        foreach ($outParts as $key => $part) {
            // If there is 'aut-num' AND 'as-block' lets remove the AS-BLOCK whois block
            if (stristr($part, "as-block:") && stristr($out, 'aut-num:')) {
                unset($outParts[$key]);
            }
        }
        $out = implode("% Information related to", $outParts);
        if (substr_count($out, 'as-block:')) {
            $outParts = explode("% Information related to", $out);
            $out = "% Information related to" . end($outParts);
        }
    }

    // remove the "route"
    $outParts = explode("\n", $out);
    foreach ($outParts as $outPart) {
        if (stristr($outPart, "% Information related to '") && strstr($outPart, "AS") ) {
            $newParts = explode($outPart, $out);
            foreach ($newParts as $key => $val) {
                $val = trim($val);
                if (strrpos($val, "route", -strlen($val)) !== false) {
                    unset($newParts[$key]);
                }
            }
            $out = implode($outPart, $newParts);
            break;
        }
    }
}


echo $out;
