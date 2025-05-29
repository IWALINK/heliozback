<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmailVerification;

class EmailController extends Controller
{
    public function generateUniqueCode()
    {
        $maxAttempts = 1000; // Adjust as needed

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // Generate a random 4-digit code
            $code = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            // Check if code already exists in database
            if (!EmailVerification::where('verification_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException("Failed to generate a unique code after $maxAttempts attempts.");
    }


    public function index($email)
    {
        $code = $this->generateUniqueCode();
        $verif = new EmailVerification();
        $verif->email = $email;
        $verif->verification_code =  $code;
        $verif->verification_code_end =  $this->addMinutes();
        $verif->save();
        return  $code;
    }

    public function addMinutes()
    {
        $minutes_to_add = 30;
        $time = new \DateTime();
        $time->add(new \DateInterval('PT' . $minutes_to_add . 'M'));
        $stamp = $time->format('Y-m-d H:i:s');
        return  $stamp;
    }
}
