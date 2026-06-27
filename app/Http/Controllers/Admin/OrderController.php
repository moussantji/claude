<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderCompletedNotification;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['user', 'package'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('reference', 'like', "%{$s}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'status' => $request->status,
            'search' => $request->search,
        ]);
    }

    public function edit(Order $order)
    {
        $order->load(['user', 'paymentMethod']);

        return view('admin.orders.edit', ['order' => $order]);
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,completed,rejected'],
            'iptv_username' => ['nullable', 'string', 'max:255'],
            'iptv_password' => ['nullable', 'string', 'max:255'],
            'iptv_dns' => ['nullable', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        // To mark an order completed, credentials must be present.
        if ($data['status'] === 'completed') {
            $request->validate([
                'iptv_username' => ['required', 'string', 'max:255'],
                'iptv_password' => ['required', 'string', 'max:255'],
                'iptv_dns' => ['required', 'string', 'max:255'],
            ], [], [
                'iptv_username' => __('Username'),
                'iptv_password' => __('Password'),
                'iptv_dns' => __('DNS / Server'),
            ]);
        }

        $wasCompleted = $order->isCompleted();

        $order->fill($data);
        if ($data['status'] === 'completed') {
            $order->processed_by = $request->user()->id;
            $order->processed_at = now();
        }
        $order->save();

        // Notify the customer (in-app + email) the first time it is completed.
        if ($order->isCompleted() && ! $wasCompleted) {
            $order->user->notify(new OrderCompletedNotification($order));

            return redirect()->route('admin.orders.index')
                ->with('status', __('Order completed — the customer has been notified by email.'));
        }

        return redirect()->route('admin.orders.index')->with('status', __('Order updated.'));
    }
}
