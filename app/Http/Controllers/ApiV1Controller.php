<?php

namespace App\Http\Controllers;

use App\Models\ASN;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\ApiBaseController;

class ApiV1Controller extends ApiBaseController
{
    /*
     * URI: /asn/{as_number}
     * Optional Params: with_raw_whois
     */
    public function asn(Request $request, $as_number)
    {
        // lets only use the AS number
        $as_number = str_ireplace('as', '', $as_number);

        $asnData = ASN::where('asn', $as_number)->first();

        if (is_null($asnData)) {
            $data = $this->makeStatus('Could not find ASN', false);
            return $this->respond($data);
        }

        $output['asn']  = $asnData->asn;
        $output['name'] = $asnData->name;
        $output['description']['short'] = $asnData->description;
        $output['description']['full']  = $asnData->description_full;
        $output['country_code']         = $asnData->counrty_code;
        $output['website']              = $asnData->website;
        $output['looking_glass']        = $asnData->looking_glass;
        $output['traffic_estimation']   = $asnData->traffic_estimation;
        $output['traffic_ratio']        = $asnData->traffic_ratio;
        $output['owner_address']        = $asnData->owner_address;

        if ($request->has('with_raw_whois') === true) {
            $output['raw_whois'] = $asnData->raw_whois;
        }

        $output['date_updated']        = (string) $asnData->updated_at;

        return $this->sendData($output);
    }
}
