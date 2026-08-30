<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index()
    {
        $messages = ContactMessage::latest()->get();
        return response()->json(['messages' => $messages]);
    }

    public function toggleRead($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->status = $msg->status === 'unread' ? 'read' : 'unread';
        $msg->save();

        return response()->json([
            'message' => "Message status updated to {$msg->status}.",
            'contact_message' => $msg,
        ]);
    }

    public function destroy($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->delete();

        return response()->json(['message' => 'Message deleted successfully.']);
    }
}