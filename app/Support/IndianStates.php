<?php
declare(strict_types=1);

namespace App\Support;

/**
 * All 28 Indian states + 8 Union Territories, alphabetical.
 * Used wherever a state dropdown is needed (suppliers, customers, business profile).
 * Determines intra-state (CGST+SGST) vs inter-state (IGST) GST on invoices per D52.
 */
class IndianStates
{
    public const LIST = [
        'Andhra Pradesh',
        'Arunachal Pradesh',
        'Assam',
        'Bihar',
        'Chhattisgarh',
        'Goa',
        'Gujarat',
        'Haryana',
        'Himachal Pradesh',
        'Jharkhand',
        'Karnataka',
        'Kerala',
        'Madhya Pradesh',
        'Maharashtra',
        'Manipur',
        'Meghalaya',
        'Mizoram',
        'Nagaland',
        'Odisha',
        'Punjab',
        'Rajasthan',
        'Sikkim',
        'Tamil Nadu',
        'Telangana',
        'Tripura',
        'Uttar Pradesh',
        'Uttarakhand',
        'West Bengal',
        // Union Territories
        'Andaman and Nicobar Islands',
        'Chandigarh',
        'Dadra and Nagar Haveli and Daman and Diu',
        'Delhi',
        'Jammu and Kashmir',
        'Ladakh',
        'Lakshadweep',
        'Puducherry',
    ];

    public static function isValid(string $state): bool
    {
        return \in_array($state, self::LIST, true);
    }
}
