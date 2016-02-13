<?php

namespace App\Http\Controllers;

use App\Helpers\IpUtils;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;

class ApiBaseController extends Controller
{
    private $_startTime = 0;
    public $ipUtils;

    public function __construct(IpUtils $ipUtils)
    {
        $this->ipUtils = $ipUtils;
        $this->_startTime = microtime(true) * 1000;
    }
    //returns load time in MiliSeconds
    private function getLoadTime($string_fromat = true) {
        $time = microtime(true) * 1000;
        $load_time = round(($time - $this->_startTime), 2);
        return $string_fromat ? $load_time . " ms" : $load_time;
    }

    public function makeStatus($message = 'Query was successful', $status = true) {
        $data['status'] = $status ? 'ok' : 'error';
        $data['status_message'] = $message;
        return $data;
    }

    public function respond($data, $code = 200)
    {
        $data['@meta']['time_zone'] = Config::get('app.timezone');
        $data['@meta']['api_version'] = 1;
        $data['@meta']['execution_time'] = $this->getLoadTime();

        return $data;
    }

    public function sendData($data_array)
    {
        $data = $this->makeStatus();
        $data['data'] = $data_array;
        return $this->respond($data);
    }
}
