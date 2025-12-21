<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CreateCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * Get all categories with hierarchical structure
     *
     * @OA\Get(
     *     path="/api/categories",
     *     operationId="getCategories",
     *     tags={"Categories"},
     *     summary="Get all categories",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", enum={"income", "expense", "both"})),
     *     @OA\Parameter(name="parent_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="with_budget", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="with_spending", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="hierarchical", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="List of categories",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Category"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:income,expense,both'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
            'with_budget' => ['nullable', 'boolean'],
            'with_spending' => ['nullable', 'boolean'],
            'hierarchical' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $query = $user->categories();

        // Apply filters
        if ($request->filled('type') && $request->type !== 'both') {
            $query->where('type', $request->type);
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Load relationships
        $query->with('children');

        // Order by sort_order
        $query->orderBy('sort_order')->orderBy('name');

        $categories = $query->get();

        // Add spending data if requested
        if ($request->boolean('with_spending')) {
            $currentMonth = Carbon::now();
            $startOfMonth = $currentMonth->copy()->startOfMonth();
            $endOfMonth = $currentMonth->copy()->endOfMonth();

            $categories = $categories->map(function ($category) use ($user, $startOfMonth, $endOfMonth) {
                $spent = $user->transactions()
                    ->where('category_id', $category->id)
                    ->where('type', 'expense')
                    ->whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->sum('amount');

                $transactionCount = $user->transactions()
                    ->where('category_id', $category->id)
                    ->whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->count();

                $category->total_spent = $spent;
                $category->transaction_count = $transactionCount;

                // Calculate children spending
                if ($category->children->count() > 0) {
                    $childrenSpent = 0;
                    $childrenTransactionCount = 0;

                    foreach ($category->children as $child) {
                        $childSpent = $user->transactions()
                            ->where('category_id', $child->id)
                            ->where('type', 'expense')
                            ->whereBetween('date', [$startOfMonth, $endOfMonth])
                            ->sum('amount');

                        $childTransactionCount = $user->transactions()
                            ->where('category_id', $child->id)
                            ->whereBetween('date', [$startOfMonth, $endOfMonth])
                            ->count();

                        $child->total_spent = $childSpent;
                        $child->transaction_count = $childTransactionCount;
                        $childrenSpent += $childSpent;
                        $childrenTransactionCount += $childTransactionCount;
                    }

                    // Include children spending in parent total
                    $category->total_spent += $childrenSpent;
                    $category->transaction_count += $childrenTransactionCount;
                }

                return $category;
            });
        }

        // Build hierarchical structure if requested
        if ($request->boolean('hierarchical')) {
            $categories = $categories->whereNull('parent_id')->values();
        }

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * Get categories summary with statistics
     *
     * @OA\Get(
     *     path="/api/categories/analytics/summary",
     *     operationId="getCategoriesSummary",
     *     tags={"Categories"},
     *     summary="Get categories summary with statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Categories summary")
     * )
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentMonth = Carbon::now();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        // Get all categories
        $categories = $user->categories()->with('children')->get();

        // Calculate total budget (sum of all budget_amount from categories)
        $totalBudget = $categories->sum('budget_amount');

        // Calculate total spent this month
        $totalSpent = $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Count total transactions this month
        $totalTransactions = $user->transactions()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->count();

        // Get hierarchical categories with spending
        $hierarchicalCategories = $this->buildCategoryHierarchy($user, $categories, $startOfMonth, $endOfMonth);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_categories' => $categories->count(),
                    'total_budget' => $totalBudget,
                    'total_spent' => $totalSpent,
                    'total_transactions' => $totalTransactions,
                    'remaining' => $totalBudget - $totalSpent,
                    'percentage_used' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0,
                ],
                'categories' => $hierarchicalCategories,
            ],
        ]);
    }

    /**
     * Build hierarchical category structure with spending data
     */
    private function buildCategoryHierarchy($user, $categories, $startOfMonth, $endOfMonth): array
    {
        // Get root categories (no parent)
        $rootCategories = $categories->whereNull('parent_id');

        return $rootCategories->map(function ($category) use ($user, $categories, $startOfMonth, $endOfMonth) {
            return $this->mapCategoryWithSpending($user, $category, $categories, $startOfMonth, $endOfMonth);
        })->values()->toArray();
    }

    /**
     * Map category with spending and children
     */
    private function mapCategoryWithSpending($user, $category, $allCategories, $startOfMonth, $endOfMonth): array
    {
        // Calculate category spending
        $spent = $user->transactions()
            ->where('category_id', $category->id)
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $transactionCount = $user->transactions()
            ->where('category_id', $category->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->count();

        // Get children
        $children = $allCategories->where('parent_id', $category->id);
        $childrenData = [];
        $childrenSpent = 0;
        $childrenTransactionCount = 0;

        foreach ($children as $child) {
            $childData = $this->mapCategoryWithSpending($user, $child, $allCategories, $startOfMonth, $endOfMonth);
            $childrenData[] = $childData;
            $childrenSpent += $childData['total_spent'];
            $childrenTransactionCount += $childData['transaction_count'];
        }

        // Total spent includes children
        $totalSpent = $spent + $childrenSpent;
        $totalTransactionCount = $transactionCount + $childrenTransactionCount;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'type' => $category->type,
            'icon' => $category->icon,
            'color' => $category->color,
            'budget_amount' => $category->budget_amount ?? 0,
            'total_spent' => $totalSpent,
            'own_spent' => $spent,
            'percentage' => ($category->budget_amount ?? 0) > 0
                ? round(($totalSpent / $category->budget_amount) * 100, 1)
                : 0,
            'transaction_count' => $totalTransactionCount,
            'own_transaction_count' => $transactionCount,
            'remaining' => ($category->budget_amount ?? 0) - $totalSpent,
            'is_active' => $category->is_active,
            'parent_id' => $category->parent_id,
            'children' => $childrenData,
            'has_children' => count($childrenData) > 0,
        ];
    }

    /**
     * Store a newly created category
     */
    public function store(CreateCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Set sort_order
        if (!isset($data['sort_order'])) {
            $maxOrder = $request->user()->categories()
                ->where('parent_id', $data['parent_id'] ?? null)
                ->max('sort_order');
            $data['sort_order'] = ($maxOrder ?? 0) + 1;
        }

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => new CategoryResource($category->load('children', 'parent')),
        ], 201);
    }

    /**
     * Display the specified category
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category->load('children', 'parent')),
        ]);
    }

    /**
     * Update the specified category
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $category->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => new CategoryResource($category->fresh()->load('children', 'parent')),
        ]);
    }

    /**
     * Remove the specified category
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with subcategories. Delete subcategories first or move them to another parent.',
            ], 422);
        }

        // Check if category has transactions
        $transactionCount = $request->user()->transactions()
            ->where('category_id', $category->id)
            ->count();

        if ($transactionCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete category with {$transactionCount} associated transactions. Please reassign or delete transactions first.",
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * Get category transactions
     */
    public function transactions(Request $request, Category $category): JsonResponse
    {
        if ($category->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $request->user()->transactions()
            ->where('category_id', $category->id)
            ->with(['account']);

        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        $transactions = $query->orderBy('date', 'desc')
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
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

        $user = $request->user();
        $period = $request->input('period', 'month');
        $type = $request->input('type', 'expense');

        $analysis = $this->categoryService->getSpendingAnalysis(
            $user,
            $period,
            $type,
            $request->start_date,
            $request->end_date
        );

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * Get category trends
     */
    public function trends(Request $request): JsonResponse
    {
        $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'period' => ['nullable', 'string', 'in:week,month,quarter,year'],
            'interval' => ['nullable', 'string', 'in:day,week,month'],
        ]);

        $user = $request->user();
        $categoryIds = $request->input('category_ids', []);
        $period = $request->input('period', 'month');
        $interval = $request->input('interval', 'day');

        $trends = $this->categoryService->getCategoryTrends(
            $user,
            $categoryIds,
            $period,
            $interval
        );

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Get available icons and colors
     */
    public function getIconsAndColors(): JsonResponse
    {
        $icons = [
            ['name' => 'shopping_cart', 'label' => 'Shopping Cart', 'category' => 'shopping'],
            ['name' => 'home', 'label' => 'Home', 'category' => 'housing'],
            ['name' => 'directions_car', 'label' => 'Car', 'category' => 'transportation'],
            ['name' => 'restaurant', 'label' => 'Restaurant', 'category' => 'food'],
            ['name' => 'favorite', 'label' => 'Heart', 'category' => 'healthcare'],
            ['name' => 'work', 'label' => 'Briefcase', 'category' => 'business'],
            ['name' => 'flight', 'label' => 'Flight', 'category' => 'travel'],
            ['name' => 'local_grocery_store', 'label' => 'Groceries', 'category' => 'food'],
            ['name' => 'movie', 'label' => 'Movie', 'category' => 'entertainment'],
            ['name' => 'local_cafe', 'label' => 'Cafe', 'category' => 'food'],
            ['name' => 'pets', 'label' => 'Pets', 'category' => 'pets'],
            ['name' => 'smartphone', 'label' => 'Phone', 'category' => 'technology'],
            ['name' => 'local_gas_station', 'label' => 'Gas Station', 'category' => 'transportation'],
            ['name' => 'school', 'label' => 'Education', 'category' => 'education'],
            ['name' => 'music_note', 'label' => 'Music', 'category' => 'entertainment'],
            ['name' => 'fitness_center', 'label' => 'Fitness', 'category' => 'health'],
            ['name' => 'checkroom', 'label' => 'Clothing', 'category' => 'shopping'],
            ['name' => 'devices', 'label' => 'Electronics', 'category' => 'technology'],
            ['name' => 'bolt', 'label' => 'Utilities', 'category' => 'utilities'],
            ['name' => 'key', 'label' => 'Rent', 'category' => 'housing'],
            ['name' => 'directions_bus', 'label' => 'Public Transit', 'category' => 'transportation'],
            ['name' => 'local_hospital', 'label' => 'Hospital', 'category' => 'healthcare'],
            ['name' => 'attach_money', 'label' => 'Money', 'category' => 'income'],
            ['name' => 'account_balance', 'label' => 'Bank', 'category' => 'finance'],
        ];

        $colors = [
            ['value' => '#3B82F6', 'label' => 'Blue'],
            ['value' => '#EF4444', 'label' => 'Red'],
            ['value' => '#10B981', 'label' => 'Green'],
            ['value' => '#F97316', 'label' => 'Orange'],
            ['value' => '#8B5CF6', 'label' => 'Purple'],
            ['value' => '#EC4899', 'label' => 'Pink'],
            ['value' => '#06B6D4', 'label' => 'Cyan'],
            ['value' => '#84CC16', 'label' => 'Lime'],
            ['value' => '#F59E0B', 'label' => 'Amber'],
            ['value' => '#A855F7', 'label' => 'Violet'],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'icons' => $icons,
                'colors' => $colors,
            ],
        ]);
    }

    /**
     * Get default categories
     */
    public function getDefaults(): JsonResponse
    {
        $defaults = [
            // Expense categories
            ['name' => 'Food & Dining', 'type' => 'expense', 'icon' => 'restaurant', 'color' => '#F97316'],
            ['name' => 'Groceries', 'type' => 'expense', 'icon' => 'local_grocery_store', 'color' => '#10B981', 'parent' => 'Food & Dining'],
            ['name' => 'Restaurants', 'type' => 'expense', 'icon' => 'local_cafe', 'color' => '#EF4444', 'parent' => 'Food & Dining'],
            ['name' => 'Transportation', 'type' => 'expense', 'icon' => 'directions_car', 'color' => '#3B82F6'],
            ['name' => 'Gas & Fuel', 'type' => 'expense', 'icon' => 'local_gas_station', 'color' => '#10B981', 'parent' => 'Transportation'],
            ['name' => 'Public Transit', 'type' => 'expense', 'icon' => 'directions_bus', 'color' => '#8B5CF6', 'parent' => 'Transportation'],
            ['name' => 'Housing', 'type' => 'expense', 'icon' => 'home', 'color' => '#EF4444'],
            ['name' => 'Rent', 'type' => 'expense', 'icon' => 'key', 'color' => '#F97316', 'parent' => 'Housing'],
            ['name' => 'Utilities', 'type' => 'expense', 'icon' => 'bolt', 'color' => '#F59E0B', 'parent' => 'Housing'],
            ['name' => 'Entertainment', 'type' => 'expense', 'icon' => 'movie', 'color' => '#10B981'],
            ['name' => 'Healthcare', 'type' => 'expense', 'icon' => 'favorite', 'color' => '#EF4444'],
            ['name' => 'Shopping', 'type' => 'expense', 'icon' => 'shopping_cart', 'color' => '#8B5CF6'],
            ['name' => 'Clothing', 'type' => 'expense', 'icon' => 'checkroom', 'color' => '#EC4899', 'parent' => 'Shopping'],
            ['name' => 'Electronics', 'type' => 'expense', 'icon' => 'devices', 'color' => '#3B82F6', 'parent' => 'Shopping'],
            // Income categories
            ['name' => 'Salary', 'type' => 'income', 'icon' => 'attach_money', 'color' => '#10B981'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => 'work', 'color' => '#3B82F6'],
            ['name' => 'Investments', 'type' => 'income', 'icon' => 'account_balance', 'color' => '#8B5CF6'],
        ];

        return response()->json([
            'success' => true,
            'data' => $defaults,
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
                'message' => 'User already has categories. Delete existing categories first or use merge.',
            ], 422);
        }

        $defaults = $this->getDefaults()->original['data'];
        $createdCategories = [];
        $parentMap = [];

        // First pass: Create parent categories
        foreach ($defaults as $default) {
            if (!isset($default['parent'])) {
                $category = Category::create([
                    'user_id' => $user->id,
                    'name' => $default['name'],
                    'type' => $default['type'],
                    'icon' => $default['icon'],
                    'color' => $default['color'],
                ]);
                $parentMap[$default['name']] = $category->id;
                $createdCategories[] = $category;
            }
        }

        // Second pass: Create child categories
        foreach ($defaults as $default) {
            if (isset($default['parent'])) {
                $category = Category::create([
                    'user_id' => $user->id,
                    'name' => $default['name'],
                    'type' => $default['type'],
                    'icon' => $default['icon'],
                    'color' => $default['color'],
                    'parent_id' => $parentMap[$default['parent']] ?? null,
                ]);
                $createdCategories[] = $category;
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($createdCategories) . ' default categories created successfully',
            'data' => CategoryResource::collection(collect($createdCategories)),
        ], 201);
    }

    /**
     * Bulk update categories
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'integer', 'exists:categories,id'],
            'categories.*.name' => ['nullable', 'string', 'max:255'],
            'categories.*.icon' => ['nullable', 'string'],
            'categories.*.color' => ['nullable', 'string'],
            'categories.*.budget_amount' => ['nullable', 'numeric', 'min:0'],
            'categories.*.is_active' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $updatedCount = 0;

        foreach ($request->categories as $categoryData) {
            $category = Category::where('id', $categoryData['id'])
                ->where('user_id', $user->id)
                ->first();

            if ($category) {
                $category->update(array_filter($categoryData, function ($key) {
                    return $key !== 'id';
                }, ARRAY_FILTER_USE_KEY));
                $updatedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$updatedCount} categories updated successfully",
        ]);
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'integer', 'exists:categories,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $user = $request->user();

        foreach ($request->order as $item) {
            Category::where('id', $item['id'])
                ->where('user_id', $user->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categories reordered successfully',
        ]);
    }

    /**
     * Merge categories
     */
    public function merge(Request $request): JsonResponse
    {
        $request->validate([
            'source_id' => ['required', 'integer', 'exists:categories,id'],
            'target_id' => ['required', 'integer', 'exists:categories,id', 'different:source_id'],
        ]);

        $user = $request->user();

        $source = Category::where('id', $request->source_id)
            ->where('user_id', $user->id)
            ->first();

        $target = Category::where('id', $request->target_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$source || !$target) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        // Move all transactions from source to target
        $user->transactions()
            ->where('category_id', $source->id)
            ->update(['category_id' => $target->id]);

        // Move children categories
        Category::where('parent_id', $source->id)
            ->where('user_id', $user->id)
            ->update(['parent_id' => $target->id]);

        // Delete source category
        $source->delete();

        return response()->json([
            'success' => true,
            'message' => "Category '{$source->name}' merged into '{$target->name}' successfully",
        ]);
    }
}
