<?php

declare(strict_types=1);

namespace App\Emails\Shop;

use App\Helpers\SMIME;
use App\Models\Shop\OrderQueue\ShopOrderQueue;
use Closure;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

class OrderSetupFailed extends Notification
{
    /**
     * The callback that should be used to create the verify email URL.
     *
     * @var Closure|null
     */
    public static ?Closure $createUrlCallback;

    /**
     * The callback that should be used to build the mail message.
     *
     * @var Closure|null
     */
    public static ?Closure $toMailCallback;

    /**
     * Get the notification's channels.
     *
     * @param ShopOrderQueue $notifiable
     *
     * @return array
     */
    public function via(ShopOrderQueue $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param ShopOrderQueue $notifiable
     *
     * @return MailMessage
     */
    public function toMail(ShopOrderQueue $notifiable): MailMessage
    {
        $shopOrdersUrl = $this->shopOrdersUrl($notifiable);

        if (! empty(static::$toMailCallback)) {
            return call_user_func(static::$toMailCallback, $notifiable, $shopOrdersUrl);
        }

        return $this->buildMailMessage($shopOrdersUrl, $notifiable);
    }

    /**
     * Get the support ticket creation email notification mail message for the given URL.
     *
     * @param string         $url
     * @param ShopOrderQueue $notifiable
     *
     * @return MailMessage
     */
    protected function buildMailMessage(string $url, ShopOrderQueue $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject(Lang::get('Order setup failed: ') . $notifiable->number)
            ->line(Lang::get('Your order :id has failed to be set up :fails times. The setup process will not be retried until the order has been checked by an employee. This might take a while. You will be informed when the order status changes.', [
                'id'    => $notifiable->number,
                'fails' => $notifiable->fails,
            ]))
            ->action(Lang::get('View Orders'), $url)
            ->replyTo(config('mail.support.address'), config('mail.support.name'));

        return SMIME::sign($message);
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param ShopOrderQueue $notifiable
     *
     * @return string
     */
    protected function shopOrdersUrl(ShopOrderQueue $notifiable): string
    {
        if (! empty(static::$createUrlCallback)) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        return URL::signedRoute('customer.shop.orders');
    }

    /**
     * Set a callback that should be used when creating the email verification URL.
     *
     * @param Closure $callback
     */
    public static function createUrlUsing(Closure $callback)
    {
        static::$createUrlCallback = $callback;
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param Closure $callback
     */
    public static function toMailUsing(Closure $callback)
    {
        static::$toMailCallback = $callback;
    }
}
