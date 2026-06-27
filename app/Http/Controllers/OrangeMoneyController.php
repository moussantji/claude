<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\OrangeMoneyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OrangeMoneyController extends Controller
{
    /**
     * Customer is redirected here by Orange after the payment page. We confirm
     * the final status server-side before activating anything.
     */
    public function return(Request $request, OrangeMoneyService $orange)
    {
        $subscriptionId = $request->session()->pull('om_pending_subscription');
        $subscription = $subscriptionId ? Subscription::find($subscriptionId) : null;

        if (! $subscription || $subscription->user_id !== $request->user()?->id) {
            return redirect()->route('dashboard');
        }

        if ($subscription->isActive()) {
            return redirect()->route('dashboard')->with('status', __('Your subscription is active.'));
        }

        $status = $orange->checkStatus(
            $subscription->payment_reference,
            (int) round($subscription->amount_paid),
            (string) $subscription->payment_token
        );

        if ($status === 'SUCCESS') {
            $subscription->activate();

            return redirect()->route('dashboard')->with(
                'status',
                __('Payment received via Orange Money! Your :plan plan is now active.', ['plan' => $subscription->package->name])
            );
        }

        if (in_array($status, ['PENDING', 'INITIATED'], true)) {
            return redirect()->route('dashboard')->with(
                'status',
                __('Your Orange Money payment is being processed. We will activate your plan once it is confirmed.')
            );
        }

        $subscription->update(['status' => 'cancelled']);

        return redirect()->route('dashboard')->with('error', __('Your Orange Money payment did not complete.'));
    }

    /**
     * Customer cancelled on Orange's page.
     */
    public function cancel(Request $request)
    {
        $subscriptionId = $request->session()->pull('om_pending_subscription');
        if ($subscriptionId && $subscription = Subscription::find($subscriptionId)) {
            if ($subscription->user_id === $request->user()?->id && ! $subscription->isActive()) {
                $subscription->update(['status' => 'cancelled']);
            }
        }

        return redirect()->route('packages.index')->with('error', __('Payment cancelled.'));
    }

    /**
     * Server-to-server notification webhook from Orange Money (CSRF-exempt).
     */
    public function notify(Request $request, OrangeMoneyService $orange)
    {
        $notifToken = $request->input('notif_token');
        $status = $request->input('status');

        Log::info('Orange Money notification', $request->all());

        $subscriptionId = $notifToken ? Cache::get('om_notif_' . $notifToken) : null;
        $subscription = $subscriptionId ? Subscription::find($subscriptionId) : null;

        if (! $subscription) {
            return response()->json(['ok' => false], 404);
        }

        if ($status === 'SUCCESS' && ! $subscription->isActive()) {
            // Re-verify with the status endpoint before activating.
            $verified = $orange->checkStatus(
                $subscription->payment_reference,
                (int) round($subscription->amount_paid),
                (string) $subscription->payment_token
            );

            if ($verified === 'SUCCESS') {
                $subscription->activate();
            }
        } elseif (in_array($status, ['FAILED', 'EXPIRED'], true) && ! $subscription->isActive()) {
            $subscription->update(['status' => 'cancelled']);
        }

        return response()->json(['ok' => true]);
    }
}
