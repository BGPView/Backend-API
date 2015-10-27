<?php

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
            $finalInput = "+ ".$finalInput;
        } else {
            $finalInput = "r + ".$finalInput;
        }
        break;
}

$rand_ip = '[ 2a06:1282:'.rand(1, 499).'::deed]';
$socket_options = array( 'socket' => array('bindto' => $rand_ip.':0') );
$socket_context = stream_context_create($socket_options);

$fp = stream_socket_client( 'tcp://'.$whois_server.':'.WHOIS_PORT, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $socket_context );
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
            if (stristr($part, "these AS numbers are further assigned by apnic")) {
                unset($outParts[$key]);
            }
        }
        $out = implode("% Information related to", $outParts);
    }
} else if ($whois_server == 'whois.afrinic.net') {
    // ASN Specific
    if (stristr($input, "as")) {
        $outParts = explode("% Information related to", $out);
        foreach ($outParts as $key => $part) {
            if (stristr($part, "these AS numbers are further assigned by afrinic")) {
                unset($outParts[$key]);
            }
        }
        $out = implode("% Information related to", $outParts);
    }
} else if ($whois_server == 'whois.ripe.net') {
    // ASN Specific
    if (stristr($input, "as")) {
        $outParts = explode("% Information related to", $out);
        foreach ($outParts as $key => $part) {
            if (stristr($part, "these as numbers are assigned to network operators in the ripe")) {
                unset($outParts[$key]);
            }
        }
        $out = implode("% Information related to", $outParts);
    }
}


echo $out;
