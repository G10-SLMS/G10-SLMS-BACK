<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\Attachment;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Requests\UpdateLeaveRequest;
use App\Http\Resources\LeaveRequestApprovalResource;
use App\Services\LeaveService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaveRequestController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
        protected LeaveService $leaveService,
    ) {}

    private function formatLeaveRequest(LeaveRequest $leaveRequest): array
    {
        $data = $leaveRequest->toArray();

        if ($leaveRequest->relationLoaded('approvalHistory')) {
            $data['approval_history'] = LeaveRequestApprovalResource::collection(
                $leaveRequest->approvalHistory
            )->toArray(request());
        }

        return $data;
    }

    public function index(Request $request)
    {
        $leaveRequests = LeaveRequest::withFullDetails()
            ->forListing($request)
            ->paginate($this->perPage($request));

        return $this->paginated(
            $leaveRequests,
            array_map(fn (LeaveRequest $item) => $this->formatLeaveRequest($item), $leaveRequests->items()),
            'Leave requests retrieved successfully.',
        );
    }

    public function stats(Request $request): JsonResponse
    {
        $counts = LeaveRequest::query()
            ->visibleTo($request->user())
            ->selectRaw("
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
            ")
            ->first();

        return $this->success([
            'pending' => (int) $counts->pending,
            'under_review' => (int) $counts->under_review,
            'approved' => (int) $counts->approved,
            'rejected' => (int) $counts->rejected,
            'cancelled' => (int) $counts->cancelled,
        ]);
    }

    public function store(StoreLeaveRequest $request)
    {
        $data = $this->leaveService->normalizeDuration(
            $request->safe()->except('supporting_document'),
        );

        $leave = LeaveRequest::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        $this->storeAttachments($request, $leave);

        $this->notifications->notifyLeaveSubmitted($leave);
        $this->notifications->emailLeaveSubmitted($leave);

        $leave = $leave->fresh(['leaveType', 'attachments']);

        return $this->success($leave->toArray(), 'Leave request created successfully.', 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $leaveRequest = LeaveRequest::withFullDetails()->with('comments')->find($id);

        if (!$leaveRequest) {
            return $this->error('Leave request not found.', 404);
        }

        if ($user->role === 'student' && $leaveRequest->user_id !== $user->id) {
            return $this->error('You are not authorized to view this leave request.', 403);
        }

        return $this->success($this->formatLeaveRequest($leaveRequest), 'Leave request retrieved successfully.');
    }

    public function update(UpdateLeaveRequest $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();

        $isOwner = $user->id === $leaveRequest->user_id;
        $isEducatorOrAdmin = in_array($user->role, ['educator', 'admin']);

        if (!$isOwner && !$isEducatorOrAdmin) {
            return $this->error('You are not authorized to perform this action.', 403);
        }

        if ($isEducatorOrAdmin && $request->has('status')) {
            return $this->handleReview($request, $leaveRequest, $user);
        }

        if ($leaveRequest->status !== 'pending') {
            return $this->error("Cannot edit a request that has already been reviewed ({$leaveRequest->status}).");
        }

        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] === 'cancelled') {
            return $this->cancel($leaveRequest);
        }

        $this->replaceAttachmentIfNeeded($request, $leaveRequest, $user);

        unset($validated['supporting_document'], $validated['remove_attachment']);

        $mergedForDuration = array_merge([
            'duration_type' => $leaveRequest->duration_type,
            'start_date' => $leaveRequest->start_date?->toDateString(),
            'start_time' => $leaveRequest->start_time,
            'end_time' => $leaveRequest->end_time,
        ], $validated);

        $validated = array_merge($validated, $this->leaveService->normalizeDuration($mergedForDuration));

        $leaveRequest->update($validated);

        return $this->success(
            $leaveRequest->fresh(['leaveType', 'user.avatar', 'reviewer', 'attachments']),
            'Leave request updated successfully.',
        );
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->user_id !== $request->user()->id) {
            return $this->error('You are not authorized to delete this leave request.', 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return $this->error("Cannot delete a request that is already {$leaveRequest->status}.");
        }

        $deletedId = $leaveRequest->id;
        $this->notifications->notifyLeaveCancelled($leaveRequest);
        $leaveRequest->delete();

        return $this->success(['id' => $deletedId], 'Leave request deleted successfully.');
    }

    public function downloadAttachment(Request $request, Attachment $attachment)
    {
        $user = $request->user();
        $leaveRequest = $attachment->leaveRequest;

        if (!$leaveRequest) {
            return $this->error('Attachment not found.', 404);
        }

        if ($user->role === 'student' && $leaveRequest->user_id !== $user->id) {
            return $this->error('You are not authorized to download this attachment.', 403);
        }

        $filePath = storage_path('app/public/' . $attachment->path);

        if (!file_exists($filePath)) {
            return $this->error('File not found on server.', 404);
        }

        return response()->download($filePath, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $attachment->original_name . '"',
        ]);
    }

    private function handleReview(Request $request, LeaveRequest $leaveRequest, $user)
    {
        $validated = $request->validated();
        $note = $validated['review_note'] ?? null;

        if ($validated['status'] === 'under_review') {
            return $this->markUnderReview($leaveRequest, $user, $note);
        }

        return $this->finalizeDecision($leaveRequest, $user, $validated['status'], $note);
    }

    private function markUnderReview(LeaveRequest $leaveRequest, $user, ?string $note)
    {
        if ($leaveRequest->status !== 'pending') {
            return $this->error('Only pending leave requests can be marked as under review.');
        }

        $leaveRequest->update([
            'status' => 'under_review',
            'reviewed_by' => $user->id,
            'review_note' => $note,
        ]);

        LeaveRequestApproval::record($leaveRequest, $user, 'under_review', $note);

        $this->notifications->notifyLeaveUnderReview($leaveRequest, $user);
        $this->notifications->emailLeaveUnderReview($leaveRequest);

        return $this->success(
            $this->formatLeaveRequest($leaveRequest->load(['leaveType', 'user.avatar', 'reviewer', 'approvalHistory.approver'])),
            'Leave request marked as under review.',
        );
    }

    private function finalizeDecision(LeaveRequest $leaveRequest, $user, string $status, ?string $note)
    {
        if (!$leaveRequest->isAwaitingDecision()) {
            return $this->error('This request has already been reviewed.');
        }

        $leaveRequest->update([
            'status' => $status,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        LeaveRequestApproval::record($leaveRequest, $user, $status, $note);

        $approved = $status === 'approved';

        if ($approved) {
            $this->notifications->notifyLeaveApproved($leaveRequest, $user);
            $this->notifications->emailLeaveApproved($leaveRequest);
        } else {
            $this->notifications->notifyLeaveRejected($leaveRequest, $user);
            $this->notifications->emailLeaveRejected($leaveRequest);
        }

        $message = $approved ? 'Leave request approved successfully.' : 'Leave request rejected successfully.';

        return $this->success(
            $this->formatLeaveRequest($leaveRequest->load(['leaveType', 'user.avatar', 'reviewer', 'approvalHistory.approver'])),
            $message,
        );
    }

    private function cancel(LeaveRequest $leaveRequest)
    {
        $leaveRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->notifications->notifyLeaveCancelled($leaveRequest);

        return $this->success(
            $leaveRequest->load(['leaveType', 'user.avatar', 'reviewer']),
            'Leave request cancelled successfully.',
        );
    }

    private function storeAttachments(Request $request, LeaveRequest $leave): void
    {
        if (!$request->hasFile('supporting_document')) {
            return;
        }

        $files = $request->file('supporting_document');
        $files = is_array($files) ? $files : [$files];

        foreach ($files as $file) {
            $this->createAttachment($leave, $file, $request->user()->id);
        }
    }

    private function replaceAttachmentIfNeeded(Request $request, LeaveRequest $leaveRequest, $user): void
    {
        $removing = $request->boolean('remove_attachment') && !$request->hasFile('supporting_document');
        $replacing = $request->hasFile('supporting_document');

        if (!$removing && !$replacing) {
            return;
        }

        foreach ($leaveRequest->attachments as $existing) {
            Storage::disk('public')->delete($existing->path);
            $existing->delete();
        }

        if ($replacing) {
            $this->createAttachment($leaveRequest, $request->file('supporting_document'), $user->id);
        }
    }

    private function createAttachment(LeaveRequest $leave, $file, int $uploadedBy): Attachment
    {
        $path = $file->store('attachments/leave-requests', 'public');

        return Attachment::create([
            'leave_request_id' => $leave->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
            'is_verified' => false,
        ]);
    }
}
