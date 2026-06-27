<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return view('admin.payment_methods.index', [
            'methods' => PaymentMethod::ordered()->get(),
        ]);
    }

    public function create()
    {
        return view('admin.payment_methods.form', [
            'method' => new PaymentMethod(['is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['qr_path'] = $this->storeQr($request);

        PaymentMethod::create($data);

        return redirect()->route('admin.payment-methods.index')->with('status', __('Payment method created.'));
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return view('admin.payment_methods.form', ['method' => $paymentMethod]);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $this->validated($request);

        if ($newQr = $this->storeQr($request)) {
            if ($paymentMethod->qr_path) {
                Storage::disk('public')->delete($paymentMethod->qr_path);
            }
            $data['qr_path'] = $newQr;
        }

        $paymentMethod->update($data);

        return redirect()->route('admin.payment-methods.index')->with('status', __('Payment method updated.'));
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->qr_path) {
            Storage::disk('public')->delete($paymentMethod->qr_path);
        }
        $paymentMethod->delete();

        return redirect()->route('admin.payment-methods.index')->with('status', __('Payment method deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'qr' => ['nullable', 'image', 'max:4096'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        unset($data['qr']);

        return $data;
    }

    private function storeQr(Request $request): ?string
    {
        if ($request->hasFile('qr')) {
            return $request->file('qr')->store('qrcodes', 'public');
        }

        return null;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (PaymentMethod::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
