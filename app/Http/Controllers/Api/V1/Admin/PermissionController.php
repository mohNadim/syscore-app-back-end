<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller
{
    /**
     * List every permission, ordered by code.
     */
    public function index(): AnonymousResourceCollection
    {
        return PermissionResource::collection(Permission::query()->orderBy('code')->get());
    }
}
