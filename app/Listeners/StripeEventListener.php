<?php

namespace App\Listeners;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;
use Stripe\Stripe;
use App\Models\Orders;
use App\Models\Cart;
use Stripe\Invoice;
use App\Mail\OrderEmail;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReferralEarnings;

class StripeEventListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle received Stripe webhooks.
     *
     * @param \Laravel\Cashier\Events\WebhookReceived $event
     * @return void
     */
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] === 'checkout.session.completed') {
            Stripe::setApiKey(env('STRIPE_SECRET'));
            $invoices = Invoice::retrieve($event->payload["data"]["object"]["invoice"]);
            $session = $event->payload['data']['object'];
            $sessionId = $session['id'];

            // Get user_id from existing order
            $existingOrder = Orders::where('stripe_session_id', $sessionId)->first();
            if (!$existingOrder) {
                Log::error('Order not found for session', [
                    'session_id' => $sessionId
                ]);
                return;
            }
            $userId = $existingOrder->user_id;

            $amount = $session['amount_total'] / 100;
            $paymentStatus = $session['payment_status'];
            $paymentMethod = $session['payment_method_types'][0] ?? null;

            try {
                // Verify user exists before proceeding
                $user = User::where('id', $userId)->first();
                if (!$user) {
                    Log::error('User not found', [
                        'session_id' => $sessionId,
                        'user_id' => $userId
                    ]);
                    return;
                }

                // Retrieve the session with line items
                $stripeSession = \Stripe\Checkout\Session::retrieve([
                    'id' => $sessionId,
                    'expand' => ['line_items'],
                ]);

                // Create or update order
                $order = Orders::updateOrCreate(
                    ['stripe_session_id' => $sessionId],
                    [
                        'user_id' => $userId,
                        'status' => 'completed',
                        'total_amount' => $amount,
                        'currency' => strtoupper($session['currency']),
                        'items' => json_encode($stripeSession->line_items->data),
                        'payment_method' => $paymentMethod,
                        'payment_status' => $paymentStatus,
                        'invoice_url' => $invoices->hosted_invoice_url ?? null,
                        'paid_at' => now(),
                    ]
                );

                // Add user data to order object without saving to database
                $order->user_name = $user->full_name;
                $order->user_email = $user->email;

                // Send order confirmation email
                Mail::to($user->email)->queue(new OrderEmail($order));

                // Check if this is the user's first order
                $previousOrders = Orders::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->where('id', '!=', $order->id)
                    ->count();

                // Send email to referrer only if this is the user's first order
                if ($previousOrders === 0 && $user->referred_by) {
                    $referrer = User::find($user->referred_by);
                    if ($referrer) {
                        // Calculate earnings based on referral count
                        $referralCount = User::where('referred_by', $referrer->id)->count();
                        $earnings = 25; // Default earnings
                        if ($referralCount >= 50) {
                            $earnings = 75;
                        } elseif ($referralCount >= 10) {
                            $earnings = 50;
                        }

                        Mail::to($referrer->email)->queue(new ReferralEarnings([
                            'full_name' => $user->full_name,
                            'amount' => $earnings . ' CHF'
                        ]));
                    }
                }

                // Clear the user's cart using user ID
                Cart::where('user_id', $user->id)->delete();
                // //
                //                 Log::info('Order processed successfully', [
                //                     'order_id' => $order->id,
                //                     'session_id' => $sessionId,
                //                     'user_id' => $userId
                //                 ]);

                // Log::info($invoices);
            } catch (\Exception $e) {
                Log::error('Error processing order', [
                    'error' => $e->getMessage(),
                    'session_id' => $sessionId,
                    'user_id' => $userId
                ]);
            }
        }
    }
}
