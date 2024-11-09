<?php

use Illuminate\Support\Facades\Route;

use App\Livewire\ShowHome;
use App\Http\Controllers\PaymentController;
use App\Livewire\ShowPackage;

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
/*
Route::get('/', function () {
    return view('welcome');
});
*/

Route::get('/', ShowHome::class);
Route::get('/contratar/{package}', ShowPackage::class);

Route::post('/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);
