<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->find($notification);

        if ($record === null) {
            return redirect()->route('dashboard');
        }

        $record->markAsRead();

        $pipelineId = $record->data['pipeline_id'] ?? null;
        if ($pipelineId !== null && ! Pipeline::whereKey($pipelineId)->exists()) {
            return redirect()->route('dashboard')->with('error', 'That item is no longer available.');
        }

        return redirect()->to($record->data['url'] ?? route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
