<?php
declare(strict_types=1);

namespace App\Support;

/**
 * CR-5 — WhatsApp "click to chat" links (https://wa.me/…).
 *
 * Deliberately the free, zero-infrastructure mechanism: the link opens WhatsApp with
 * the recipient and message pre-filled, and the user presses send (attaching the
 * printed PDF themselves if they want the full statement). No Meta Business account,
 * no approved templates, no per-message cost, and no publicly-hosted file — the app
 * stores no files and has no outbound HTTP dependency.
 *
 * wa.me needs a full international number with no punctuation and no leading '+'.
 * Numbers in this system are Indian and stored in assorted shapes ('9876543210',
 * '+91 98765 43210', '098765 43210'), so normalise before building the link.
 */
class Whatsapp
{
    private const DEFAULT_COUNTRY_CODE = '91';

    /**
     * Normalise a stored phone to wa.me digits, or null when it cannot be trusted.
     *
     * Returning null (rather than guessing) matters: a wrong number would send a
     * customer's financial statement to a stranger. Callers hide the button on null.
     */
    public static function normalize(?string $phone, string $countryCode = self::DEFAULT_COUNTRY_CODE): ?string
    {
        $digits = \preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // '0XXXXXXXXXX' — strip the domestic trunk prefix.
        if (\strlen($digits) === 11 && \str_starts_with($digits, '0')) {
            $digits = \substr($digits, 1);
        }

        // Bare 10-digit national number — prepend the country code.
        if (\strlen($digits) === 10) {
            return $countryCode . $digits;
        }

        // Already carries a country code (e.g. 91XXXXXXXXXX).
        if (\strlen($digits) === 12 && \str_starts_with($digits, $countryCode)) {
            return $digits;
        }

        // Any other international-looking number: accept a plausible length as-is.
        if (\strlen($digits) >= 11 && \strlen($digits) <= 15) {
            return $digits;
        }

        // Too short / too long to be a real number — refuse rather than guess.
        return null;
    }

    /**
     * Build a click-to-chat URL, or null when the number is unusable.
     * The message is sent as a query parameter, so it must be URL-encoded.
     */
    public static function chatUrl(?string $phone, string $message = ''): ?string
    {
        $number = self::normalize($phone);
        if ($number === null) {
            return null;
        }
        $url = 'https://wa.me/' . $number;
        if ($message !== '') {
            $url .= '?text=' . \rawurlencode($message);
        }
        return $url;
    }
}
