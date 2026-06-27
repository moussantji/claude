<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('admin.orders.edit', $this->order);

        return (new MailMessage)
            ->subject(__('New order :ref', ['ref' => $this->order->reference]))
            ->greeting(__('New order received'))
            ->line(__(':name placed an order.', ['name' => $this->order->user->name]))
            ->line(__('Package: :package', ['package' => $this->order->package_name]))
            ->line(__('Amount: :amount', ['amount' => fcfa($this->order->amount)]))
            ->line(__('Payment method: :method', ['method' => $this->order->payment_method_name]))
            ->line(__('Payment number: :number', ['number' => $this->order->payment_number ?: '—']))
            ->action(__('Edit the order'), $url)
            ->line(__('Fill in the username, password and DNS to fulfil this order.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_order',
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'message' => __(':name placed an order (:package).', [
                'name' => $this->order->user->name,
                'package' => $this->order->package_name,
            ]),
            'url' => route('admin.orders.edit', $this->order),
        ];
    }
}
