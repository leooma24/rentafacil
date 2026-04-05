<?php

namespace App\Http\Controllers;

use App\Models\WashingMachine;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    public function show(WashingMachine $washingMachine)
    {
        $machine = $washingMachine->load(['company', 'activeRental.customer']);

        return view('qr.machine-info', compact('machine'));
    }

    public function download(WashingMachine $washingMachine)
    {
        $url = route('qr.show', $washingMachine);

        $qr = QrCode::format('svg')
            ->size(300)
            ->margin(2)
            ->generate($url);

        return response($qr, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-' . $washingMachine->machine_code . '.svg"',
        ]);
    }
}
