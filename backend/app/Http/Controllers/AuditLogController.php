<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'per_page' => 'sometimes|integer|min:5|max:100',
            'action' => 'sometimes|string|max:100',
        ]);

        $q = AuditLog::query()->with('user:id,name,email')->orderByDesc('id');

        if ($request->filled('action')) {
            $q->where('action', $request->action);
        }

        $perPage = (int) ($request->input('per_page', 30));
        $page = $q->paginate($perPage);

        return response()->json([
            'success' => true,
            'logs' => $page->items(),
            'pagination' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
