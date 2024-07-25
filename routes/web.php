<?php

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CorsMiddleware;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/generate-token', [Controller::class, 'generateClientCredentialsToken']);

//  Route::middleware([CorsMiddleware::class, CheckClientCredentials::class])
//       ->post('oauth/token', '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken');
