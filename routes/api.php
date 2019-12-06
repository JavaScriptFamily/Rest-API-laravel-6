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

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::get('/clear-cache', function() {
    $exitCode = Artisan::call('config:clear');
    $exitCode = Artisan::call('cache:clear');
    $exitCode = Artisan::call('config:cache');
    return 'DONE'; //Return anything
});

// Fall Back URL, If route not exist 
Route::fallback(function(){
    return response()->json(
        [
            'success'=> false,
            'message'=> "Page Not Found.",
            'error'=> (object)[]
        ], 404);
});

Route::post('login', [ 'as' => 'login', 'uses' => 'API\UserController@login']);
Route::post('socialLogin', [ 'as' => 'socialLogin', 'uses' => 'API\UserController@socialLogin']);
Route::post('register', 'API\UserController@register');

// Forgot Password
Route::post('send_forgot_password_link', 'API\UserController@send_forgot_password_link');
Route::get('find_forgot_password_token/{token}', 'API\UserController@find_forgot_password_token');
Route::post('reset_forgot_password', 'API\UserController@reset_forgot_password');
Route::post('static_pages', 'API\DashboardDetails@static_page_list');

Route::group(['middleware' => 'auth:api'], function() {
    Route::post('userProfile', 'API\UserController@userProfile');
});