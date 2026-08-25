<?php

declare(strict_types=1);

namespace Sukli\Services;

use Sukli\Core\Database;

/**
 * Zero-dependency Code 39 barcode generation (value assignment + SVG
 * rendering) for products that don't already have one, and for printable
 * labels. Code 39 is used (rather than Code 128/EAN) because its
 * character table is small and unambiguous to hand-encode correctly
 * without an external barcode library — this project has none.
 */
class BarcodeService
{
    /** Code 39: each value is 9 elements (bar,space,bar,space,bar,space,bar,space,bar); 1 = wide, 0 = narrow. */
    private const PATTERNS = [
        '0' => '000110100', '1' => '100100001', '2' => '001100001', '3' => '101100000',
        '4' => '000110001', '5' => '100110000', '6' => '001110000', '7' => '000100101',
        '8' => '100100100', '9' => '001100100', 'A' => '100001001', 'B' => '001001001',
        'C' => '101001000', 'D' => '000011001', 'E' => '100011000', 'F' => '001011000',
        'G' => '000001101', 'H' => '100001100', 'I' => '001001100', 'J' => '000011100',
        'K' => '100000011', 'L' => '001000011', 'M' => '101000010', 'N' => '000010011',
        'O' => '100010010', 'P' => '001010010', 'Q' => '000000111', 'R' => '100000110',
        'S' => '001000110', 'T' => '000010110', 'U' => '110000001', 'V' => '011000001',
        'W' => '111000000', 'X' => '010010001', 'Y' => '110010000', 'Z' => '011010000',
        '-' => '010000101', '.' => '110000100', ' ' => '011000100', '$' => '010101000',
        '/' => '010100010', '+' => '010001010', '%' => '000101010', '*' => '010010100',
    ];

    /** Generates a unique 12-digit numeric barcode for the store (retried on collision). */
    public static function generate(int $storeId): string
    {
        do {
            $code = (string) random_int(100000000000, 999999999999);
            $exists = Database::one("SELECT id FROM products WHERE store_id = ? AND barcode = ?", [$storeId, $code]);
        } while ($exists);

        return $code;
    }

    /** @return string An inline SVG rendering of the barcode, with human-readable text below. */
    public static function svg(string $value, int $narrow = 2, int $height = 60): string
    {
        $clean = strtoupper(preg_replace('/[^0-9A-Za-z\-. $\/+%]/', '', $value) ?? '');
        $wide = $narrow * 2;
        $chars = '*' . $clean . '*';

        $x = 0;
        $bars = '';
        for ($i = 0, $len = strlen($chars); $i < $len; $i++) {
            $pattern = self::PATTERNS[$chars[$i]] ?? self::PATTERNS['-'];
            $isBar = true;
            foreach (str_split($pattern) as $bit) {
                $w = $bit === '1' ? $wide : $narrow;
                if ($isBar) {
                    $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '" fill="#000"/>';
                }
                $x += $w;
                $isBar = !$isBar;
            }
            $x += $narrow; // inter-character gap
        }

        $totalWidth = $x;
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $totalWidth . '" height="' . ($height + 18) . '" viewBox="0 0 ' . $totalWidth . ' ' . ($height + 18) . '">'
            . $bars
            . '<text x="' . ($totalWidth / 2) . '" y="' . ($height + 14) . '" text-anchor="middle" font-family="monospace" font-size="13">' . htmlspecialchars($value) . '</text>'
            . '</svg>';
    }
}
