<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\DeletesWithoutBreakingReferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UserCategories\AddUserCategoryMembersRequest;
use App\Http\Requests\Api\V1\Admin\UserCategories\IndexUserCategoryMemberRequest;
use App\Http\Requests\Api\V1\Admin\UserCategories\IndexUserCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UserCategories\StoreUserCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UserCategories\UpdateUserCategoryRequest;
use App\Http\Resources\UserCategoryResource;
use App\Http\Resources\UserResource;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserCategoryController extends Controller
{
    use DeletesWithoutBreakingReferences;

    /**
     * List user categories with their member counts, ordered by id.
     */
    public function index(IndexUserCategoryRequest $request): AnonymousResourceCollection
    {
        $categories = UserCategory::query()
            ->withCount('users')
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->get();

        return UserCategoryResource::collection($categories);
    }

    /**
     * Create a user category.
     */
    public function store(StoreUserCategoryRequest $request, AuditLogger $audit): JsonResponse
    {
        $category = UserCategory::create($request->validated())->refresh();

        $audit->log('user_category.created', $category, null, $category->toArray());

        return response()->json([
            'message' => __('admin_user_categories.created'),
            'data' => new UserCategoryResource($category->loadCount('users')),
        ], 201);
    }

    /**
     * Show one user category.
     */
    public function show(UserCategory $userCategory): UserCategoryResource
    {
        return new UserCategoryResource($userCategory->loadCount('users'));
    }

    /**
     * Update a user category.
     */
    public function update(UpdateUserCategoryRequest $request, UserCategory $userCategory, AuditLogger $audit): JsonResponse
    {
        $userCategory->update($request->validated());

        if ($userCategory->wasChanged()) {
            $audit->logChange('user_category.updated', $userCategory);
        }

        return response()->json([
            'message' => __('admin_user_categories.updated'),
            'data' => new UserCategoryResource($userCategory->loadCount('users')),
        ]);
    }

    /**
     * Delete a category that has no members and no discounts referencing it.
     *
     * @throws ConflictHttpException when members or discounts still reference the category
     */
    public function destroy(UserCategory $userCategory, AuditLogger $audit): JsonResponse
    {
        $before = $userCategory->toArray();

        $this->deleteOrConflict($userCategory, __('admin_user_categories.category_in_use'));

        $audit->log('user_category.deleted', $userCategory, $before, null);

        return response()->json(['message' => __('admin_user_categories.deleted')]);
    }

    /**
     * List the members of a category, newest assignments last, with the assignment time on each user.
     */
    public function members(IndexUserCategoryMemberRequest $request, UserCategory $userCategory): AnonymousResourceCollection
    {
        $members = $userCategory->users()
            ->orderBy('users.full_name')
            ->orderBy('users.id')
            ->paginate($request->integer('per_page', 15))->withQueryString();

        return UserResource::collection($members);
    }

    /**
     * Add users to a category; users who are already members are left untouched.
     */
    public function addMembers(AddUserCategoryMembersRequest $request, UserCategory $userCategory, AuditLogger $audit): JsonResponse
    {
        /** @var Staff $staff */
        $staff = $request->user();

        $changes = $userCategory->users()->syncWithoutDetaching(
            collect($request->validated('user_ids'))
                ->mapWithKeys(fn (int $id) => [$id => ['assigned_by_staff_id' => $staff->id, 'assigned_at' => now()]])
                ->all(),
        );

        $audit->log('user_category.members_added', $userCategory, null, ['user_ids' => $changes['attached']]);

        return response()->json([
            'message' => __('admin_user_categories.members_added'),
            'data' => ['attached' => count($changes['attached'])],
        ]);
    }

    /**
     * Remove one user from a category.
     *
     * @throws NotFoundHttpException when the user is not a member of the category
     */
    public function removeMember(Request $request, UserCategory $userCategory, User $user, AuditLogger $audit): JsonResponse
    {
        if ($userCategory->users()->detach($user->id) === 0) {
            throw new NotFoundHttpException;
        }

        $audit->log('user_category.member_removed', $userCategory, null, ['user_id' => $user->id]);

        return response()->json(['message' => __('admin_user_categories.member_removed')]);
    }
}
