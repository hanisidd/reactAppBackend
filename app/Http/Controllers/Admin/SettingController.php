<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Default fallback email subject and template HTML
     */
    private $defaultSubject = "Your Digital Product Purchase - {product_name}";
    private $defaultBody = "<p>Hi <strong>{customer_name}</strong>,</p><p>Thank you for your order <strong>#{order_id}</strong>!</p><p>You can download your digital file here:</p><p><a href=\"{download_link}\">{product_name} Download</a></p><p>Best regards,<br>Store Team</p>";

    public function getEmailTemplate()
    {
        $subjectSetting = Setting::where('key', 'digital_email_subject')->first();
        $bodySetting = Setting::where('key', 'digital_email_body')->first();

        return response()->json([
            'subject' => $subjectSetting ? $subjectSetting->value : $this->defaultSubject,
            'body' => $bodySetting ? $bodySetting->value : $this->defaultBody,
        ]);
    }

    public function updateEmailTemplate(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        Setting::updateOrCreate(
            ['key' => 'digital_email_subject'],
            ['value' => $request->subject]
        );

        Setting::updateOrCreate(
            ['key' => 'digital_email_body'],
            ['value' => $request->body]
        );

        return response()->json([
            'message' => 'Email template updated successfully.',
            'subject' => $request->subject,
            'body' => $request->body,
        ]);
    }
}
