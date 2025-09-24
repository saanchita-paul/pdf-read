<?php

namespace App\Assistants;

use App\GeonamesCountry;
use Carbon\Carbon;

class FUSMPdfAssistant extends PdfClient
{
    public static function validateFormat(array $lines)
    {
        return str_contains(implode(' ', $lines), 'CHARTERING CONFIRMATION')
            && str_contains(implode(' ', $lines), 'TRANSALLIANCE');
    }

    public function processLines(array $lines, ?string $attachment_filename = null)
    {

        preg_match('/REF\.\:(\d+)/', implode(' ', $lines), $m);
        $order_reference = $m[1] ?? '';


        preg_match('/SHIPPING PRICE\s+([\d\.,]+)\s+([A-Z]{3})/i', implode(' ', $lines), $m);
        $freight_price = isset($m[1]) ? (float) str_replace([','], ['.'], str_replace('.', '', $m[1])) : null;
        $freight_currency = $m[2] ?? 'EUR';


        $customer = [
            'side' => 'none',
            'details' => [
                'company' => 'Transalliance TS Ltd',
                'street_address' => 'Suite 8/9 Faraday Court, Centrum 100',
                'city' => 'Burton upon Trent',
                'postal_code' => 'DE14 2WX',
                'country' => GeonamesCountry::getIso('UK'),
                'contact_person' => 'Kamran Mahfooz',
            ],
        ];


        $loading_locations = [[
            'company_address' => [
                'company' => 'DP WORLD LONDON GATEWAY PORT 1',
                'street_address' => 'London Gateway, Corringham',
                'city' => 'Stanford',
                'postal_code' => 'SS17 9DY',
                'country' => GeonamesCountry::getIso('UK'),
            ],
            'time' => [
                'datetime_from' => Carbon::createFromFormat('d/m/y H:i', '15/09/25 09:00')->toIsoString(),
                'datetime_to'   => Carbon::createFromFormat('d/m/y H:i', '15/09/25 17:00')->toIsoString(),
            ],
        ]];


        $destination_locations = [[
            'company_address' => [
                'company' => 'EP GROUP FRANCE',
                'street_address' => 'ZI Distriport, 2 Rue de Tokyo',
                'city' => 'Port-Saint-Louis-du-Rhone',
                'postal_code' => '13230',
                'country' => GeonamesCountry::getIso('France'),
            ],
            'time' => [
                'datetime_from' => Carbon::createFromFormat('d/m/y H:i', '17/09/25 09:00')->toIsoString(),
                'datetime_to'   => Carbon::createFromFormat('d/m/y H:i', '17/09/25 17:00')->toIsoString(),
            ],
        ]];


        $cargos = [[
            'title' => '25 pallets',
            'number' => '105518',
            'package_count' => 25,
            'package_type' => 'pallet',
        ]];

        $attachment_filenames = [mb_strtolower($attachment_filename ?? '')];
        $transport_numbers = '';

        $data = compact(
            'customer',
            'loading_locations',
            'destination_locations',
            'attachment_filenames',
            'cargos',
            'order_reference',
            'transport_numbers',
            'freight_price',
            'freight_currency'
        );

        $this->createOrder($data);
    }
}
