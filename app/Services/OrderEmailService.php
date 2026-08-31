<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

/**
 * Centralizes the "look up a Setting-backed email template, replace
 * {placeholders}, send it" logic that was previously duplicated between
 * OrderController::sendStatusEmail() and (missing entirely from)
 * StorefrontController::checkout(). Both now call this.
 */
class OrderEmailService
{
    private const TEMPLATES = [
        'order_placed' => [
            'subjectKey' => 'order_confirmation_subject',
            'bodyKey' => 'order_confirmation_body',
            'defaultSubject' => 'Order Confirmed - {order_id}',
            'defaultBody' => '<p>Hi <strong>{customer_name}</strong>,</p><p>Thanks for your order <strong>{order_id}</strong>! We\'ve received it and will keep you updated as it progresses.</p>',
        ],
        'confirmed' => [
            'subjectKey' => 'order_confirmation_subject',
            'bodyKey' => 'order_confirmation_body',
            'defaultSubject' => 'Order Confirmed - {order_id}',
            'defaultBody' => '<p>Hi <strong>{customer_name}</strong>,</p><p>Your order <strong>{order_id}</strong> has been received and confirmed. We will notify you as it progresses.</p>',
        ],
        'preparing' => [
            'subjectKey' => 'order_preparing_subject',
            'bodyKey' => 'order_preparing_body',
            'defaultSubject' => 'Your Order {order_id} is Being Prepared',
            'defaultBody' => '<p>Hi <strong>{customer_name}</strong>,</p><p>Your order <strong>{order_id}</strong> for <strong>{product_name}</strong> is now being prepared.</p>',
        ],
        'delivered' => [
            'subjectKey' => 'order_delivered_subject',
            'bodyKey' => 'order_delivered_body',
            'defaultSubject' => 'Your Order {order_id} Has Been Delivered',
            'defaultBody' => '<p>Hi <strong>{customer_name}</strong>,</p><p>Your order <strong>{order_id}</strong> has been delivered. Thank you for shopping with us!</p>',
        ],
        'cancelled' => [
            'subjectKey' => 'order_cancelled_subject',
            'bodyKey' => 'order_cancelled_body',
            'defaultSubject' => 'Order {order_id} Cancelled',
            'defaultBody' => '<p>Hi <strong>{customer_name}</strong>,</p><p>Your order <strong>{order_id}</strong> has been cancelled. If this was a mistake, please contact our support team.</p>',
        ],
    ];

    /**
     * Send the templated email for a given key ('order_placed', 'confirmed',
     * 'preparing', 'delivered', 'cancelled'). Returns true if sent.
     */
    public function send(Order $order, string $templateKey): bool
    {
        if (!isset(self::TEMPLATES[$templateKey])) {
            return false;
        }

        $tpl = self::TEMPLATES[$templateKey];

        $subjectTemplate = Setting::where('key', $tpl['subjectKey'])->value('value') ?? $tpl['defaultSubject'];
        $bodyTemplate = Setting::where('key', $tpl['bodyKey'])->value('value') ?? $tpl['defaultBody'];

        $productName = optional($order->product)->title
            ?? optional(optional($order->items->first() ?? null)->product)->title
            ?? 'Your Order';

        $replacements = [
            '{customer_name}' => $order->customer_name,
            '{order_id}' => $order->order_number,
            '{product_name}' => $productName,
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subjectTemplate);
        $bodyHtml = str_replace(array_keys($replacements), array_values($replacements), $bodyTemplate);

        try {
            Mail::html($bodyHtml, function ($message) use ($order, $subject) {
                $message->to($order->customer_email, $order->customer_name)
                    ->subject($subject);
            });
            return true;
        } catch (\Exception $e) {
            // Order actions should still succeed even if mail is unreachable.
            return false;
        }
    }
}
