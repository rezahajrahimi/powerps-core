<?php

namespace App\Http\Controllers;

use App\Models\BotUser;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\ProductCategory;
use App\Models\AccountBallance;
use App\Models\PurchaseIntent;
use App\Models\PromoCodeUsage;
use App\Services\LicenseFeatureService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getDashboardStats()
    {
        try {
            $today = Carbon::today();

            $totalUsers = BotUser::count();
            $newUsersToday = BotUser::whereDate('created_at', $today)->count();

            $totalSalesToday = Product::whereDate('created_at', $today)->count();

            // Total Revenue from Transactions (Wallet Charges)
            $totalRevenue = Transaction::where('confirmed', true)->sum('amount');
            $totalRevenueToday = Transaction::where('confirmed', true)
                ->whereDate('created_at', $today)
                ->sum('amount');

            // Active Configs (isActive = 1)
            $activeConfigs = Product::where('isActive', 1)->count();

            // Total Sales (Sum of product prices)
            $totalSales = Product::join('product_categories', 'products.product_categories_id', '=', 'product_categories.id')
                ->sum('product_categories.price');

            // Monthly sales for chart (Revenue per month)
            $monthlySales = Product::join('product_categories', 'products.product_categories_id', '=', 'product_categories.id')
                ->select(
                    DB::raw('CAST(SUM(product_categories.price) AS UNSIGNED) as amount'),
                    DB::raw("DATE_FORMAT(products.created_at, '%Y-%m') as month")
                )
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->take(6)
                ->get();

            return response()->json([
                'total_users' => $totalUsers,
                'new_users_today' => $newUsersToday,
                'total_sales_today' => $totalSalesToday,
                'total_revenue_today' => $totalRevenueToday,
                'active_configs' => $activeConfigs,
                'total_revenue' => $totalRevenue,
                'total_sales' => $totalSales,
                'monthly_sales' => $monthlySales
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getFinancialReport(Request $request)
    {
        try {
            $query = Transaction::where('confirmed', true)->with(['payment_types', 'user']);

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }

            $transactions = $query->orderBy('id', 'desc')->paginate($request->count ?? 50);

            // Stats based on the filtered query
            $filteredTotal = $query->sum('amount');

            // General stats
            $stats = [
                'total_revenue' => Transaction::where('confirmed', true)->sum('amount'),
                'today_revenue' => Transaction::where('confirmed', true)->whereDate('created_at', Carbon::today())->sum('amount'),
                'this_month' => Transaction::where('confirmed', true)->whereMonth('created_at', Carbon::now()->month)->sum('amount'),
                'pending_amount' => Transaction::where('confirmed', false)->sum('amount'),
            ];

            return response()->json([
                'transactions' => $transactions,
                'total_amount' => $filteredTotal,
                'stats' => $stats
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getUserReport(Request $request)
    {
        try {
            $query = BotUser::query();

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%$search%")
                        ->orWhere('account_id', 'like', "%$search%")
                        ->orWhere('first_name', 'like', "%$search%");
                });
            }

            $users = $query->orderBy('id', 'desc')->paginate($request->count ?? 50);

            // Add balance to each user
            $users->getCollection()->transform(function ($user) {
                $balance = AccountBallance::where('account_id', $user->account_id)->first();
                $user->wallet_balance = $balance ? $balance->ballance : 0;
                return $user;
            });

            // Additional stats for the users tab
            $stats = [
                'total_users' => BotUser::count(),
                'new_today' => BotUser::whereDate('created_at', Carbon::today())->count(),
                'with_balance' => AccountBallance::where('ballance', '>', 0)->count(),
                'total_balance' => AccountBallance::sum('ballance'),
            ];

            return response()->json([
                'users' => $users,
                'stats' => $stats
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getProductReport(Request $request)
    {
        try {
            $query = Product::with(['user', 'product_category']);

            if ($request->has('category_id')) {
                $query->where('product_categories_id', $request->category_id);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }

            $products = $query->orderBy('id', 'desc')->paginate($request->count ?? 50);

            return response()->json($products, 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getRetentionStats()
    {
        try {
            $license = new LicenseFeatureService();
            if ($license->isBronzeOrBelow()) {
                return $license->silverRequiredResponse();
            }

            $since30 = Carbon::now()->subDays(30);
            $buyersLast30 = Product::where('created_at', '>=', $since30)->distinct()->count('account_id');
            $repeatBuyers = Product::where('created_at', '>=', $since30)
                ->select('account_id')
                ->groupBy('account_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();

            $renewalRate = $buyersLast30 > 0 ? round($repeatBuyers / $buyersLast30, 4) : 0;

            $payload = [
                'renewal_rate_30d' => $renewalRate,
                'repeat_buyers_30d' => $repeatBuyers,
                'buyers_30d' => $buyersLast30,
                'total_users' => BotUser::count(),
                'users_with_purchase' => Product::distinct('account_id')->count('account_id'),
                'license_tier' => $license->current(),
            ];

            if ($license->isGold()) {
                $abandonedToday = PurchaseIntent::whereNull('completed_at')
                    ->whereDate('created_at', Carbon::today())
                    ->count();
                $promoRevenueToday = PromoCodeUsage::whereDate('applied_at', Carbon::today())->sum('discount_amount');

                $payload['abandoned_intents_today'] = $abandonedToday;
                $payload['open_abandoned_intents'] = PurchaseIntent::whereNull('completed_at')->count();
                $payload['promo_discount_today'] = $promoRevenueToday;
            }

            return response()->json($payload, 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getRetentionChart()
    {
        try {
            $license = new LicenseFeatureService();
            if (! $license->isGold()) {
                return $license->goldRequiredResponse();
            }

            $monthlySales = Product::join('product_categories', 'products.product_categories_id', '=', 'product_categories.id')
                ->select(
                    DB::raw('CAST(COUNT(products.id) AS UNSIGNED) as sales_count'),
                    DB::raw('CAST(SUM(product_categories.price) AS UNSIGNED) as amount'),
                    DB::raw("DATE_FORMAT(products.created_at, '%Y-%m') as month")
                )
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->take(6)
                ->get();

            $categoryPerformance = Product::join('product_categories', 'products.product_categories_id', '=', 'product_categories.id')
                ->select(
                    'product_categories.id',
                    'product_categories.category_name',
                    DB::raw('COUNT(products.id) as sales_count')
                )
                ->groupBy('product_categories.id', 'product_categories.category_name')
                ->orderByDesc('sales_count')
                ->take(10)
                ->get();

            return response()->json([
                'monthly_sales' => $monthlySales,
                'top_categories' => $categoryPerformance,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
}
