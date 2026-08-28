<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->get();

        return response()->json([
            'users' => $users,
        ]);
    }
    public function toggleStatus($id)
{
    $user = User::findOrFail($id);

    // Toggle status between active and blocked
    $user->status = $user->status === 'active' ? 'blocked' : 'active';
    $user->save();

    return response()->json([
        'message' => "User status updated to {$user->status}.",
        'user' => $user,
    ]);
}
}
