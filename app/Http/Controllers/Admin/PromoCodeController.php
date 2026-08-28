<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function index()
    {
        return response()->json(['promos' => PromoCode::latest()->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:promo_codes,code',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'expires_at' => 'nullable|date',
            'status' => 'required|in:active,inactive',
        ]);

        $promo = PromoCode::create([
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'value' => $request->value,
            'expires_at' => $request->expires_at,
            'status' => $request->status,
        ]);

        return response()->json(['message' => 'Promo code created.', 'promo' => $promo], 201);
    }

    public function update(Request $request, $id)
    {
        $promo = PromoCode::findOrFail($id);
        $request->validate([
            'code' => 'required|string|unique:promo_codes,code,' . $id,
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'expires_at' => 'nullable|date',
            'status' => 'required|in:active,inactive',
        ]);

        $promo->update([
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'value' => $request->value,
            'expires_at' => $request->expires_at,
            'status' => $request->status,
        ]);

        return response()->json(['message' => 'Promo code updated.', 'promo' => $promo]);
    }

    public function destroy($id)
    {
        PromoCode::findOrFail($id)->delete();
        return response()->json(['message' => 'Promo code deleted.']);
    }
}