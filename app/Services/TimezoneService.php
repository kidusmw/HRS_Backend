<?php

namespace App\Services;

class TimezoneService
{
    /**
     * Get IANA timezone from city and country
     * 
     * This uses a combination of city-country mapping and country fallbacks.
     * For production, consider integrating with a geocoding API for more accuracy.
     */
    public static function getTimezoneFromLocation(string $city, string $country): string
    {
        // Normalize inputs
        $city = trim($city);
        $country = trim($country);
        
        // City-Country specific mappings (common hotel locations)
        $cityCountryMap = [
            // United States
            'New York' => ['United States' => 'America/New_York'],
            'Los Angeles' => ['United States' => 'America/Los_Angeles'],
            'Chicago' => ['United States' => 'America/Chicago'],
            'Denver' => ['United States' => 'America/Denver'],
            'Phoenix' => ['United States' => 'America/Phoenix'],
            'Miami' => ['United States' => 'America/New_York'],
            'Seattle' => ['United States' => 'America/Los_Angeles'],
            'Boston' => ['United States' => 'America/New_York'],
            'San Francisco' => ['United States' => 'America/Los_Angeles'],
            'Las Vegas' => ['United States' => 'America/Los_Angeles'],
            
            // United Kingdom
            'London' => ['United Kingdom' => 'Europe/London'],
            'Manchester' => ['United Kingdom' => 'Europe/London'],
            'Edinburgh' => ['United Kingdom' => 'Europe/London'],
            
            // France
            'Paris' => ['France' => 'Europe/Paris'],
            'Lyon' => ['France' => 'Europe/Paris'],
            'Marseille' => ['France' => 'Europe/Paris'],
            
            // Germany
            'Berlin' => ['Germany' => 'Europe/Berlin'],
            'Munich' => ['Germany' => 'Europe/Berlin'],
            'Frankfurt' => ['Germany' => 'Europe/Berlin'],
            
            // Italy
            'Rome' => ['Italy' => 'Europe/Rome'],
            'Milan' => ['Italy' => 'Europe/Rome'],
            'Venice' => ['Italy' => 'Europe/Rome'],
            
            // Spain
            'Madrid' => ['Spain' => 'Europe/Madrid'],
            'Barcelona' => ['Spain' => 'Europe/Madrid'],
            
            // Japan
            'Tokyo' => ['Japan' => 'Asia/Tokyo'],
            'Osaka' => ['Japan' => 'Asia/Tokyo'],
            'Kyoto' => ['Japan' => 'Asia/Tokyo'],
            
            // China
            'Beijing' => ['China' => 'Asia/Shanghai'],
            'Shanghai' => ['China' => 'Asia/Shanghai'],
            'Hong Kong' => ['China' => 'Asia/Hong_Kong'],
            
            // UAE
            'Dubai' => ['United Arab Emirates' => 'Asia/Dubai'],
            'Abu Dhabi' => ['United Arab Emirates' => 'Asia/Dubai'],
            
            // India
            'Mumbai' => ['India' => 'Asia/Kolkata'],
            'Delhi' => ['India' => 'Asia/Kolkata'],
            'Bangalore' => ['India' => 'Asia/Kolkata'],
            
            // Australia
            'Sydney' => ['Australia' => 'Australia/Sydney'],
            'Melbourne' => ['Australia' => 'Australia/Melbourne'],
            'Perth' => ['Australia' => 'Australia/Perth'],
            
            // Canada
            'Toronto' => ['Canada' => 'America/Toronto'],
            'Vancouver' => ['Canada' => 'America/Vancouver'],
            'Montreal' => ['Canada' => 'America/Toronto'],
            
            // Mexico
            'Mexico City' => ['Mexico' => 'America/Mexico_City'],
            'Cancun' => ['Mexico' => 'America/Cancun'],
            
            // Brazil
            'Rio de Janeiro' => ['Brazil' => 'America/Sao_Paulo'],
            'Sao Paulo' => ['Brazil' => 'America/Sao_Paulo'],
            
            // South Africa
            'Cape Town' => ['South Africa' => 'Africa/Johannesburg'],
            'Johannesburg' => ['South Africa' => 'Africa/Johannesburg'],
            
            // Egypt
            'Cairo' => ['Egypt' => 'Africa/Cairo'],
            
            // Turkey
            'Istanbul' => ['Turkey' => 'Europe/Istanbul'],
            
            // Russia
            'Moscow' => ['Russia' => 'Europe/Moscow'],
            'Saint Petersburg' => ['Russia' => 'Europe/Moscow'],
            
            // Thailand
            'Bangkok' => ['Thailand' => 'Asia/Bangkok'],
            
            // Singapore
            'Singapore' => ['Singapore' => 'Asia/Singapore'],
            
            // South Korea
            'Seoul' => ['South Korea' => 'Asia/Seoul'],

            // Ethiopia
            'Addis Ababa' => ['Ethiopia' => 'Africa/Addis_Ababa'],
            'Harar' => ['Ethiopia' => 'Africa/Addis_Ababa'],
            'Gondar' => ['Ethiopia' => 'Africa/Addis_Ababa'],
            'Jimma' => ['Ethiopia' => 'Africa/Addis_Ababa'],
            'Mekelle' => ['Ethiopia' => 'Africa/Addis_Ababa'],
            'Hawassa' => ['Ethiopia' => 'Africa/Addis_Ababa'],
            'Bahir Dar' => ['Ethiopia' => 'Africa/Addis_Ababa'],
            'Debre Birhan' => ['Ethiopia' => 'Africa/Addis_Ababa'],
        ];
        
        // Check city-country mapping first
        if (isset($cityCountryMap[$city]) && isset($cityCountryMap[$city][$country])) {
            return $cityCountryMap[$city][$country];
        }
        
        // Country-based fallback timezones
        $countryTimezoneMap = [
            'United States' => 'America/New_York',
            'United Kingdom' => 'Europe/London',
            'Canada' => 'America/Toronto',
            'Mexico' => 'America/Mexico_City',
            'Brazil' => 'America/Sao_Paulo',
            'France' => 'Europe/Paris',
            'Germany' => 'Europe/Berlin',
            'Italy' => 'Europe/Rome',
            'Spain' => 'Europe/Madrid',
            'Netherlands' => 'Europe/Amsterdam',
            'Belgium' => 'Europe/Brussels',
            'Switzerland' => 'Europe/Zurich',
            'Austria' => 'Europe/Vienna',
            'Portugal' => 'Europe/Lisbon',
            'Greece' => 'Europe/Athens',
            'Poland' => 'Europe/Warsaw',
            'Czech Republic' => 'Europe/Prague',
            'Sweden' => 'Europe/Stockholm',
            'Norway' => 'Europe/Oslo',
            'Denmark' => 'Europe/Copenhagen',
            'Finland' => 'Europe/Helsinki',
            'Russia' => 'Europe/Moscow',
            'Turkey' => 'Europe/Istanbul',
            'Japan' => 'Asia/Tokyo',
            'China' => 'Asia/Shanghai',
            'India' => 'Asia/Kolkata',
            'South Korea' => 'Asia/Seoul',
            'Thailand' => 'Asia/Bangkok',
            'Singapore' => 'Asia/Singapore',
            'Malaysia' => 'Asia/Kuala_Lumpur',
            'Indonesia' => 'Asia/Jakarta',
            'Philippines' => 'Asia/Manila',
            'Vietnam' => 'Asia/Ho_Chi_Minh',
            'Australia' => 'Australia/Sydney',
            'New Zealand' => 'Pacific/Auckland',
            'South Africa' => 'Africa/Johannesburg',
            'Egypt' => 'Africa/Cairo',
            'United Arab Emirates' => 'Asia/Dubai',
            'Saudi Arabia' => 'Asia/Riyadh',
            'Israel' => 'Asia/Jerusalem',
            'Argentina' => 'America/Argentina/Buenos_Aires',
            'Chile' => 'America/Santiago',
            'Colombia' => 'America/Bogota',
            'Peru' => 'America/Lima',
            'Ethiopia' => 'Africa/Addis_Ababa',
        ];
        
        // Use country-based timezone if available
        if (isset($countryTimezoneMap[$country])) {
            return $countryTimezoneMap[$country];
        }
        
        // Default fallback to UTC
        return 'UTC';
    }
}

