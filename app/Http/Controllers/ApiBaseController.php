<?php

namespace App\Http\Controllers;

use App\Helpers\IpUtils;
use App\Models\ApiApplication;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

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

    /**
     * generates a random timecoded uuid
     *
     * @return string
     */
    public function generate_uuid()
    {
        return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }


    public function registerApplication(Request $request)
    {
        if ($request->isMethod('post') === true) {
            $validator = Validator::make($request->all(), [
                'name' => 'required|max:255',
                'email' => 'required|email|unique:api_applications',
                'usage' => 'required|min:5',
            ]);

            if ($validator->fails()) {
                return redirect('register-application')
                    ->withErrors($validator)
                    ->withInput();
            }

            $key = $this->generate_uuid();
            $application = new ApiApplication();
            $application->name = $request->input('name');
            $application->email = $request->input('email');
            $application->use = $request->input('usage');
            $application->key = $key;
            $application->save();

            Mail::send('api.email', ['key' => $application->key], function ($message) use ($application) {
                $message->from('postmaster@bgpview.io', 'BGPView [NoReply]');
                $message->to($application->email);
                $message->subject('BGPView API Key');
            });

            return view('api.register-application-done');
        }


        return view('api.register-application');
    }
}
