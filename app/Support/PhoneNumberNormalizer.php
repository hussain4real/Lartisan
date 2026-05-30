<?php

namespace App\Support;

use InvalidArgumentException;

class PhoneNumberNormalizer
{
    /**
     * @return array{country_code: string, national: string, e164: string}
     */
    public function normalize(string $countryCode, string $phoneNumber): array
    {
        $countryDigits = preg_replace('/\D+/', '', $countryCode) ?: '';
        $numberDigits = preg_replace('/\D+/', '', $phoneNumber) ?: '';

        if ($countryDigits === '') {
            throw new InvalidArgumentException('A phone country code is required.');
        }

        if ($numberDigits === '') {
            throw new InvalidArgumentException('A phone number is required.');
        }

        if (str_starts_with($numberDigits, $countryDigits) && strlen($numberDigits) > strlen($countryDigits) + 4) {
            $numberDigits = substr($numberDigits, strlen($countryDigits));
        }

        $nationalNumber = ltrim($numberDigits, '0');

        if ($nationalNumber === '') {
            throw new InvalidArgumentException('A valid phone number is required.');
        }

        return [
            'country_code' => '+'.$countryDigits,
            'national' => $nationalNumber,
            'e164' => '+'.$countryDigits.$nationalNumber,
        ];
    }
}
