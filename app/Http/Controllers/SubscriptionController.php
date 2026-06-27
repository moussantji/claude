<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Show the checkout page for a package.
     */
    public function show(Package $package)
    {
        abort_unless($package->is_active, 404);

        return view('subscribe.show', [
            'package' => $package,
        ]);
    }

    /**
     * "Purchase" a package and create the subscription.
     *
     * NOTE: This is a simulated checkout for demonstration. In production you
     * would integrate a real payment gateway (Stripe, PayPal, etc.) and only
     * activate the subscription after a confirmed payment webhook.
     */
    public function store(Request $request, Package $package)
    {
        abort_unless($package->is_active, 404);

        $request->validate([
            'payment_method' => ['required', 'in:card,paypal,crypto'],
            'agree' => ['accepted'],
        ]);

        $user = $request->user();

        // Stack onto any current active subscription's end date.
        $current = $user->activeSubscription();
        $startsAt = $current && $current->ends_at?->isFuture()
            ? $current->ends_at
            : Carbon::now();

        Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'amount_paid' => $package->price,
            'starts_at' => Carbon::now(),
            'ends_at' => $startsAt->copy()->addDays($package->duration_days),
        ]);

        return redirect()->route('dashboard')->with(
            'status',
            "Payment successful! Your {$package->name} plan is now active."
        );
    }

    /**
     * Cancel (mark inactive) one of the user's subscriptions.
     */
    public function cancel(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $subscription->update(['status' => 'cancelled']);

        return redirect()->route('dashboard')->with('status', 'Your subscription has been cancelled.');
    }
}
