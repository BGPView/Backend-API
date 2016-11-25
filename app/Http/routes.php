<?php

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::group(['middleware' => ['api']], function () {

    Route::get('/asn/{as_number}', ['as' => 'asn', 'uses' => 'ApiV1Controller@asn']);
    Route::get('/asn/{as_number}/prefixes', ['as' => 'asn.prefixes', 'uses' => 'ApiV1Controller@asnPrefixes']);
    Route::get('/asn/{as_number}/peers', ['as' => 'asn.peers', 'uses' => 'ApiV1Controller@asnPeers']);
    Route::get('/asn/{as_number}/ixs', ['as' => 'asn.ixs', 'uses' => 'ApiV1Controller@asnIxs']);
    Route::get('/asn/{as_number}/upstreams', ['as' => 'asn.upstreams', 'uses' => 'ApiV1Controller@asnUpstreams']);
    Route::get('/asn/{as_number}/downstreams', ['as' => 'asn.downstreams', 'uses' => 'ApiV1Controller@asnDownstreams']);
    Route::get('/prefix/{ip}/{cidr}', ['as' => 'prefix', 'uses' => 'ApiV1Controller@prefix']);
    Route::get('/prefix/{ip}/{cidr}/dns', ['as' => 'prefix.dns', 'uses' => 'ApiV1Controller@prefixDns']);
    Route::get('/ip/{ip}', ['as' => 'ip', 'uses' => 'ApiV1Controller@ip']);
    Route::get('/ix/{ix_id}', ['as' => 'ix', 'uses' => 'ApiV1Controller@ix']);

    Route::get('/search', ['as' => 'asns', 'uses' => 'ApiV1Controller@search']);

    // Reporting Routes
    Route::group(['prefix' => 'reports'], function () {
        Route::get('countries', ['as' => 'reports.countries', 'uses' => 'ApiV1Controller@countriesReport']);
        Route::get('countries/{country_code}', ['as' => 'reports.country', 'uses' => 'ApiV1Controller@countryReport']);
    });

    // Misc Routes (Internal Use)
    Route::get('/dns/live/{hostname}', ['uses' => 'ApiV1Controller@getLiveDns']);
    Route::get('/sitemap', ['uses' => 'ApiV1Controller@sitemapUrls']);
    Route::get('/as-summery', ['uses' => 'ApiV1Controller@asnSummery']);
    Route::get('/contacts/{resource}/{cidr?}', ['uses' => 'ApiV1Controller@getContacts']);

});

Route::group(['middleware' => ['web']], function () {
    Route::any('/register-application', ['as' => 'register.application', 'uses' => 'ApiBaseController@registerApplication']);
});
