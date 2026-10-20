<?php

namespace App\Pickrush\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Scannable codes for labels and manifests, drawn as plain HTML (bars and squares) so they print
 * from the browser and from the PDF engine without any image extension. Both codes carry only the
 * tracking or manifest number: hub handheld scanners read the barcode, phone cameras the QR.
 */
final class Codes
{
    /** Code 128 bar patterns (bar/space widths) for values 0–106, from the standard. */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /**
     * Code 128 (set B) as alternating bar/space module widths, starting with a bar.
     *
     * @return list<int>
     */
    public static function code128(string $text): array
    {
        $values = [self::START_B];
        foreach (str_split($text) as $char) {
            $code = ord($char);
            if ($code < 32 || $code > 126) {
                throw new \InvalidArgumentException('Code 128 set B covers printable ASCII only.');
            }
            $values[] = $code - 32;
        }
        $checksum = self::START_B;
        foreach (array_slice($values, 1) as $position => $value) {
            $checksum += ($position + 1) * $value;
        }
        $values[] = $checksum % 103;
        $values[] = self::STOP;

        $widths = [];
        foreach ($values as $value) {
            foreach (str_split(self::PATTERNS[$value]) as $width) {
                $widths[] = (int) $width;
            }
        }

        return $widths;
    }

    /**
     * QR code as rows of dark (true) and light (false) modules, quiet zone included.
     *
     * @return list<list<bool>>
     */
    public static function qr(string $text): array
    {
        $options = new QROptions(['eccLevel' => EccLevel::M, 'addQuietzone' => true, 'quietzoneSize' => 2]);

        return (new QRCode($options))->addByteSegment($text)->getQRMatrix()->getMatrix(true);
    }
}
