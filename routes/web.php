<?php

use Illuminate\Support\Facades\Route;

use App\Livewire\ShowHome;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\PlanCheckoutController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\CityLanding;
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

// Lo que el cliente abre desde WhatsApp. Sin login: la firma de la liga es la
// que autoriza, y `signed` la valida. Alterar un carácter devuelve 403.
Route::middleware('signed')->group(function () {
    Route::get('/recibo/{payment}', [PublicDocumentController::class, 'receipt'])
        ->name('publico.recibo');
    Route::get('/recibo/{payment}/pdf', [PublicDocumentController::class, 'receiptPdf'])
        ->name('publico.recibo.pdf');
    Route::get('/estado-de-cuenta/{customer}', [PublicDocumentController::class, 'statement'])
        ->name('publico.estado-de-cuenta');
});

Route::get('/demo', [DemoController::class, 'index'])->name('demo.start');
Route::post('/demo/iniciar', [DemoController::class, 'create'])
    ->middleware('throttle:demo')
    ->name('demo.create');

Route::post('/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);

Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/renta-lavadoras/{city}', CityLanding::class)->name('city.landing');

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

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

Route::get('/plan/{package}/checkout', [PlanCheckoutController::class, 'checkout'])
    ->name('plan.checkout')
    ->middleware('auth');
