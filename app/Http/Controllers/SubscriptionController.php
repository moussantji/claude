<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Subscription;
use App\Services\OrangeMoneyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
            'orangeApiEnabled' => app(OrangeMoneyService::class)->apiEnabled(),
        ]);
    }

    /**
     * Process a checkout. Orange Money uses the real Web Payment API when
     * configured; every other method (and Orange Money in demo mode) uses a
     * simulated instant activation.
     */
    public function store(Request $request, Package $package, OrangeMoneyService $orange)
    {
        abort_unless($package->is_active, 404);

        $validated = $request->validate([
            'payment_method' => ['required', 'in:card,paypal,crypto,orange_money'],
            'orange_phone' => ['nullable', 'string', 'max:30'],
            'agree' => ['accepted'],
        ]);

        $user = $request->user();

        // --- Real Orange Money Web Payment flow -----------------------------
        if ($validated['payment_method'] === 'orange_money' && $orange->apiEnabled()) {
            return $this->startOrangeMoneyPayment($user, $package, $validated, $orange);
        }

        // --- Simulated / instant flow ---------------------------------------
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'pending',
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $this->buildReference($validated),
            'amount_paid' => $package->price,
        ]);
        $subscription->activate();

        $message = $validated['payment_method'] === 'orange_money'
            ? __('Payment received via Orange Money! Your :plan plan is now active.', ['plan' => $package->name])
            : __('Payment successful! Your :plan plan is now active.', ['plan' => $package->name]);

        return redirect()->route('dashboard')->with('status', $message);
    }

    private function startOrangeMoneyPayment($user, Package $package, array $validated, OrangeMoneyService $orange)
    {
        $orderId = 'OM-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5));

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'pending',
            'payment_method' => 'orange_money',
            'payment_reference' => $orderId,
            'amount_paid' => $package->price,
        ]);

        $result = $orange->initiatePayment([
            'order_id' => $orderId,
            'amount' => (int) round($package->price),
            'return_url' => route('payment.orange.return'),
            'cancel_url' => route('payment.orange.cancel'),
            'notif_url' => route('payment.orange.notify'),
            'reference' => config('app.name') . ' - ' . $package->name,
        ]);

        if (! $result || empty($result['payment_url'])) {
            $subscription->update(['status' => 'cancelled']);

            return redirect()->route('subscribe.show', $package)
                ->with('error', __('We could not reach Orange Money right now. Please try again.'));
        }

        $subscription->update(['payment_token' => $result['pay_token']]);

        // Remember which subscription to finalise on return, and map the
        // notif_token to it for the asynchronous webhook.
        $request = request();
        $request->session()->put('om_pending_subscription', $subscription->id);
        if (! empty($result['notif_token'])) {
            Cache::put('om_notif_' . $result['notif_token'], $subscription->id, now()->addHours(2));
        }

        return redirect()->away($result['payment_url']);
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

    private function buildReference(array $validated): string
    {
        if (($validated['payment_method'] ?? null) === 'orange_money') {
            return 'OM-' . substr(preg_replace('/\D/', '', $validated['orange_phone'] ?? ''), -4) . '-' . strtoupper(Str::random(6));
        }

        return strtoupper($validated['payment_method'] ?? 'PAY') . '-' . strtoupper(Str::random(8));
    }
}
