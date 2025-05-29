<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use App\Http\Controllers\Auth\AuthController;

class ReferralController extends Controller
{
    public function getReferralCode(Request $request)
    {
        try {
            // Get the authenticated user
            $user = AuthController::get_user($request);

            // Get referral statistics
            $referralCount = User::where('referred_by', $user->id)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'referral_code' => $user->referral_code,
                    'referral_count' => $referralCount,
                    'referral_link' => env('FRONT_END_URL') . "/p/" . $user->referral_code
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate referral code'
            ], 500);
        }
    }

    public function showReferrals(Request $request)
    {
        $user = AuthController::get_user($request);
        $referrals = $user->referrals()->get();

        return view('referral.referrals', compact('referrals'));
    }

    public function getReferralEarnings(Request $request)
    {
        try {
            $user = AuthController::get_user($request);
            $referrals = User::where('referred_by', $user->id)
                ->with(['orders' => function ($query) {
                    $query->where('status', 'completed')
                        ->where('payment_status', 'paid');
                }])
                ->get();

            $totalEarnings = 0;
            $referralCount = $referrals->count(); // Nombre total de personnes parrainées
            $singleReferralEarnings = 25;
            if ($referralCount > 0) {
                foreach ($referrals as $referral) {
                    if ($referral->orders->count() > 0) {
                        $totalEarnings += 25;
                    }
                }
            } elseif ($referralCount >= 10) {
                foreach ($referrals as $referral) {
                    if ($referral->orders->count() > 0) {
                        $totalEarnings += 50;
                    }
                }
            } elseif ($referralCount >= 50) {
                foreach ($referrals as $referral) {
                    if ($referral->orders->count() > 0) {
                        $totalEarnings += 75;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_earnings' => $totalEarnings,
                    'referral_count' => $referralCount,
                    'referrals' => $referrals->map(function ($referral) {
                        return [
                            'name' => $referral->full_name,
                            'email' => $referral->email,
                            'orders_count' => $referral->orders->count()
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate referral earnings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


//
