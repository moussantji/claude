<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCompletedNotification extends Notification
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
        return (new MailMessage)
            ->subject(__('Your order :ref is confirmed', ['ref' => $this->order->reference]))
            ->greeting(__('Your subscription is ready!'))
            ->line(__('Your order :ref has been validated. Here are your connection details:', ['ref' => $this->order->reference]))
            ->line(__('Username: :value', ['value' => $this->order->iptv_username]))
            ->line(__('Password: :value', ['value' => $this->order->iptv_password]))
            ->line(__('DNS / Server: :value', ['value' => $this->order->iptv_dns]))
            ->action(__('Go to my dashboard'), route('dashboard'))
            ->line(__('Load these details into your IPTV player to start watching. Thank you!'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_completed',
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'message' => __('Your order :ref is confirmed — your access details are ready.', ['ref' => $this->order->reference]),
            'url' => route('dashboard'),
        ];
    }
}
