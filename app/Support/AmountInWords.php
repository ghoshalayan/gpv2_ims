<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Convert a positive amount in INR (rupees + paise) to its Indian English wording.
 * Indian numbering uses lakhs and crores (groups of 2 after the first 3 digits).
 *
 *   1234567 -> "Twelve Lakh Thirty Four Thousand Five Hundred Sixty Seven"
 *   1234.50 -> "One Thousand Two Hundred Thirty Four Rupees And Fifty Paise Only"
 *
 * Caps at ₹999,99,99,999.99 (a hundred crore − 1).
 */
class AmountInWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen',
        'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function inr(float $amount): string
    {
        if ($amount < 0) {
            return 'Minus ' . self::inr(\abs($amount));
        }
        $amount = \round($amount, 2);
        $rupees = (int) \floor($amount);
        $paise  = (int) \round(($amount - $rupees) * 100);

        if ($rupees === 0 && $paise === 0) {
            return 'Zero Rupees Only';
        }

        $parts = [];
        if ($rupees > 0) {
            $parts[] = \trim(self::convertIndian($rupees)) . ' Rupees';
        }
        if ($paise > 0) {
            $parts[] = \trim(self::convertHundreds($paise)) . ' Paise';
        }
        return \implode(' And ', $parts) . ' Only';
    }

    /** Indian numbering: crore-lakh-thousand-hundred. */
    private static function convertIndian(int $n): string
    {
        if ($n === 0) return 'Zero';
        $words = '';
        $crore    = \intdiv($n, 10000000); $n %= 10000000;
        $lakh     = \intdiv($n, 100000);   $n %= 100000;
        $thousand = \intdiv($n, 1000);     $n %= 1000;
        if ($crore > 0)    { $words .= self::convertHundreds($crore) . ' Crore '; }
        if ($lakh > 0)     { $words .= self::convertHundreds($lakh) . ' Lakh '; }
        if ($thousand > 0) { $words .= self::convertHundreds($thousand) . ' Thousand '; }
        if ($n > 0)        { $words .= self::convertHundreds($n); }
        return \trim(\preg_replace('/\s+/', ' ', $words) ?? '');
    }

    /** Convert 0..999 to words. */
    private static function convertHundreds(int $n): string
    {
        if ($n === 0) return '';
        $out = '';
        if ($n >= 100) {
            $out .= self::ONES[\intdiv($n, 100)] . ' Hundred ';
            $n %= 100;
        }
        if ($n >= 20) {
            $out .= self::TENS[\intdiv($n, 10)];
            $n %= 10;
            if ($n > 0) {
                $out .= ' ' . self::ONES[$n];
            }
        } elseif ($n > 0) {
            $out .= self::ONES[$n];
        }
        return \trim(\preg_replace('/\s+/', ' ', $out) ?? '');
    }
}
