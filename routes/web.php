<?php

use Illuminate\Support\Facades\Route;

use App\Livewire\ShowHome;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\QrCodeController;
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

Route::get('/contrato/{rental}/descargar', [ContractController::class, 'download'])
    ->name('contract.download')
    ->middleware('auth');

Route::get('/recibo/{payment}/descargar', [ContractController::class, 'receipt'])
    ->name('receipt.download')
    ->middleware('auth');

Route::get('/lavadora/{washingMachine}/info', [QrCodeController::class, 'show'])
    ->name('qr.show');

Route::get('/lavadora/{washingMachine}/qr', [QrCodeController::class, 'download'])
    ->name('qr.download')
    ->middleware('auth');
