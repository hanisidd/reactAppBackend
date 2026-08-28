<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::select('id', 'name', 'email')
            ->latest()
            ->get();

        return response()->json([
            'admins' => $admins,
        ]);
    }
    public function store(StoreAdminRequest $request)
    {
        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Admin created successfully.',
            'admin' => $admin->only([
                'id',
                'name',
                'email',
            ]),
        ], 201);
    }

    public function update(UpdateAdminRequest $request, $id)
    {
        $admin = Admin::findOrFail($id);

        $admin->name = $request->name;
        $admin->email = $request->email;

        if ($request->filled('password')) {
            $admin->password = Hash::make($request->password);
        }

        $admin->save();

        return response()->json([
            'message' => 'Admin updated successfully.',
            'admin' => $admin->only([
                'id',
                'name',
                'email',
            ]),
        ]);
    }
    public function destroy($id)
{
    $admin = Admin::findOrFail($id);

    if ($admin->id === auth()->id()) {
        return response()->json([
            'message' => 'You cannot delete your own account.',
        ], 403);
    }

    $admin->delete();

    return response()->json([
        'message' => 'Admin deleted successfully.',
    ]);
}
}