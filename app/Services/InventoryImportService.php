<?php

namespace App\Services;

use App\Models\Pannel;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class InventoryImportService
{
    public function __construct(
        private readonly SimpleSpreadsheetReader $reader = new SimpleSpreadsheetReader(),
    ) {
    }

    /**
     * @return array{
     *     categories_created:int,
     *     categories_updated:int,
     *     configs_imported:int,
     *     duplicate_configs:int,
     *     skipped_rows:int,
     *     errors:array<int, string>
     * }
     */
    public function import(UploadedFile $file, int $panelId): array
    {
        $panel = Pannel::find($panelId);
        if (! $panel || ! $panel->isInventoryPanel()) {
            throw new RuntimeException('پنل انتخاب‌شده از نوع موجودی (custome) نیست.');
        }

        $tempPath = $file->getRealPath();
        if ($tempPath === false) {
            throw new RuntimeException('فایل آپلود شده قابل خواندن نیست.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        $rows = $this->reader->read($tempPath, $extension !== '' ? $extension : null);
        if ($rows === []) {
            throw new RuntimeException('فایل خالی است.');
        }

        $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $rows[0]);
        $categoryIndex = $this->findColumnIndex($header, ['category_name', 'category', 'دسته_بندی', 'دسته بندی', 'دسته']);
        $priceIndex = $this->findColumnIndex($header, [
            'price',
            'قیمت',
            'قیمت_تومان',
            'قیمت__تومان',
            'قیمت(تومان)',
        ]);
        $configIndex = $this->findColumnIndex($header, ['config', 'configs', 'کانفیگ', 'کانفیگها', 'کانفیگ ها']);
        $priceInDollarIndex = $this->findColumnIndex($header, ['price_in_dollar', 'dollar_price', 'قیمت_دلار', 'قیمت دلار']);
        $subscriptionIndex = $this->findColumnIndex($header, ['subscription_link', 'subscription', 'لینک_سابسکریپشن', 'لینک سابسکریپشن']);
        $panelLinkIndex = $this->findColumnIndex($header, ['panel_link', 'panel', 'لینک_پنل', 'لینک پنل']);

        if ($categoryIndex === null || $configIndex === null) {
            throw new RuntimeException('ستون‌های دسته‌بندی و کانفیگ در فایل یافت نشد.');
        }

        $result = [
            'categories_created' => 0,
            'categories_updated' => 0,
            'configs_imported' => 0,
            'duplicate_configs' => 0,
            'skipped_rows' => 0,
            'errors' => [],
        ];

        $categoryCache = [];
        $importedConfigs = [];

        foreach (array_slice($rows, 1) as $rowNumber => $row) {
            $line = $rowNumber + 2;

            try {
                $categoryName = trim((string) ($row[$categoryIndex] ?? ''));
                $config = trim((string) ($row[$configIndex] ?? ''));

                if ($categoryName === '' || $config === '') {
                    $result['skipped_rows']++;
                    continue;
                }

                $price = $priceIndex !== null ? $this->parsePrice($row[$priceIndex] ?? null) : 0;
                $priceInDollar = $priceInDollarIndex !== null
                    ? $this->parsePrice($row[$priceInDollarIndex] ?? null, true)
                    : 0.0;
                $subscriptionLink = $subscriptionIndex !== null
                    ? trim((string) ($row[$subscriptionIndex] ?? ''))
                    : '';
                $panelLink = $panelLinkIndex !== null
                    ? trim((string) ($row[$panelLinkIndex] ?? ''))
                    : '';

                if ($subscriptionLink === '') {
                    $subscriptionLink = $config;
                }
                if ($panelLink === '') {
                    $panelLink = $config;
                }

                $category = $this->resolveCategory(
                    $panel,
                    $categoryName,
                    $price,
                    $priceInDollar,
                    $categoryCache,
                    $result
                );

                $configKey = $this->configFingerprint($config);
                $duplicateInFile = isset($importedConfigs[$configKey]);
                $duplicateInDatabase = $this->configExistsOnPanel($panel->id, $config);

                if ($duplicateInFile || $duplicateInDatabase) {
                    $result['duplicate_configs']++;
                    $result['errors'][] = "ردیف {$line}: کانفیگ تکراری بود؛ دسته‌بندی به‌روزرسانی شد.";
                    continue;
                }

                Product::create([
                    'product_categories_id' => $category->id,
                    'configs' => $config,
                    'subscription_link' => $subscriptionLink,
                    'panel_link' => $panelLink,
                    'isActive' => true,
                    'deactive_by_admin' => false,
                ]);

                $importedConfigs[$configKey] = true;
                $result['configs_imported']++;
            } catch (\Throwable $th) {
                $result['skipped_rows']++;
                $result['errors'][] = "ردیف {$line}: {$th->getMessage()}";
                \Log::warning("Inventory import row {$line} failed: {$th->getMessage()}");
            }
        }

        return $result;
    }

    /**
     * @param array<string, ProductCategory> $categoryCache
     * @param array<string, mixed> $result
     */
    private function resolveCategory(
        Pannel $panel,
        string $categoryName,
        int $price,
        float $priceInDollar,
        array &$categoryCache,
        array &$result
    ): ProductCategory {
        $cacheKey = mb_strtolower($categoryName);

        if (isset($categoryCache[$cacheKey])) {
            $category = $categoryCache[$cacheKey];
            if ($this->updateCategoryPricing($category, $price, $priceInDollar)) {
                $result['categories_updated']++;
            }

            return $category;
        }

        $category = ProductCategory::query()
            ->where('pannel_id', $panel->id)
            ->where('category_name', $categoryName)
            ->first();

        if ($category === null) {
            $category = ProductCategory::create([
                'pannel_id' => $panel->id,
                'category_name' => $categoryName,
                'price' => $price,
                'price_in_dollar' => $priceInDollar,
                'expire_day' => 30,
                'volume' => 0,
                'rechargable' => false,
                'show_subscription_link' => false,
                'show_pannel_link' => false,
                'send_config_to_user' => true,
                'is_active' => true,
                'ip_limit' => 0,
            ]);
            $result['categories_created']++;
        } elseif ($this->updateCategoryPricing($category, $price, $priceInDollar)) {
            $result['categories_updated']++;
        }

        $categoryCache[$cacheKey] = $category;

        return $category;
    }

    private function updateCategoryPricing(
        ProductCategory $category,
        int $price,
        float $priceInDollar
    ): bool {
        $updated = false;

        if ($price > 0 && (int) $category->price !== $price) {
            $category->price = $price;
            $updated = true;
        }

        if ($priceInDollar > 0 && (float) $category->price_in_dollar !== $priceInDollar) {
            $category->price_in_dollar = $priceInDollar;
            $updated = true;
        }

        if ($category->rechargable) {
            $category->rechargable = false;
            $updated = true;
        }

        if ($updated) {
            $category->save();
        }

        return $updated;
    }

    private function configExistsOnPanel(int $panelId, string $config): bool
    {
        return Product::query()
            ->where('configs', $config)
            ->whereHas('product_category', function ($query) use ($panelId) {
                $query->where('pannel_id', $panelId);
            })
            ->exists();
    }

    private function configFingerprint(string $config): string
    {
        return hash('sha256', trim($config));
    }

    public function buildTemplateRows(): array
    {
        return [
            ['دسته‌بندی', 'قیمت (تومان)', 'کانفیگ'],
            ['آلمان ۱ ماهه', '50000', 'vless://example-config'],
            ['ترکیه ۳ ماهه', '120000', 'vmess://example-config'],
        ];
    }

    public function buildTemplateCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($this->buildTemplateRows() as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$content;
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = str_replace(['‌', ' '], '_', $value);
        $value = mb_strtolower($value);

        return str_replace(['(', ')', '（', '）'], '', $value);
    }

    /**
     * @param array<int, string|null> $header
     * @param array<int, string> $aliases
     */
    private function findColumnIndex(array $header, array $aliases): ?int
    {
        foreach ($header as $index => $column) {
            if ($column === null) {
                continue;
            }

            foreach ($aliases as $alias) {
                if ($column === $this->normalizeHeader($alias)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function parsePrice(mixed $value, bool $allowFloat = false): int|float
    {
        if ($value === null || $value === '') {
            return $allowFloat ? 0.0 : 0;
        }

        $normalized = str_replace([',', ' '], '', (string) $value);
        if ($allowFloat) {
            return (float) $normalized;
        }

        return (int) $normalized;
    }
}
