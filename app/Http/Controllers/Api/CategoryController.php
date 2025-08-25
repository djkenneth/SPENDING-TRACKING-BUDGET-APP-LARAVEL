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
     *
     * @OA\Get(
     *     path="/api/categories",
     *     operationId="getCategories",
     *     tags={"Categories"},
     *     summary="Get all user categories",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", enum={"income", "expense", "transfer"})),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="include_inactive", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(type="string", enum={"name", "sort_order", "created_at"})),
     *     @OA\Parameter(name="sort_direction", in="query", required=false, @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CategoryResource")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="by_type", type="object"),
     *                 @OA\Property(property="active_count", type="integer"),
     *                 @OA\Property(property="inactive_count", type="integer"),
     *                 @OA\Property(property="statistics", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Post(
     *     path="/api/categories",
     *     operationId="createCategory",
     *     tags={"Categories"},
     *     summary="Create a new category",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateCategoryRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/CategoryResource")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
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
     *
     * @OA\Get(
     *     path="/api/categories/{id}",
     *     operationId="getCategory",
     *     tags={"Categories"},
     *     summary="Get specific category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", ref="#/components/schemas/CategoryResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Put(
     *     path="/api/categories/{id}",
     *     operationId="updateCategory",
     *     tags={"Categories"},
     *     summary="Update category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateCategoryRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/CategoryResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
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
     *
     * @OA\Delete(
     *     path="/api/categories/{id}",
     *     operationId="deleteCategory",
     *     tags={"Categories"},
     *     summary="Delete category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot delete category"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Get(
     *     path="/api/categories/{id}/transactions",
     *     operationId="getCategoryTransactions",
     *     tags={"Categories"},
     *     summary="Get category transactions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="account_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", enum={"income", "expense", "transfer"})),
     *     @OA\Response(
     *         response=200,
     *         description="Category transactions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TransactionResource")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Get(
     *     path="/api/categories/analytics/spending-analysis",
     *     operationId="getCategorySpendingAnalysis",
     *     tags={"Categories"},
     *     summary="Get spending analysis by category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="string", enum={"week", "month", "quarter", "year"})),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", enum={"income", "expense"})),
     *     @OA\Response(
     *         response=200,
     *         description="Spending analysis data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Get(
     *     path="/api/categories/analytics/trends",
     *     operationId="getCategoryTrends",
     *     tags={"Categories"},
     *     summary="Get category trends",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="string", enum={"month", "quarter", "year"})),
     *     @OA\Parameter(name="months", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=24)),
     *     @OA\Parameter(name="category_ids[]", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="integer"))),
     *     @OA\Response(
     *         response=200,
     *         description="Category trends data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Put(
     *     path="/api/categories/bulk/update",
     *     operationId="bulkUpdateCategories",
     *     tags={"Categories"},
     *     summary="Bulk update categories",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="categories", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="color", type="string"),
     *                 @OA\Property(property="icon", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="sort_order", type="integer")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CategoryResource"))
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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

                $category->update(array_filter($categoryData, function ($key) {
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
     *
     * @OA\Put(
     *     path="/api/categories/bulk/reorder",
     *     operationId="reorderCategories",
     *     tags={"Categories"},
     *     summary="Reorder categories",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="categories", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="sort_order", type="integer")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories reordered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Get(
     *     path="/api/categories/meta/icons-and-colors",
     *     operationId="getCategoryIconsAndColors",
     *     tags={"Categories"},
     *     summary="Get available icons and colors",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Available icons and colors",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="icons", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="colors", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Post(
     *     path="/api/categories/merge",
     *     operationId="mergeCategories",
     *     tags={"Categories"},
     *     summary="Merge categories",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="source_category_id", type="integer"),
     *             @OA\Property(property="target_category_id", type="integer"),
     *             @OA\Property(property="delete_source", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories merged successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Get(
     *     path="/api/categories/meta/defaults",
     *     operationId="getDefaultCategories",
     *     tags={"Categories"},
     *     summary="Get default categories for new users",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Default categories list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     *
     * @OA\Post(
     *     path="/api/categories/meta/create-defaults",
     *     operationId="createDefaultCategories",
     *     tags={"Categories"},
     *     summary="Create default categories for user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=201,
     *         description="Default categories created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CategoryResource"))
     *         )
     *     ),
     *     @OA\Response(response=400, description="User already has categories"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
