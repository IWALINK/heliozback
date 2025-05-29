<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\AuthController;

class CartController extends Controller
{

    public function store(Request $request)
    {
        $user = AuthController::get_user($request);
        $request->validate([
            'product_id' => 'required|string',
            'name' => 'required|string',
            'price' => 'required|numeric',
            'quantity' => 'required|integer',
            'type' => 'required|in:revenue,usage',
            'image' => 'required|string'
        ]);

        // Check if item already exists in cart
        $existingItem = Cart::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->where('type', $request->type)
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $request->quantity;
            $existingItem->save();
            return response()->json($existingItem);
        }

        // Create new cart item
        $cartItem = Cart::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'name' => $request->name,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'type' => $request->type,
            'image' => $request->image
        ]);

        return response()->json($cartItem, 201);
    }

    public function update(Request $request, $productId)
    {
        $user = AuthController::get_user($request);
        $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:revenue,usage'
        ]);

        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->where('type', $request->type)
            ->firstOrFail();

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json($cartItem);
    }

    public function destroy(Request $request, $productId)
    {
        $user = AuthController::get_user($request);
        $request->validate([
            'type' => 'required|in:revenue,usage'
        ]);

        Cart::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->where('type', $request->type)
            ->delete();

        return response()->json(['message' => 'Cart item removed']);
    }

    public function clear(Request $request)
    {
        $user = AuthController::get_user($request);
        Cart::where('user_id', $user->id)->delete();
        return response()->json(['message' => 'Cart cleared']);
    }

    public function index(Request $request)
    {
        $user = AuthController::get_user($request);
        $cartItems = Cart::where('user_id', $user->id)->get();
        return response()->json($cartItems);
    }
}
