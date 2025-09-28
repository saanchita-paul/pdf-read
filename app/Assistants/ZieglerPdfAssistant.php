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
        $content = implode("\n", $lines);

        $order_reference = '';
        $freight_price = 0;
        $freight_currency = 'EUR';
        $booking_date = null;

        if (preg_match('/(\d{5,})\s+([\d,.]+)\s+(\d{2}\/\d{2}\/\d{4})/', $content, $m)) {
            $order_reference  = $m[1];
            $freight_price    = uncomma($m[2]);
            $freight_currency = 'EUR';
            $booking_date     = Carbon::createFromFormat('d/m/Y', $m[3])->toIsoString();
        }

        $carrier = '';
        foreach ($lines as $line) {
            if (preg_match('/Test_Client/i', $line)) {
                $carrier = trim($line);
                break;
            }
        }

        $customer = [
            'side' => 'none',
            'details' => [
                'company' => 'Ziegler UK Ltd',
                'street_address' => 'London Gateway Logistics Park, North 4, North Sea Crossing',
                'city' => 'Stanford le Hope',
                'postal_code' => 'SS17 9FJ',
                'country' => GeonamesCountry::getIso('United Kingdom'),
                'contact_person' => 'Booking Desk',
            ],
        ];


        [$loading_locations, $destination_locations, $cargos] = $this->parseLocationsAndCargos($lines);

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
            'carrier',
            'booking_date'
        );

        $this->createOrder($data);
    }

    private function parseLocationsAndCargos(array $lines): array
    {
        $loading = [];
        $destinations = [];
        $cargos = [];

        $current = null;
        $mode = null;

        foreach ($lines as $i => $line) {
            $trim = trim($line);

            if (Str::startsWith($trim, 'Collection')) {
                if ($current) {
                    if ($mode === 'collection') {
                        $loading[] = $this->finalizeLocation($current);
                    } elseif ($mode === 'delivery') {
                        $destinations[] = $this->finalizeLocation($current);
                    }
                }
                $mode = 'collection';
                $current = [
                    'company_address' => [
                        'company' => '',
                        'street_address' => '',
                        'city' => '',
                        'postal_code' => '',
                        'country' => GeonamesCountry::getIso('United Kingdom'),
                    ],
                    'time' => [
                        'datetime_from' => Carbon::create(1970,1,1,0,0,0,'UTC')->toIsoString(),
                        'datetime_to'   => Carbon::create(1970,1,1,23,59,59,'UTC')->toIsoString(),
                    ],
                ];

                if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $trim, $dm)) {
                    $date = Carbon::createFromFormat('d/m/Y', $dm[1]);
                    $from = $date->copy()->setTime(9, 0);
                    $to   = str_contains($trim, '3PM')
                        ? $date->copy()->setTime(15, 0)
                        : $date->copy()->setTime(14, 0);
                    $current['time'] = [
                        'datetime_from' => $from->toIsoString(),
                        'datetime_to'   => $to->toIsoString(),
                    ];
                }
                continue;
            }

            if (Str::startsWith($trim, 'Delivery')) {
                if ($current) {
                    if ($mode === 'collection') {
                        $loading[] = $this->finalizeLocation($current);
                    } elseif ($mode === 'delivery') {
                        $destinations[] = $this->finalizeLocation($current);
                    }
                }
                $mode = 'delivery';
                $current = [
                    'company_address' => [
                        'company' => '',
                        'street_address' => '',
                        'city' => '',
                        'postal_code' => '',
                        'country' => GeonamesCountry::getIso('France'),
                    ],
                    'time' => [
                        'datetime_from' => Carbon::create(1970,1,1,0,0,0,'UTC')->toIsoString(),
                        'datetime_to'   => Carbon::create(1970,1,1,23,59,59,'UTC')->toIsoString(),
                    ],
                ];
                continue;
            }

            if ($mode && $current) {
                if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $trim, $dm)) {
                    $date = Carbon::createFromFormat('d/m/Y', $dm[1]);
                    if ($mode === 'delivery') {
                        if (str_contains(strtolower($lines[$i-1] ?? ''), '06:00')) {
                            $from = $date->copy()->setTime(6, 0);
                            $to   = $date->copy()->endOfDay();
                        } elseif (str_contains(strtolower($lines[$i+1] ?? ''), '09:00')) {
                            $from = $date->copy()->setTime(9, 0);
                            $to   = $date->copy()->setTime(12, 0);
                        } else {
                            $from = $date->copy()->startOfDay();
                            $to   = $date->copy()->endOfDay();
                        }
                        $current['time'] = [
                            'datetime_from' => $from->toIsoString(),
                            'datetime_to'   => $to->toIsoString(),
                        ];
                    }
                    continue;
                }

                if (!$current['company_address']['company']) {
                    $current['company_address']['company'] = $trim;
                } elseif (!$current['company_address']['street_address'] && preg_match('/(ROAD|LANE|RUE|CHEM|HALL)/i', $trim)) {
                    $current['company_address']['street_address'] = $trim;
                } elseif (preg_match('/^(\d{5})\s+(.+)/', $trim, $fm)) {
                    $current['company_address']['postal_code'] = $fm[1];
                    $current['company_address']['city'] = trim($fm[2]);
                    $current['company_address']['country'] = GeonamesCountry::getIso('France');
                } elseif (preg_match('/([A-Z]{1,2}\d.*\d[A-Z]{2})\s*(.*)/', $trim, $um)) {
                    $current['company_address']['postal_code'] = $um[1];
                    $current['company_address']['city'] = $um[2] ?: $current['company_address']['city'];
                    $current['company_address']['country'] = GeonamesCountry::getIso('United Kingdom');
                }

                if (preg_match('/(\d+)\s+pallets?/i', $trim, $pm)) {
                    $cargos[] = [
                        'title' => "{$pm[1]} pallets",
                        'number' => $this->findRefInNearby($lines, $i),
                        'package_count' => (int)$pm[1],
                        'package_type' => 'pallet',
                    ];
                }
            }
        }

        if ($current) {
            if ($mode === 'collection') {
                $loading[] = $this->finalizeLocation($current);
            } elseif ($mode === 'delivery') {
                $destinations[] = $this->finalizeLocation($current);
            }
        }

        return [$loading, $destinations, $cargos];
    }

    private function finalizeLocation(array $location): array
    {
        $location['company_address']['company']        = $location['company_address']['company'] ?: 'Unknown';
        $location['company_address']['street_address'] = $location['company_address']['street_address'] ?: 'N/A';
        $location['company_address']['city']           = $location['company_address']['city'] ?: 'Unknown';
        $location['company_address']['postal_code']    = $location['company_address']['postal_code'] ?: '00000';
        return $location;
    }

    private function findRefInNearby(array $lines, int $i): string
    {
        for ($j = max(0, $i-2); $j <= min(count($lines)-1, $i+2); $j++) {
            if (preg_match('/REF\s+([A-Z0-9]+)/i', $lines[$j], $m)) {
                return $m[1];
            }
        }
        return '';
    }
}
