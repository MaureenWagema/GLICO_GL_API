<?php

use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;
use App\Http\Middleware\CorsMiddleware;
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

//  Route::middleware([CorsMiddleware::class, CheckClientCredentials::class])
//       ->post('oauth/token', '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken');
