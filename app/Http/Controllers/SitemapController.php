<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $cities = [
            'culiacan', 'mazatlan', 'los-mochis', 'guasave', 'hermosillo',
            'guadalajara', 'zapopan', 'monterrey', 'tijuana', 'mexicali',
            'cdmx', 'puebla', 'queretaro', 'leon', 'aguascalientes',
            'merida', 'cancun', 'chihuahua', 'durango', 'puerto-vallarta',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Home
        $xml .= '<url><loc>' . url('/') . '</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>';

        // City pages
        foreach ($cities as $city) {
            $xml .= '<url><loc>' . url("/renta-lavadoras/{$city}") . '</loc><changefreq>monthly</changefreq><priority>0.8</priority></url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
