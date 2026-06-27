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

        $validated = $request->validate([
            'payment_method' => ['required', 'in:card,paypal,crypto,orange_money'],
            'orange_phone' => ['required_if:payment_method,orange_money', 'nullable', 'string', 'max:30'],
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
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $this->buildReference($validated),
            'amount_paid' => $package->price,
            'starts_at' => Carbon::now(),
            'ends_at' => $startsAt->copy()->addDays($package->duration_days),
        ]);

        $message = $validated['payment_method'] === 'orange_money'
            ? __('Payment received via Orange Money! Your :plan plan is now active.', ['plan' => $package->name])
            : __('Payment successful! Your :plan plan is now active.', ['plan' => $package->name]);

        return redirect()->route('dashboard')->with('status', $message);
    }

    private function buildReference(array $validated): string
    {
        if (($validated['payment_method'] ?? null) === 'orange_money') {
            return 'OM-' . substr(preg_replace('/\D/', '', $validated['orange_phone'] ?? ''), -4) . '-' . strtoupper(\Illuminate\Support\Str::random(6));
        }

        return strtoupper($validated['payment_method'] ?? 'PAY') . '-' . strtoupper(\Illuminate\Support\Str::random(8));
    }

    /**
     * Cancel (mark inactive) one of the user's subscriptions.
     */
    public function cancel(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $subscription->update(['status' => 'cancelled']);

        return redirect()->route('dashboard')->with('status', __('Your subscription has been cancelled.'));
    }
}
