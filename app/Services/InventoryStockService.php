<?php

namespace App\Services;

use App\Models\Pannel;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class InventoryStockService
{
    public function getSummary(int $panelId): array
    {
        $this->assertInventoryPanel($panelId);

        $base = $this->baseQuery($panelId);

        return [
            'active' => (clone $base)->where('isActive', true)->count(),
            'sold' => (clone $base)->where('isActive', false)->whereNotNull('account_id')->count(),
            'total' => (clone $base)->count(),
        ];
    }

    public function listStock(
        int $panelId,
        ?string $status = 'all',
        ?string $sort = 'created_at_desc',
        ?string $search = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $this->assertInventoryPanel($panelId);

        $query = $this->baseQuery($panelId)
            ->with([
                'product_category:id,category_name,price,price_in_dollar,pannel_id',
                'user:account_id,username,first_name,last_name',
            ]);

        $this->applyStatusFilter($query, $status);
        $this->applySearch($query, $search);
        $this->applySort($query, $sort);

        return $query->paginate($perPage);
    }

    public function updateStockItem(
        int $productId,
        int $panelId,
        string $configs,
        string $subscriptionLink,
        string $panelLink
    ): Product {
        $product = $this->findInventoryProduct($productId, $panelId);

        if ($product->remark === 'pending') {
            throw new RuntimeException('این رکورد قابل ویرایش نیست.');
        }

        $configs = trim($configs);
        $subscriptionLink = trim($subscriptionLink);
        $panelLink = trim($panelLink);

        if ($configs === '') {
            throw new RuntimeException('کانفیگ نمی‌تواند خالی باشد.');
        }

        if ($subscriptionLink === '') {
            $subscriptionLink = $configs;
        }
        if ($panelLink === '') {
            $panelLink = $configs;
        }

        $isSold = ! $product->isActive && $product->account_id !== null;

        if ($isSold && $configs !== trim((string) $product->configs)) {
            throw new RuntimeException('کانفیگ فروخته‌شده قابل تغییر نیست.');
        }

        if (! $isSold && $this->configExistsOnPanel($panelId, $configs, $productId)) {
            throw new RuntimeException('این کانفیگ قبلاً در موجودی ثبت شده است.');
        }

        $product->configs = $configs;
        $product->subscription_link = $subscriptionLink;
        $product->panel_link = $panelLink;
        $product->save();

        return $product->load([
            'product_category:id,category_name,price,price_in_dollar,pannel_id',
            'user:account_id,username,first_name,last_name',
        ]);
    }

    public function deleteStockItem(int $productId, int $panelId): void
    {
        $product = $this->findInventoryProduct($productId, $panelId);

        if ($product->remark === 'pending') {
            throw new RuntimeException('این رکورد قابل حذف نیست.');
        }

        $product->delete();
    }

    public function findInventoryProduct(int $productId, int $panelId): Product
    {
        $this->assertInventoryPanel($panelId);

        $product = $this->baseQuery($panelId)
            ->where('id', $productId)
            ->first();

        if ($product === null) {
            throw new RuntimeException('کانفیگ مورد نظر یافت نشد.');
        }

        return $product;
    }

    private function configExistsOnPanel(int $panelId, string $config, ?int $exceptProductId = null): bool
    {
        return $this->baseQuery($panelId)
            ->where('configs', $config)
            ->when($exceptProductId !== null, fn (Builder $query) => $query->where('id', '!=', $exceptProductId))
            ->exists();
    }

    private function assertInventoryPanel(int $panelId): void
    {
        $panel = Pannel::find($panelId);
        if (! $panel || ! $panel->isInventoryPanel()) {
            throw new RuntimeException('پنل انتخاب‌شده از نوع موجودی (custome) نیست.');
        }
    }

    private function baseQuery(int $panelId): Builder
    {
        return Product::query()
            ->whereHas('product_category', function (Builder $query) use ($panelId) {
                $query->where('pannel_id', $panelId);
            })
            ->where(function (Builder $query) {
                $query->whereNull('remark')
                    ->orWhere('remark', '!=', 'pending');
            });
    }

    private function withCategoryJoin(Builder $query): Builder
    {
        if (! $this->queryHasJoin($query, 'product_categories')) {
            $query->join(
                'product_categories',
                'products.product_categories_id',
                '=',
                'product_categories.id'
            );
        }

        return $query->select('products.*');
    }

    private function queryHasJoin(Builder $query, string $table): bool
    {
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if ($join->table === $table) {
                return true;
            }
        }

        return false;
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        match ($status) {
            'active' => $query->where('isActive', true),
            'sold' => $query->where('isActive', false)->whereNotNull('account_id'),
            default => null,
        };
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $inner) use ($search) {
            $inner->where('configs', 'like', "%{$search}%")
                ->orWhere('subscription_link', 'like', "%{$search}%")
                ->orWhere('remark', 'like', "%{$search}%")
                ->orWhereHas('product_category', function (Builder $categoryQuery) use ($search) {
                    $categoryQuery->where('category_name', 'like', "%{$search}%");
                });
        });
    }

    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'created_at_asc' => $query->orderBy('created_at'),
            'updated_at_desc' => $query->orderByDesc('updated_at'),
            'updated_at_asc' => $query->orderBy('updated_at'),
            'category_asc' => $this->withCategoryJoin($query)->orderBy('product_categories.category_name'),
            'category_desc' => $this->withCategoryJoin($query)->orderByDesc('product_categories.category_name'),
            'price_asc' => $this->withCategoryJoin($query)->orderBy('product_categories.price'),
            'price_desc' => $this->withCategoryJoin($query)->orderByDesc('product_categories.price'),
            'status' => $query->orderByDesc('isActive')->orderByDesc('id'),
            default => $query->orderByDesc('created_at'),
        };
    }
}
