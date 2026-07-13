<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'v1/licenses', 'middleware' => ['license.cors']], function () {
    Route::options('{any}', function () {
        return response('', 204);
    })->where('any', '.*');
    Route::post('claim', 'Api\LicenseController@claim')->middleware('throttle:10,60');
    Route::post('verify', 'Api\LicenseController@verify')->middleware('throttle:120,60');
    Route::post('recovery/request', 'Api\LicenseController@requestRecovery')->middleware('throttle:20,60');
    Route::post('recovery/confirm', 'Api\LicenseController@confirmRecovery')->middleware('throttle:30,60');
});
