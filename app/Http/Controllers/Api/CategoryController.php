<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CreateCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryTransactionResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Get all user categories
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:income,expense,transfer'],
            'is_active' => ['nullable', 'boolean'],
            'include_inactive' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:name,sort_order,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = $request->user()->categories();

        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'sort_order');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        $categories = $query->get();

        // Calculate category statistics
        $statistics = $this->categoryService->getCategoriesStatistics($request->user());

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
            'meta' => [
                'total' => $categories->count(),
                'by_type' => $categories->groupBy('type')->map->count(),
                'active_count' => $categories->where('is_active', true)->count(),
                'inactive_count' => $categories->where('is_active', false)->count(),
                'statistics' => $statistics,
            ]
        ]);
    }

    /**
     * Create a new category
     */
    public function store(CreateCategoryRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $category = $this->categoryService->createCategory($request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => new CategoryResource($category)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Category creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific category
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        // Ensure category belongs to authenticated user
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $categoryData = new CategoryResource($category);
        $categoryStats = $this->categoryService->getCategoryStatistics($category);

        return response()->json([
            'success' => true,
            'data' => array_merge($categoryData->toArray($request), [
                'statistics' => $categoryStats
            ])
        ]);
    }

    /**
     * Update category
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        // Ensure category belongs to authenticated user
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $updatedCategory = $this->categoryService->updateCategory($category, $request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => new CategoryResource($updatedCategory->fresh())
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Category update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete category
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        // Ensure category belongs to authenticated user
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        // Check if category can be deleted
        $canDelete = $this->categoryService->canDeleteCategory($category);
        if (!$canDelete['can_delete']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category',
                'errors' => $canDelete['reasons']
            ], 400);
        }

        try {
            DB::beginTransaction();

            $this->categoryService->deleteCategory($category);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Category deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category transactions
     */
    public function transactions(Request $request, Category $category): JsonResponse
    {
        // Ensure category belongs to authenticated user
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'type' => ['nullable', 'string', 'in:income,expense,transfer'],
        ]);

        $query = $category->transactions()->with(['account', 'transferAccount']);

        // Apply filters
        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $perPage = $request->input('per_page', 20);
        $transactions = $query->latest('date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CategoryTransactionResource::collection($transactions->items()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ]
        ]);
    }

    /**
     * Get spending analysis by category
     */
    public function spendingAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:week,month,quarter,year'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'type' => ['nullable', 'string', 'in:income,expense'],
        ]);

        $period = $request->input('period', 'month');
        $type = $request->input('type', 'expense');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $analysis = $this->categoryService->getSpendingAnalysis(
            $request->user(),
            $period,
            $type,
            $startDate,
            $endDate
        );

        return response()->json([
            'success' => true,
            'data' => $analysis
        ]);
    }

    /**
     * Get category trends
     */
    public function trends(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:month,quarter,year'],
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $period = $request->input('period', 'month');
        $months = $request->input('months', 6);
        $categoryIds = $request->input('category_ids');

        $trends = $this->categoryService->getCategoryTrends(
            $request->user(),
            $period,
            $months,
            $categoryIds
        );

        return response()->json([
            'success' => true,
            'data' => $trends
        ]);
    }

    /**
     * Bulk update categories
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.id' => ['required', 'integer', 'exists:categories,id'],
            'categories.*.name' => ['sometimes', 'string', 'max:255'],
            'categories.*.color' => ['sometimes', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'categories.*.icon' => ['sometimes', 'string', 'max:50'],
            'categories.*.is_active' => ['sometimes', 'boolean'],
            'categories.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $updatedCategories = [];

        try {
            DB::beginTransaction();

            foreach ($request->categories as $categoryData) {
                $category = $user->categories()->find($categoryData['id']);

                if (!$category) {
                    continue;
                }

                $category->update(array_filter($categoryData, function($key) {
                    return $key !== 'id';
                }, ARRAY_FILTER_USE_KEY));

                $updatedCategories[] = new CategoryResource($category->fresh());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Categories updated successfully',
                'data' => $updatedCategories
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.id' => ['required', 'integer', 'exists:categories,id'],
            'categories.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $user = $request->user();

        try {
            DB::beginTransaction();

            foreach ($request->categories as $categoryData) {
                $category = $user->categories()->find($categoryData['id']);

                if ($category) {
                    $category->update(['sort_order' => $categoryData['sort_order']]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Categories reordered successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Reorder failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category icons and colors
     */
    public function getIconsAndColors(): JsonResponse
    {
        $iconsAndColors = $this->categoryService->getAvailableIconsAndColors();

        return response()->json([
            'success' => true,
            'data' => $iconsAndColors
        ]);
    }

    /**
     * Merge categories
     */
    public function merge(Request $request): JsonResponse
    {
        $request->validate([
            'source_category_id' => ['required', 'integer', 'exists:categories,id'],
            'target_category_id' => ['required', 'integer', 'exists:categories,id', 'different:source_category_id'],
            'delete_source' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $sourceCategory = $user->categories()->find($request->source_category_id);
        $targetCategory = $user->categories()->find($request->target_category_id);

        if (!$sourceCategory || !$targetCategory) {
            return response()->json([
                'success' => false,
                'message' => 'One or both categories not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $result = $this->categoryService->mergeCategories(
                $sourceCategory,
                $targetCategory,
                $request->boolean('delete_source', false)
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Categories merged successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Merge failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default categories for new users
     */
    public function getDefaults(): JsonResponse
    {
        $defaults = $this->categoryService->getDefaultCategories();

        return response()->json([
            'success' => true,
            'data' => $defaults
        ]);
    }

    /**
     * Create default categories for user
     */
    public function createDefaults(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user already has categories
        if ($user->categories()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'User already has categories'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $categories = $this->categoryService->createDefaultCategories($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Default categories created successfully',
                'data' => CategoryResource::collection($categories)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create default categories: ' . $e->getMessage()
            ], 500);
        }
    }
}
