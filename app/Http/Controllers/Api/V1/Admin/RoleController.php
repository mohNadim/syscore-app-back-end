<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PermissionCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Roles\StoreRoleRequest;
use App\Http\Requests\Api\V1\Admin\Roles\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RoleController extends Controller
{
    /**
     * List all roles with their permissions and staff counts.
     */
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()->with('permissions')->withCount('staff')->orderBy('id')->get();

        return RoleResource::collection($roles);
    }

    /**
     * Create a custom role and sync its permissions.
     */
    public function store(StoreRoleRequest $request, AuditLogger $audit): JsonResponse
    {
        $role = DB::transaction(function () use ($request): Role {
            $role = Role::create([
                'name_ar' => $request->validated('name_ar'),
                'name_en' => $request->validated('name_en'),
                'is_system' => false,
            ]);
            $role->permissions()->sync($request->validated('permission_ids'));

            return $role;
        });

        $role->load('permissions')->loadCount('staff');

        $audit->log('role.created', $role, null, [...$role->toArray(), 'permissions' => $this->permissionCodes($role)]);

        return response()->json([
            'message' => __('admin_roles.created'),
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * Show one role with its permissions and staff count.
     */
    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions')->loadCount('staff'));
    }

    /**
     * Update a role and optionally replace its permissions.
     *
     * @throws ConflictHttpException when the change would remove the acting staff's own role management permission
     */
    public function update(UpdateRoleRequest $request, Role $role, AuditLogger $audit): JsonResponse
    {
        $role->load('permissions');
        $permissionIds = $request->validated('permission_ids');

        if ($permissionIds !== null && $request->user()->role_id === $role->id) {
            $keepsRolesManage = Permission::query()
                ->whereIn('id', $permissionIds)
                ->where('code', PermissionCode::RolesManage->value)
                ->exists();

            if (! $keepsRolesManage) {
                throw new ConflictHttpException(__('admin_roles.cannot_remove_own_roles_permission'));
            }
        }

        $codesBefore = $this->permissionCodes($role);

        DB::transaction(function () use ($request, $role, $permissionIds): void {
            $role->update($request->safe()->only(['name_ar', 'name_en']));

            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }
        });

        if ($role->wasChanged()) {
            $audit->logChange('role.updated', $role);
        }

        $role->load('permissions')->loadCount('staff');
        $codesAfter = $this->permissionCodes($role);

        if ($codesBefore !== $codesAfter) {
            $audit->log('role.permissions_updated', $role, ['permissions' => $codesBefore], ['permissions' => $codesAfter]);
        }

        return response()->json([
            'message' => __('admin_roles.updated'),
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Delete a custom role that no staff member uses.
     *
     * @throws ConflictHttpException when the role is a system role or is assigned to staff
     */
    public function destroy(Role $role, AuditLogger $audit): JsonResponse
    {
        if ($role->is_system) {
            throw new ConflictHttpException(__('admin_roles.system_role'));
        }

        if ($role->staff()->exists()) {
            throw new ConflictHttpException(__('admin_roles.in_use'));
        }

        $role->load('permissions');
        $before = [...$role->toArray(), 'permissions' => $this->permissionCodes($role)];

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->delete();
        });

        $audit->log('role.deleted', $role, $before, null);

        return response()->json(['message' => __('admin_roles.deleted')]);
    }

    /**
     * Get the sorted permission codes of a role whose permissions are loaded.
     *
     * @return list<string>
     */
    private function permissionCodes(Role $role): array
    {
        return $role->permissions->pluck('code')->sort()->values()->all();
    }
}
