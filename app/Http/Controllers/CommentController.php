<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Http\Resources\CommentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Comment::with(['user', 'replies.user']);

        // Filter by leave_request_id if provided
        if ($request->has('leave_request_id')) {
            $query->where('leave_request_id', $request->leave_request_id);
        }

        // Filter by parent_id to get only top-level comments or replies
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } else {
            // By default, only show top-level comments (no parent)
            $query->whereNull('parent_id');
        }

        $comments = $query->latest()->paginate(20);

        // return CommentResource::collection($comments);
        return response()->json([
            'success' => true,
            'message' => 'Comments retrieved successfully',
            'data' => CommentResource::collection($comments),
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show($commentId)
    {
        $comment = Comment::with(['user', 'replies.user'])->find($commentId);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found. It may have been deleted.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Comment retrieved successfully',
            'data' => (new CommentResource($comment))->toArray(request()),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $commentId)
    {
        $comment = Comment::find($commentId);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found. It may have been deleted.',
                'data' => null,
            ], 404);
        }

        try {
            $this->authorize('update', $comment);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this comment. You can only update your own comments.',
                'data' => null,
            ], 403);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $comment->update([
            'body' => $validated['body'],
            'edited_at' => now(),
        ]);

        $comment->load('user', 'replies.user');

        return response()->json([
            'success' => true,
            'message' => 'Comment updated successfully',
            'data' => (new CommentResource($comment))->toArray($request),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($commentId)
    {
        $comment = Comment::find($commentId);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found. It may have been already deleted.',
                'data' => null,
            ], 404);
        }

        try {
            $this->authorize('delete', $comment);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this comment. You can only delete your own comments.',
                'data' => null,
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully',
        ], 200);
    }
}
