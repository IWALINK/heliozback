<?php

namespace App\Http\Controllers;

use App\Models\BankInformation;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AuthController;

class BankInformationController extends Controller
{
    public function index(Request $request)
    {
        $user = AuthController::get_user($request);
        $bankInfo = BankInformation::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => $bankInfo
        ]);
    }

    private function getBankValidationRules()
    {
        return [
            'account_holder_name' => ['required', 'string', 'min:2', 'max:50'],
            'bank_name' => ['required', 'string', 'min:2', 'max:50'],
            'iban' => [
                'required',
                'string'
            ],
            'bic' => ['nullable', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
            'account_number' => ['nullable', 'string'],
            'routing_number' => ['nullable', 'string'],
            'user_id' => ['required', 'exists:users,id']
        ];
    }

    public function store(Request $request)
    {
        $user = AuthController::get_user($request);
        $request->merge(['user_id' => $user->id]);

        $validated = $request->validate($this->getBankValidationRules(), [
            'account_holder_name.required' => 'Le nom du titulaire est requis',
            'account_holder_name.min' => 'Le nom doit contenir au moins 2 caractères',
            'account_holder_name.max' => 'Le nom est trop long',
            'bank_name.required' => 'Le nom de la banque est requis',
            'bank_name.min' => 'Le nom de la banque doit contenir au moins 2 caractères',
            'bank_name.max' => 'Le nom de la banque est trop long',
            'iban.required' => 'L\'IBAN est requis',
            'iban.regex' => 'Format IBAN européen invalide',
            'bic.regex' => 'Format BIC/SWIFT invalide',
        ]);

        return BankInformation::create($validated);
    }

    public function update(Request $request, BankInformation $bankInformation)
    {
        $user = AuthController::get_user($request);

        if ($bankInformation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier ces informations bancaires'
            ], 403);
        }

        $request->merge(['user_id' => $user->id]);
        $validated = $request->validate($this->getBankValidationRules());
        $bankInformation->update($validated);
        return $bankInformation;
    }
}
