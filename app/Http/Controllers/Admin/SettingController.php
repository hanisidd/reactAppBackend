<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->all();

        if (!empty($settings['logo_path'])) {
            $settings['logo_url'] = asset('storage/' . $settings['logo_path']);
        }
        if (!empty($settings['hero_image_path'])) {
            $settings['hero_image_url'] = asset('storage/' . $settings['hero_image_path']);
        }

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $textFields = [
            'brand_name',
            'hero_heading',
            'hero_text',
            'tax_percentage',
            'delivery_fee',
            'footer_phone',
            'footer_email',
            'footer_address',
            'footer_whatsapp',
            'footer_instagram',
            'footer_facebook',
            'footer_about',
            'about_heading',
            'about_description',
            'about_f1_title',
            'about_f1_desc',
            'about_f2_title',
            'about_f2_desc',
            'about_f3_title',
            'about_f3_desc',
            'digital_email_subject',
            'digital_email_body',
            'order_confirmation_subject',
            'order_confirmation_body',
            'order_preparing_subject',
            'order_preparing_body',
            'order_delivered_subject',
            'order_delivered_body',
            'order_cancelled_subject',
            'order_cancelled_body', // NEW
        ];

        foreach ($textFields as $field) {
            if ($request->has($field)) {
                Setting::updateOrCreate(['key' => $field], ['value' => $request->input($field) ?? '']);
            }
        }

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'logo_path'], ['value' => $logoPath]);
        }

        if ($request->hasFile('hero_image')) {
            $heroPath = $request->file('hero_image')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'hero_image_path'], ['value' => $heroPath]);
        }

        return response()->json(['message' => 'Settings updated successfully.']);
    }

    public function getPublicSettings()
    {
        $settings = Setting::pluck('value', 'key')->all();

        $logoUrl = !empty($settings['logo_path']) ? asset('storage/' . $settings['logo_path']) : null;
        $heroUrl = !empty($settings['hero_image_path']) ? asset('storage/' . $settings['hero_image_path']) : null;

        return response()->json([
            'brand_name' => $settings['brand_name'] ?? 'DigitalStore',
            'logo_url' => $logoUrl,
            'hero_image_url' => $heroUrl,
            'hero_heading' => $settings['hero_heading'] ?? 'Discover & Download Premium Products',
            'hero_text' => $settings['hero_text'] ?? 'Explore our curated collection of digital software, e-books, and high-quality physical merchandise.',
            'tax_percentage' => (float) ($settings['tax_percentage'] ?? 5),
            'delivery_fee' => (float) ($settings['delivery_fee'] ?? 250),
            'footer_phone' => $settings['footer_phone'] ?? '+92 300 1234567',
            'footer_email' => $settings['footer_email'] ?? 'support@digitalstore.pk',
            'footer_address' => $settings['footer_address'] ?? 'Lahore, Punjab, Pakistan',
            'footer_whatsapp' => $settings['footer_whatsapp'] ?? '923001234567',
            'footer_instagram' => $settings['footer_instagram'] ?? 'https://instagram.com',
            'footer_facebook' => $settings['footer_facebook'] ?? 'https://facebook.com',
            'footer_about' => $settings['footer_about'] ?? 'Your trusted store for instant digital downloads and physical products.',
            'about_heading' => $settings['about_heading'] ?? 'About Our Store',
            'about_description' => $settings['about_description'] ?? 'Providing top-notch digital downloads and physical products with quality and fast delivery.',
            'about_f1_title' => $settings['about_f1_title'] ?? 'Instant Downloads',
            'about_f1_desc' => $settings['about_f1_desc'] ?? 'Access your digital purchases immediately after checkout with direct email delivery.',
            'about_f2_title' => $settings['about_f2_title'] ?? 'Trusted Shipping',
            'about_f2_desc' => $settings['about_f2_desc'] ?? 'Fast and safe nationwide delivery for all physical goods across Pakistan.',
            'about_f3_title' => $settings['about_f3_title'] ?? '24/7 Support',
            'about_f3_desc' => $settings['about_f3_desc'] ?? 'Reach out anytime via email or WhatsApp for quick resolution of your inquiries.',
        ]);
    }
}