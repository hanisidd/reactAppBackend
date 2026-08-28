<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'brand_name', 'hero_heading', 'hero_text', 'tax_percentage',
            'delivery_fee', 'footer_phone', 'footer_email', 'footer_address',
            'footer_whatsapp', 'footer_instagram', 'footer_facebook', 'footer_about'
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
            'brand_name' => !empty($settings['brand_name']) ? $settings['brand_name'] : 'DigitalStore',
            'logo_url' => $logoUrl,
            'hero_image_url' => $heroUrl,
            'hero_heading' => !empty($settings['hero_heading']) ? $settings['hero_heading'] : 'Discover & Download Premium Products',
            'hero_text' => !empty($settings['hero_text']) ? $settings['hero_text'] : 'Explore our curated collection of digital software, e-books, and high-quality physical merchandise.',
            'tax_percentage' => (float) ($settings['tax_percentage'] ?? 5),
            'delivery_fee' => (float) ($settings['delivery_fee'] ?? 250),
            'footer_phone' => $settings['footer_phone'] ?? '+92 300 1234567',
            'footer_email' => $settings['footer_email'] ?? 'support@digitalstore.pk',
            'footer_address' => $settings['footer_address'] ?? 'Lahore, Punjab, Pakistan',
            'footer_whatsapp' => $settings['footer_whatsapp'] ?? '923001234567',
            'footer_instagram' => $settings['footer_instagram'] ?? 'https://instagram.com',
            'footer_facebook' => $settings['footer_facebook'] ?? 'https://facebook.com',
            'footer_about' => $settings['footer_about'] ?? 'Your trusted store for instant digital downloads and physical products.',
        ]);
    }
}