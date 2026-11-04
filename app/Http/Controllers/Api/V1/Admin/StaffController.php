<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Staff\IndexStaffRequest;
use App\Http\Requests\Api\V1\Admin\Staff\StoreStaffRequest;
use App\Http\Requests\Api\V1\Admin\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StaffController extends Controller
{
    /**
     * List staff members with optional search, role and active filters.
     */
    public function index(IndexStaffRequest $request): AnonymousResourceCollection
    {
        $search = $request->validated('search');

        $staff = Staff::query()
            ->with('role')
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                $term = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('full_name', 'like', $term)->orWhere('username', 'like', $term));
            })
            ->when($request->validated('role_id') !== null, fn ($query) => $query->where('role_id', $request->validated('role_id')))
            ->when($request->validated('is_active') !== null, fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString();

        return StaffResource::collection($staff);
    }

    /**
     * Create a staff member.
     */
    public function store(StoreStaffRequest $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->safe()->except(['password', 'password_confirmation']);

        $staff = Staff::create([
            ...$data,
            'password_hash' => $request->validated('password'),
            'is_active' => $request->validated('is_active') ?? true,
        ]);

        $staff->refresh()->load('role');

        $audit->log('staff.created', $staff, null, $staff->toArray());

        return response()->json([
            'message' => __('admin_staff.created'),
            'data' => new StaffResource($staff),
        ], 201);
    }

    /**
     * Show one staff member with their role and permissions.
     */
    public function show(Staff $staff): StaffResource
    {
        return new StaffResource($staff->load('role.permissions'));
    }

    /**
     * Update a staff member; deactivation and password changes revoke their API tokens.
     *
     * @throws ConflictHttpException when the acting staff tries to deactivate themselves or change their own role
     */
    public function update(UpdateStaffRequest $request, Staff $staff, AuditLogger $audit): JsonResponse
    {
        $isSelf = $staff->is($request->user());

        if ($isSelf && ($request->validated('is_active') === false
            || ($request->has('role_id') && (int) $request->validated('role_id') !== $staff->role_id))) {
            throw new ConflictHttpException(__('admin_staff.cannot_modify_self'));
        }

        $data = $request->safe()->except(['password', 'password_confirmation']);

        if ($request->filled('password')) {
            $data['password_hash'] = $request->validated('password');
        }

        $staff->update($data);

        if ($staff->wasChanged()) {
            $audit->logChange('staff.updated', $staff);
        }

        if ($staff->wasChanged('password_hash') || ($staff->wasChanged('is_active') && ! $staff->is_active)) {
            $this->revokeTokens($request, $staff);
        }

        return response()->json([
            'message' => __('admin_staff.updated'),
            'data' => new StaffResource($staff->load('role')),
        ]);
    }

    /**
     * Deactivate a staff member and revoke their tokens; staff are never hard-deleted.
     *
     * @throws ConflictHttpException when the acting staff targets themselves
     */
    public function destroy(Request $request, Staff $staff, AuditLogger $audit): JsonResponse
    {
        if ($staff->is($request->user())) {
            throw new ConflictHttpException(__('admin_staff.cannot_deactivate_self'));
        }

        $staff->update(['is_active' => false]);
        $staff->tokens()->delete();

        $audit->log('staff.deactivated', $staff, ['is_active' => true], ['is_active' => false]);

        return response()->json(['message' => __('admin_staff.deactivated')]);
    }

    /**
     * Delete the staff member's tokens, keeping the current one when the actor edits themselves.
     */
    private function revokeTokens(Request $request, Staff $staff): void
    {
        $tokens = $staff->tokens();

        if ($staff->is($request->user())) {
            $tokens->where('id', '!=', $request->user()->currentAccessToken()->id);
        }

        $tokens->delete();
    }
}
