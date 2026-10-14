<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Which business details appear under the business name on printed bills.
 *
 * Each detail has an on/off switch in System Settings. The print templates
 * already hide any field that is empty, so this works by blanking the fields
 * that are switched off and handing the templates back an ordinary business
 * array. Thermal and A4 bills both go through here, so they cannot disagree.
 *
 * Every switch defaults to ON: a business that has never opened the setting
 * keeps printing exactly what it printed before.
 */
final class BillHeader
{
    /** setting key => [business field, label shown in System Settings] */
    public const FIELDS = [
        'bill_show_legal_name' => ['legal_name', 'Legal name'],
        'bill_show_address'    => ['address',    'Address'],
        'bill_show_state'      => ['state',      'State'],
        'bill_show_phone'      => ['phone',      'Phone'],
        'bill_show_email'      => ['email',      'Email'],
        'bill_show_gstin'      => ['gstin',      'GSTIN'],
    ];

    /** The value a fresh install ships with until the owner changes it. */
    private const PLACEHOLDER_LEGAL_NAME = 'Your Business Legal Name';

    /**
     * @param array<string, mixed> $business  a business_profile row
     * @param string|null          $billType  'tax_invoice' or 'bill_of_supply'
     * @return array<string, mixed>           the same row, switched-off fields blanked
     */
    public static function apply(array $business, ?string $billType = null): array
    {
        $out = $business;
        foreach (self::FIELDS as $key => [$field]) {
            if (!Settings::bool($key, true)) {
                $out[$field] = null;
            }
        }

        // A tax invoice must carry the supplier's GSTIN to be valid, so the
        // switch only applies to bills of supply.
        if ($billType === 'tax_invoice') {
            $out['gstin'] = $business['gstin'] ?? null;
        }

        // Never print the install placeholder, nor a legal name that only
        // repeats the display name.
        $legal = \trim((string) ($out['legal_name'] ?? ''));
        if ($legal === ''
            || \strcasecmp($legal, self::PLACEHOLDER_LEGAL_NAME) === 0
            || \strcasecmp($legal, (string) ($out['display_name'] ?? '')) === 0) {
            $out['legal_name'] = null;
        }

        return $out;
    }
}
