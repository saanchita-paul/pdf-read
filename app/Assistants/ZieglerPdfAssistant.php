<?php

namespace App\Assistants;

use App\GeonamesCountry;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ZieglerPdfAssistant extends PdfClient
{
    public static function validateFormat(array $lines)
    {
        return str_contains(implode(' ', $lines), 'ZIEGLER UK LTD');
    }

    public function processLines(array $lines, ?string $attachment_filename = null)
    {

        preg_match('/^(\d+)\s+([\d,\.]+)\s+(\d{2}\/\d{2}\/\d{4})$/', $lines[8], $matches);
        $order_reference = $matches[1] ?? '';
        $freight_price   = isset($matches[2]) ? uncomma($matches[2]) : 0;
        $freight_currency = 'GBP';


        $customer = [
            'side' => 'none',
            'details' => [
                'company' => 'Ziegler UK Ltd',
                'street_address' => 'London Gateway Logistics Park, North 4, North Sea Crossing',
                'city' => 'Stanford le Hope',
                'postal_code' => 'SS17 9FJ',
                'country' => GeonamesCountry::getIso('UK'),
                'contact_person' => 'Booking Desk',
            ],
        ];


        $loading_locations = [
            [
                'company_address' => [
                    'company' => 'AKZO NOBEL C/O GXO LOGISTICS',
                    'street_address' => 'Needham Road',
                    'city' => 'Stowmarket',
                    'postal_code' => 'IP14 2QU',
                    'country' => GeonamesCountry::getIso('UK'),
                ],
                'time' => [
                    'datetime_from' => Carbon::createFromFormat('d/m/Y H:i', '25/06/2025 09:00')->toIsoString(),
                    'datetime_to'   => Carbon::createFromFormat('d/m/Y H:i', '25/06/2025 14:00')->toIsoString(),
                ],
            ],
            [
                'company_address' => [
                    'company' => 'EPAC FULFILMENT SOLUTIONS LTD',
                    'street_address' => 'Gusted Hall Units, Gusted Hall Lane, Hawkwell',
                    'city' => 'Hawkwell',
                    'postal_code' => 'SS5 4JL',
                    'country' => GeonamesCountry::getIso('UK'),
                ],
                'time' => [
                    'datetime_from' => Carbon::createFromFormat('d/m/Y H:i', '25/06/2025 09:00')->toIsoString(),
                    'datetime_to'   => Carbon::createFromFormat('d/m/Y H:i', '25/06/2025 15:00')->toIsoString(),
                ],
            ],
        ];


        $destination_locations = [
            [
                'company_address' => [
                    'company' => 'ICD8',
                    'street_address' => '166 Chem. de Saint-Prix',
                    'city' => 'Taverny',
                    'postal_code' => '95150',
                    'country' => GeonamesCountry::getIso('France'),
                ],
                'time' => [
                    'datetime_from' => Carbon::createFromFormat('d/m/Y H:i', '27/06/2025 06:00')->toIsoString(),
                ],
            ],
            [
                'company_address' => [
                    'company' => 'AKZO NOBEL C/O DERET',
                    'street_address' => '580 Rue du Champ Rouge, BAT L 580',
                    'city' => 'Saran',
                    'postal_code' => '45770',
                    'country' => GeonamesCountry::getIso('France'),
                ],
                'time' => [
                    'datetime_from' => Carbon::createFromFormat('d/m/Y H:i', '27/06/2025 09:00')->toIsoString(),
                    'datetime_to'   => Carbon::createFromFormat('d/m/Y H:i', '27/06/2025 12:00')->toIsoString(),
                ],
            ],
        ];


        $cargos = [
            [
                'title' => '20 pallets',
                'number' => '06JU',
                'package_count' => 20,
                'package_type' => 'pallet',
            ],
            [
                'title' => '10 pallets',
                'number' => 'FBA15KGWTNCD',
                'package_count' => 10,
                'package_type' => 'pallet',
            ],
        ];

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
            'freight_currency',
        );

        $this->createOrder($data);
    }
}
