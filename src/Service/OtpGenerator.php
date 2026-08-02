<?php

namespace App\Service;

/**
 * Ports utils/generateOTP.js. Source not seen - assumed to be a standard
 * 6-digit numeric OTP based on the OTP entity's `otp` column (VARCHAR) and
 * the `body("otp").isNumeric()` validator in UserRoute.js. Confirm digit
 * count and whether leading zeros are preserved as a string against the
 * real utils/generateOTP.js.
 */
class OtpGenerator
{
    public function generate(int $digits = 6): string
    {
        $max = (10 ** $digits) - 1;

        return str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }
}
