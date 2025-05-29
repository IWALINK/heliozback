<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Auth\EmailController;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailConfirmation;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\UserSession;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Mail\ResetPassword;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $messages = [
                'full_name.required' => 'Le nom complet est requis.',
                'full_name.string' => 'Le nom complet doit être une chaîne de caractères.',
                'full_name.min' => 'Le nom complet doit contenir au moins :min caractères.',
                'full_name.max' => 'Le nom complet ne peut pas dépasser :max caractères.',
                'email.required' => 'L\'adresse e-mail est requise.',
                'email.email' => 'L\'adresse e-mail n\'est pas valide.',
                'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
                'phone_number.required' => 'Le numéro de téléphone est requis.',
                'phone_number.string' => 'Le numéro de téléphone doit être une chaîne de caractères.',
                'address.required' => 'L\'adresse est requise.',
                'address.string' => 'L\'adresse doit être une chaîne de caractères.',
                'password.required' => 'Le mot de passe est requis.',
                'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
                'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
                'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre.',
                'referral_code.exists' => 'Le code de parrainage n\'est pas valide.',
            ];

            $request->validate([
                'full_name' => ['required', 'string', 'min:2', 'max:50'],
                'email' => ['required', 'string', 'email', 'unique:users'],
                'phone_number' => ['required', 'string'],
                'address' => ['required', 'string'],
                'password' => [
                    'required',
                    'confirmed',
                    'min:8',
                    'regex:/^(?=.*[A-Z])(?=.*[0-9])[a-zA-Z0-9]+$/'
                ],
                'referral_code' => ['nullable', 'string', 'exists:users,referral_code'],
            ], $messages);

            // Find referring user if referral code is provided
            $referred_by = null;
            if ($request->referral_code) {
                $referred_by = User::where('referral_code', $request->referral_code)->first()->id;
            }
            $user = User::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'password' => Hash::make($request->password),
                'referred_by' => $referred_by,
            ]);

            $email_controller =  new EmailController();
            $unique_code =  $email_controller->index($request->email);
            $info = [
                'user_name' =>  $user->full_name,
                'code' =>   $unique_code,
                'mail' => md5($request->email),
            ];
            Mail::to($request->email)->queue(new EmailConfirmation($info));

            $token = $user->createToken('auth_token')->plainTextToken;

            UserSession::create([
                'user_id' => $user->id,
                'session_id' => $token,
                'token' => $token,
                'expires_at' => Carbon::now()->addYears(5),
            ]);
            return response()->json([
                "result" => 'Account Created !'
            ])->cookie('session_id', $token, 2628000, null, null, true, true, false, 'None');
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // Check if email is verified and resend verification if needed
        if (!$user->email_verified_at) {
            // Generate and send new verification code
            $email_controller = new EmailController();
            $unique_code = $email_controller->index($user->email);
            $info = [
                'user_name' => $user->full_name,
                'code' => $unique_code,
                'mail' => md5($user->email),
            ];
            Mail::to($user->email)->queue(new EmailConfirmation($info));

            throw ValidationException::withMessages([
                'email' => ['Veuillez vérifier votre adresse e-mail avant de vous connecter. Un nouveau code de vérification a été envoyé.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Create user session
        UserSession::create([
            'user_id' => $user->id,
            'session_id' => $token,
            'token' => $token,
            'expires_at' => Carbon::now()->addYears(5),
        ]);

        return response()->json([
            'message' => 'Connexion réussie'
        ])->cookie('session_id', $token, 2628000, null, null, true, true, false, 'None');
    }

    public function logout(Request $request)
    {
        $sessionId = $request->cookie('session_id');
        UserSession::where('session_id', $sessionId)->delete();

        return response()->json(['message' => 'Logged out'])
            ->cookie('session_id', $sessionId, 2628000, null, null, true, true, false, 'None');
    }


    public static function get_user(Request $request)
    {
        $sessionId = $request->cookie('session_id');
        $session = UserSession::where('session_id', $sessionId)
            ->where('expires_at', '>', Carbon::now())
            ->first();
        if (!$session) {
            return response()->json(['message' => 'Invalid token'], 401);
        }
        $token = PersonalAccessToken::findToken($session->token);
        $user = $token->tokenable;
        Auth::login($user);
        $user = $request->user();
        return $user;
    }

    public function verify_otp(Request $request)
    {
        $user = self::get_user($request);
        $user->createAsStripeCustomer();
        $request->validate([
            'otp' => ['required', 'string'],
        ]);

        if (
            EmailVerification::where("verification_code", "=", $request->otp)
            ->where('verification_code_end', '>=', date('Y-m-d H:i:s'))->count() == 1
        ) {
            $user = User::where('email',   $user->email)->first();
            $user->email_verified_at = date('Y-m-d H:i:s');
            $user->save();
            return response()->json(['message' => 'Email verified successfully']);
        }
        return response()->json(['message' => 'Invalid code'], 401);
    }

    public function getProfile(Request $request)
    {
        try {
            $user = self::get_user($request);

            return response()->json([
                'success' => true,
                'user' => [
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'address' => $user->address,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil'
            ], 401);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = self::get_user($request);

            $messages = [
                'full_name.min' => 'Le nom complet doit contenir au moins :min caractères.',
                'full_name.max' => 'Le nom complet ne peut pas dépasser :max caractères.',
                'email.unique' => 'Cette adresse email est déjà utilisée.',
                'phone_number.required' => 'Le numéro de téléphone est requis.',
                'current_password.required' => 'Le mot de passe actuel est requis pour changer le mot de passe.',
                'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
                'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre.',
                'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            ];

            $validator = Validator::make($request->all(), [
                'full_name' => ['sometimes', 'string', 'min:2', 'max:50'],
                'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
                'phone_number' => ['sometimes', 'required', 'string', function ($attribute, $value, $fail) {
                    if (!preg_match('/^\+[1-9]\d{1,14}$/', $value)) {
                        $fail('Le numéro de téléphone doit être au format international valide.');
                    }
                }],
                'address' => ['sometimes', 'string'],
                'current_password' => ['required_with:password'],
                'password' => [
                    'sometimes',
                    'confirmed',
                    'min:8',
                    'regex:/^(?=.*[A-Z])(?=.*[0-9])[a-zA-Z0-9]+$/'
                ],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify current password if changing password
            if ($request->has('password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le mot de passe actuel est incorrect'
                    ], 422);
                }
            }

            // Update user information
            $user->full_name = $request->full_name ?? $user->full_name;
            $user->email = $request->email ?? $user->email;
            $user->phone_number = $request->phone_number ?? $user->phone_number;
            $user->address = $request->address ?? $user->address;

            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil'
            ], 500);
        }
    }

    public function send_reset_password_code(Request $request)
    {
        $verify_if_email_exist = User::where('email', "=",  $request->email)->count();
        $email_controller = new EmailController();
        if ($verify_if_email_exist > 0) {
            $code = $email_controller->index($request->email);
            $info = [
                'requestCode' =>   $code
            ];
            Mail::to($request->email)->queue(new ResetPassword($info));
            //->later(now()->addMinutes(1), new ResetPassword($info));
            //
            return "mail have been sent";
        } else {
            return response(['errors' => "Aucun compte n'est associé à cet email."], 422);
        }
        //  abort(403, 'Unauthorized. The provided credentials do not match our records.');
    }

    public function update_password_from_email_code(Request $request)
    {
        $messages = [
            'code.required' => 'Le code de vérification est requis.',
            'code.digits' => 'Le code doit contenir exactement :digits chiffres.',
            'password.required' => 'Le mot de passe est requis.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre.',
        ];

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'digits:4'],
            'password' => [
                    'required',
                    'confirmed',
                    'min:8',
                    'regex:/^(?=.*[A-Z])(?=.*[0-9])[a-zA-Z0-9]+$/'
                ],
        ], $messages);

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()], 422);
        }

        if (EmailVerification::where("verification_code", "=", $request->code)->where('verification_code_end', '>=', date('Y-m-d H:i:s'))->count() == 1) {
            User::where(
                'email',
                "=",
                EmailVerification::where("verification_code", "=", $request->code)->where('verification_code_end', '>=', date('Y-m-d H:i:s'))->value("email")
            )->update(['password' => Hash::make($request->password)]);

            EmailVerification::where("email", "=",  EmailVerification::where("verification_code", "=", $request->code)
                ->where('verification_code_end', '>=', date('Y-m-d H:i:s'))->value("email"))->delete();
            return "code updated";
        } else {
            return response(['errors' => "This code expire or invalide."], 422);
        }
    }
}
