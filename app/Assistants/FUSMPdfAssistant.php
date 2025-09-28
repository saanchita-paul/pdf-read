<?php

namespace App\Assistants;

use App\GeonamesCountry;
use Carbon\Carbon;


class FUSMPdfAssistant extends PdfClient
{
    public static function validateFormat(array $lines)
    {
        $text = implode(' ', $lines);
        return str_contains($text, 'CHARTERING CONFIRMATION') &&
            str_contains($text, 'TRANSALLIANCE');
    }

    public function processLines(array $lines, ?string $attachment_filename = null)
    {
        $content = implode("\n", $lines);

        // --- Order reference ---
        preg_match('/REF\.\:(\d+)/i', $content, $m);
        $order_reference = $m[1] ?? '';

        // --- Freight price + currency ---
        preg_match('/SHIPPING PRICE.*?([\d\.,]+)\s+([A-Z]{3})/i', $content, $m);
        $freight_price = isset($m[1]) ? (float) str_replace([','], ['.'], str_replace('.', '', $m[1])) : 0;
        $freight_currency = $m[2] ?? 'EUR';

        // --- Customer fixed ---
        $customer = [
            'side' => 'none',
            'details' => [
                'company' => 'Transalliance TS Ltd',
                'street_address' => 'Suite 8/9 Faraday Court, Centrum 100',
                'city' => 'Burton upon Trent',
                'postal_code' => 'DE14 2WX',
                'country' => GeonamesCountry::getIso('United Kingdom'),
                'contact_person' => $this->findContact($lines),
            ],
        ];

        // --- Locations ---
        $loading_locations = $this->extractLocation($lines, 'Loading');
        $destination_locations = $this->extractLocation($lines, 'Delivery');

        // --- Cargo ---
        $cargos = $this->extractCargos($content);

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

    private function findContact(array $lines): string
    {
        foreach ($lines as $line) {
            if (stripos($line, 'Contact:') !== false) {
                return trim(str_ireplace('Contact:', '', $line));
            }
        }
        return 'Booking Desk';
    }

    private function extractLocation(array $lines, string $keyword): array
    {
        $locations = [];

        for ($i = 0; $i < count($lines); $i++) {
            if (stripos($lines[$i], $keyword) !== false) {
                $block = [];


                if (isset($lines[$i+2]) && preg_match('/ON:\s*(\d{2}\/\d{2}\/\d{2})/', $lines[$i+2], $dm)) {
                    $date = Carbon::createFromFormat('d/m/y', $dm[1]);
                    $from = $date->copy()->setTime(9, 0);
                    $to   = $date->copy()->setTime(17, 0);
                } else {
                    $date = Carbon::now();
                    $from = $date->copy()->startOfDay();
                    $to   = $date->copy()->endOfDay();
                }


                $j = $i+3;
                while ($j < count($lines) && stripos($lines[$j], 'Instructions') === false && trim($lines[$j]) !== '') {
                    $block[] = trim($lines[$j]);
                    $j++;
                }
                $blockText = implode(' ', $block);


                $company = strtok($blockText, ' ');
                $street  = $this->guessStreet($blockText);
                $postal  = $this->guessPostal($blockText);
                $city    = $this->guessCity($blockText);

                $country = $keyword === 'Delivery'
                    ? GeonamesCountry::getIso('France')
                    : GeonamesCountry::getIso('United Kingdom');

                $locations[] = [
                    'company_address' => [
                        'company' => $company ?: 'Unknown',
                        'street_address' => $street ?: 'N/A',
                        'city' => $city ?: 'Unknown',
                        'postal_code' => $postal ?: '00000',
                        'country' => $country,
                    ],
                    'time' => [
                        'datetime_from' => $from->toIsoString(),
                        'datetime_to'   => $to->toIsoString(),
                    ],
                ];
            }
        }
        return $locations;
    }


    private function extractCargos(string $content): array
    {
        $cargos = [];

        // Try inline "Pal. nb.: X"
        if (preg_match('/Pal\. nb\.\s*:\s*(\d+)/i', $content, $m)) {
            $count = (int) $m[1];
        }
        // Or check next line after "Pal. nb."
        elseif (preg_match('/Pal\. nb\.[^\n]*\n\s*([\d]+)/i', $content, $m)) {
            $count = (int) $m[1];
        } else {
            $count = 0;
        }

        if ($count > 0) {
            $cargos[] = [
                'title' => "$count pallets",
                'number' => $this->findCargoRef($content),
                'package_count' => $count,
                'package_type' => 'pallet',
            ];
        }

        return $cargos;
    }


    private function findCargoRef(string $content): string
    {
        if (preg_match('/OT\s*:\s*(\d+)/i', $content, $m)) {
            return $m[1];
        }
        return '';
    }

    private function guessStreet(string $block): string
    {
        if (preg_match('/(RUE\s+[A-Z0-9\-\s]+|ROAD\s+[A-Z0-9\-\s]+|LANE\s+[A-Z0-9\-\s]+|GATEWAY\s+[A-Z0-9\-\s]+)/i', $block, $m)) {
            return trim($m[0]);
        }
        return 'N/A';
    }

    private function guessCity(string $block): string
    {
        if (preg_match('/\b([A-Z][A-Z\-\s]+)$/i', $block, $m)) {
            return trim($m[1]);
        }
        return 'Unknown';
    }

    private function guessPostal(string $block): string
    {
        if (preg_match('/\b\d{4,5}\b/', $block, $m)) {
            return $m[0];
        }
        return '00000';
    }
}
