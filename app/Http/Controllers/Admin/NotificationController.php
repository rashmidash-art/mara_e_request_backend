<?php

namespace App\Http\Controllers\Admin;

// namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function index(Request $request)
    // {
    //     $user = Auth::user();
    //     if (! $user) {
    //         return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
    //     }

    //     $notifications = Notification::where('user_id', $user->id)
    //         ->where('is_read', 0)
    //         ->orderBy('created_at', 'desc')
    //         ->limit(10)
    //         ->get();

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $notifications,
    //     ]);
    // }

    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $query = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        $total = $query->count();

        // If total <= 15, skip pagination overhead
        if ($total <= $perPage) {
            $data = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => 1,
                    'last_page' => 1,
                    'from' => $total > 0 ? 1 : null,
                    'to' => $total > 0 ? $total : null,
                ],
            ]);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $paginated->items(),   // ← always a flat array
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    // ---------------- MARK AS READ ----------------
    public function markAsRead(Request $request, $id)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $notification) {
            return response()->json(['status' => 'error', 'message' => 'Notification not found'], 404);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Notification marked as read']);
    }

    // ---------------- GET UNREAD COUNT ----------------
    public function unreadCount()
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => ['unread_count' => $count],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
