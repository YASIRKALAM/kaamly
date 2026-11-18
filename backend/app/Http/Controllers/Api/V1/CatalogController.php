<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Service\Models\ServiceCategory;
use App\Domains\Service\Models\Service;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    /**
     * List all active categories with their active subcategories
     */
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn($q) => $q->where('is_active', true)])
            ->orderBy('display_order')
            ->get();

        return $this->successResponse($categories);
    }

    /**
     * List all active services
     */
    public function services(): JsonResponse
    {
        $services = Service::where('is_active', true)
            ->with('category')
            ->get();

        return $this->successResponse($services);
    }

    /**
     * Get single service details
     */
    public function showService(string $slug): JsonResponse
    {
        $service = Service::where('slug', $slug)
            ->where('is_active', true)
            ->with('category')
            ->firstOrFail();

        return $this->successResponse($service);
    }
}
