<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Users\IndexUserRequest;
use App\Http\Requests\Api\V1\Admin\Users\SyncUserCategoriesRequest;
use App\Http\Requests\Api\V1\Admin\Users\UpdateUserChildRequest;
use App\Http\Requests\Api\V1\Admin\Users\UpdateUserRequest;
use App\Http\Resources\AdminChildResource;
use App\Http\Resources\AdminUserResource;
use App\Models\Child;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends Controller
{
    /**
     * List app users, searchable by name or phone, filterable by active flag and category.
     */
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $search = $request->validated('search');

        $users = User::query()
            ->with('categories')
            ->withCount('children')
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                $term = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('full_name', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas(
                'categories',
                fn ($inner) => $inner->where('user_categories.id', $request->integer('category_id')),
            ))
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return AdminUserResource::collection($users);
    }

    /**
     * Show one user with children and categories.
     */
    public function show(User $user): AdminUserResource
    {
        return new AdminUserResource($this->loadDetails($user));
    }

    /**
     * Update a user's profile and active flag; deactivating revokes all of the user's tokens.
     */
    public function update(UpdateUserRequest $request, User $user, AuditLogger $audit): JsonResponse
    {
        $user->update($request->validated());

        if ($user->wasChanged()) {
            $audit->logChange('user.updated', $user);
        }

        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => __('admin_users.updated'),
            'data' => new AdminUserResource($this->loadDetails($user)),
        ]);
    }

    /**
     * List all of a user's children, including archived ones.
     */
    public function children(User $user): AnonymousResourceCollection
    {
        return AdminChildResource::collection($user->children()->orderBy('birth_date')->orderBy('id')->get());
    }

    /**
     * Update one child of the given user.
     *
     * @throws NotFoundHttpException when the child belongs to another user
     */
    public function updateChild(UpdateUserChildRequest $request, User $user, Child $child, AuditLogger $audit): JsonResponse
    {
        if ($child->user_id !== $user->id) {
            throw new NotFoundHttpException;
        }

        $child->update($request->validated());

        if ($child->wasChanged()) {
            $audit->logChange('child.updated', $child);
        }

        return response()->json([
            'message' => __('admin_users.child_updated'),
            'data' => new AdminChildResource($child->refresh()),
        ]);
    }

    /**
     * Replace a user's categories; existing memberships keep their assignment metadata.
     */
    public function syncCategories(SyncUserCategoriesRequest $request, User $user, AuditLogger $audit): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->user();

        $existingIds = $user->categories()->pluck('user_categories.id')->all();

        $changes = $user->categories()->sync(
            collect($request->validated('category_ids'))
                ->mapWithKeys(fn (int $id) => [$id => in_array($id, $existingIds, true)
                    ? []
                    : ['assigned_by_staff_id' => $staff->id, 'assigned_at' => now()]])
                ->all(),
        );

        if ($changes['attached'] !== [] || $changes['detached'] !== []) {
            $audit->log('user.categories_synced', $user, null, [
                'attached' => $changes['attached'],
                'detached' => $changes['detached'],
            ]);
        }

        return response()->json([
            'message' => __('admin_users.categories_synced'),
            'data' => new AdminUserResource($this->loadDetails($user)),
        ]);
    }

    private function loadDetails(User $user): User
    {
        return $user->load(['children' => fn ($query) => $query->orderBy('birth_date')->orderBy('id'), 'categories'])->loadCount('children');
    }
}
