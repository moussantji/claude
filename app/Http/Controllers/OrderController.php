<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class OrderController extends Controller
{
    /**
     * Checkout: choose a payment method, see the procedure + QR, enter the
     * number used to pay.
     */
    public function create(Package $package)
    {
        abort_unless($package->is_active, 404);

        return view('orders.create', [
            'package' => $package,
            'methods' => PaymentMethod::active()->ordered()->get(),
        ]);
    }

    public function store(Request $request, Package $package)
    {
        abort_unless($package->is_active, 404);

        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payment_number' => ['required', 'string', 'max:40'],
        ]);

        $method = PaymentMethod::active()->findOrFail($validated['payment_method_id']);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'package_id' => $package->id,
            'package_name' => $package->name,
            'payment_method_id' => $method->id,
            'payment_method_name' => $method->name,
            'payment_number' => $validated['payment_number'],
            'amount' => $package->price,
            'currency' => config('streamwave.currency', 'FCFA'),
            'status' => 'pending',
        ]);

        // Notify every administrator (in-app + email) with a link to fulfil it.
        Notification::send(User::admins()->get(), new NewOrderNotification($order));

        return redirect()->route('orders.show', $order)
            ->with('status', __('Your order has been placed! We will confirm your access shortly.'));
    }

    public function index(Request $request)
    {
        return view('orders.index', [
            'orders' => $request->user()->orders()->with('package')->paginate(10),
        ]);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load('paymentMethod');

        return view('orders.show', ['order' => $order]);
    }
}
