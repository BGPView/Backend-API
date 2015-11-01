<?php

namespace App\Helpers;

use App\Models\RirAsnAllocation;
use App\Models\RirIPv4Allocation;
use App\Models\RirIPv6Allocation;

class IpUtils
{

    public function IPv4cidrIpCount($reverse = false)
    {
        // 'cidr' => 'IP count'
        $array = [
            '1'		=>	'2147483648',
            '2'		=>	'1073741824',
            '3'		=>	'536870912',
            '4'		=>	'268435456',
            '5'		=>	'134217728',
            '6'		=>	'67108864',
            '7'		=>	'33554432',
            '8'		=>	'16777216',
            '9'		=>	'8388608',
            '10'	=> 	'4194304',
            '11'	=> 	'2097152',
            '12'	=> 	'1048576',
            '13'	=> 	'524288',
            '14'	=> 	'262144',
            '15'	=> 	'131072',
            '16'	=> 	'65536',
            '17'	=> 	'32768',
            '18'	=> 	'16384',
            '19'	=> 	'8192',
            '20'	=> 	'4096',
            '21'	=> 	'2048',
            '22'	=> 	'1024',
            '23'	=> 	'512',
            '24'	=> 	'256',
            '25'	=> 	'128',
            '26'	=> 	'64',
            '27'	=> 	'32',
            '28'	=> 	'16',
            '29'	=> 	'8',
            '30'	=> 	'4',
            '31'	=> 	'2',
            '32'	=> 	'1',
        ];

        if ($reverse === true) {
            return array_flip($array);
        }

        return $array;
    }

    public function IPv6cidrIpCount($reverse = false)
    {
        // 'cidr' => 'IP count'
        $array = [
            '128' => '1',
            '127' => '2',
            '126' => '4',
            '125' => '8',
            '124' => '16',
            '123' => '32',
            '122' => '64',
            '121' => '128',
            '120' => '256',
            '119' => '512',
            '118' => '1024',
            '117' => '2048',
            '116' => '4096',
            '115' => '8192',
            '114' => '16384',
            '113' => '32768',
            '112' => '65536',
            '111' => '131072',
            '110' => '262144',
            '109' => '524288',
            '108' => '1048576',
            '107' => '2097152',
            '106' => '4194304',
            '105' => '8388608',
            '104' => '16777216',
            '103' => '33554432',
            '102' => '67108864',
            '101' => '134217728',
            '100' => '268435456',
            '99' => '536870912',
            '98' => '1073741824',
            '97' => '2147483648',
            '96' => '4294967296',
            '95' => '8589934592',
            '94' => '17179869184',
            '93' => '34359738368',
            '92' => '68719476736',
            '91' => '137438953472',
            '90' => '274877906944',
            '89' => '549755813888',
            '88' => '1099511627776',
            '87' => '2199023255552',
            '86' => '4398046511104',
            '85' => '8796093022208',
            '84' => '17592186044416',
            '83' => '35184372088832',
            '82' => '70368744177664',
            '81' => '140737488355328',
            '80' => '281474976710656',
            '79' => '562949953421312',
            '78' => '1125899906842624',
            '77' => '2251799813685248',
            '76' => '4503599627370496',
            '75' => '9007199254740992',
            '74' => '18014398509481985',
            '73' => '36028797018963968',
            '72' => '72057594037927936',
            '71' => '144115188075855872',
            '70' => '288230376151711744',
            '69' => '576460752303423488',
            '68' => '1152921504606846976',
            '67' => '2305843009213693952',
            '66' => '4611686018427387904',
            '65' => '9223372036854775808',
            '64' => '18446744073709551616',
            '63' => '36893488147419103232',
            '62' => '73786976294838206464',
            '61' => '147573952589676412928',
            '60' => '295147905179352825856',
            '59' => '590295810358705651712',
            '58' => '1180591620717411303424',
            '57' => '2361183241434822606848',
            '56' => '4722366482869645213696',
            '55' => '9444732965739290427392',
            '54' => '18889465931478580854784',
            '53' => '37778931862957161709568',
            '52' => '75557863725914323419136',
            '51' => '151115727451828646838272',
            '50' => '302231454903657293676544',
            '49' => '604462909807314587353088',
            '48' => '1208925819614629174706176',
            '47' => '2417851639229258349412352',
            '46' => '4835703278458516698824704',
            '45' => '9671406556917033397649408',
            '44' => '19342813113834066795298816',
            '43' => '38685626227668133590597632',
            '42' => '77371252455336267181195264',
            '41' => '154742504910672534362390528',
            '40' => '309485009821345068724781056',
            '39' => '618970019642690137449562112',
            '38' => '1237940039285380274899124224',
            '37' => '2475880078570760549798248448',
            '36' => '4951760157141521099596496896',
            '35' => '9903520314283042199192993792',
            '34' => '19807040628566084398385987584',
            '33' => '39614081257132168796771975168',
            '32' => '79228162514264337593543950336',
            '31' => '158456325028528675187087900672',
            '30' => '316912650057057350374175801344',
            '29' => '633825300114114700748351602688',
            '28' => '1267650600228229401496703205376',
            '27' => '2535301200456458802993406410752',
            '26' => '5070602400912917605986812821504',
            '25' => '10141204801825835211973625643008',
            '24' => '20282409603651670423947251286016',
            '23' => '40564819207303340847894502572032',
            '22' => '81129638414606681695789005144064',
            '21' => '162259276829213363391578010288128',
            '20' => '324518553658426726783156020576256',
            '19' => '649037107316853453566312041152512',
            '18' => '1298074214633706907132624082305024',
            '17' => '2596148429267413814265248164610048',
            '16' => '5192296858534827628530496329220096',
            '15' => '10384593717069655257060992658440192',
            '14' => '20769187434139310514121985316880384',
            '13' => '41538374868278621028243970633760768',
            '12' => '83076749736557242056487941267521536',
            '11' => '166153499473114484112975882535043072',
            '10' => '332306998946228968225951765070086144',
            '9' => '664613997892457936451903530140172288',
            '8' => '1329227995784915872903807060280344576',
        ];

        if ($reverse === true) {
            return array_flip($array);
        }

        return $array;
    }

    /**
     * Convert an IP address from presentation to decimal(39,0) format suitable for storage in MySQL
     *
     * @param string $ip_address An IP address in IPv4, IPv6 or decimal notation
     * @return string The IP address in decimal notation
     */
    public function ip2dec($ip_address)
    {
        // IPv4 address
        if (strpos($ip_address, ':') === false && strpos($ip_address, '.') !== false) {
            $ip_address = '::' . $ip_address;
        }

        // IPv6 address
        if (strpos($ip_address, ':') !== false) {
            $network = inet_pton($ip_address);
            $parts = unpack('N*', $network);

            foreach ($parts as &$part) {
                if ($part < 0) {
                    $part = bcadd((string) $part, '4294967296');
                }

                if (!is_string($part)) {
                    $part = (string) $part;
                }
            }

            $decimal = $parts[4];
            $decimal = bcadd($decimal, bcmul($parts[3], '4294967296'));
            $decimal = bcadd($decimal, bcmul($parts[2], '18446744073709551616'));
            $decimal = bcadd($decimal, bcmul($parts[1], '79228162514264337593543950336'));

            return $decimal;
        }

        // Decimal address
        return $ip_address;
    }

    /**
     * Convert an IP address from decimal format to presentation format
     *
     * @param string $decimal An IP address in IPv4, IPv6 or decimal notation
     * @return string The IP address in presentation format
     */
    public function dec2ip($decimal)
    {
        // IPv4 or IPv6 format
        if (strpos($decimal, ':') !== false || strpos($decimal, '.') !== false) {
            return $decimal;
        }

        // Decimal format
        $parts = array();
        $parts[1] = bcdiv($decimal, '79228162514264337593543950336', 0);
        $decimal = bcsub($decimal, bcmul($parts[1], '79228162514264337593543950336'));
        $parts[2] = bcdiv($decimal, '18446744073709551616', 0);
        $decimal = bcsub($decimal, bcmul($parts[2], '18446744073709551616'));
        $parts[3] = bcdiv($decimal, '4294967296', 0);
        $decimal = bcsub($decimal, bcmul($parts[3], '4294967296'));
        $parts[4] = $decimal;

        foreach ($parts as &$part) {
            if (bccomp($part, '2147483647') == 1) {
                $part = bcsub($part, '4294967296');
            }

            $part = (int) $part;
        }

        $network = pack('N4', $parts[1], $parts[2], $parts[3], $parts[4]);

        $ip_address = inet_ntop($network);

        // Turn IPv6 to IPv4 if it's IPv4
        if (preg_match('/^::\d+.\d+.\d+.\d+$/', $ip_address)) {
            return substr($ip_address, 2);
        }

        return $ip_address;
    }

    public function getInputType($input)
    {
        if (!filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return 6;
        } elseif (!filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return 4;
        }

        return "asn";
    }

    public function normalizeInput($input)
    {
        $type = $this->getInputType($input);

        if ($type === 'asn') {
            return "AS" . str_ireplace('as', '', $input);
        }
        return $input;
    }

    public function getAllocationEntry($input)
    {
        $type = $this->getInputType($input);

        // Try to do IP lookups
        if (is_numeric($type) === true) {
            if ($type === 6) {
                $class = RirIPv6Allocation::class;
            } else {
                $class = RirIPv4Allocation::class;
            }

            $ipDec = number_format($this->ip2dec($input), 0, '', '');
            return $class::where('ip_dec_start', '<=', $ipDec)
                ->where('ip_dec_end', '>=',  $ipDec)
                ->orderBy('date_allocated', 'desc')
                ->first();
        }

        //Not an IP. lets try look up domain
        $input = str_ireplace("AS", "", $input);
        return RirAsnAllocation::where('asn', $input)
            ->orderBy('date_allocated', 'desc')
            ->first();


    }
}
