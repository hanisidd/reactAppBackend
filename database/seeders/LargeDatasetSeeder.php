<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates large, realistic datasets for load/UI testing.
 *
 * Usage:
 *   php artisan db:seed --class=Database\\Seeders\\LargeDatasetSeeder
 *
 * Tune the counts below, or override via env vars so CI/staging can use
 * smaller numbers than your local machine:
 *   SEED_CATEGORIES=30 SEED_PRODUCTS=5000 SEED_ORDERS=10000 php artisan db:seed --class=LargeDatasetSeeder
 *
 * Why bulk insert instead of Model::factory()->create() in a loop:
 * Eloquent's ->create() fires model events, casts, and a *separate INSERT
 * query* per row. For 5,000+ rows that's 5,000+ round trips to the DB and
 * is easily 50-100x slower than batching raw arrays through DB::table()->insert().
 * We lose model events for these rows (fine — this is test data, not
 * something a user action should react to), but we keep using the
 * factories to *generate* realistic field values.
 */
class LargeDatasetSeeder extends Seeder
{
    private const BATCH_SIZE = 500;

    public function run(): void
    {
        $categoryCount = (int) env('SEED_CATEGORIES', 25);
        $productCount = (int) env('SEED_PRODUCTS', 3000);
        $orderCount = (int) env('SEED_ORDERS', 5000);

        $this->command?->info("Seeding {$categoryCount} categories, {$productCount} products, {$orderCount} orders...");

        $categoryIds = $this->seedCategories($categoryCount);
        $productIds = $this->seedProducts($productCount, $categoryIds);
        $this->seedOrders($orderCount, $productIds);

        $this->command?->info('Done.');
    }

private function seedCategories(int $count): array
{
    $now = now();

    $rows = Category::factory()
        ->count($count)
        ->make()
        ->map(function ($category) use ($now) {
            $data = $category->toArray();

            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            return $data;
        })
        ->toArray();

    foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
        DB::table('categories')->insert($chunk);
    }

    return DB::table('categories')->pluck('id')->all();
}

    private function seedProducts(int $count, array $categoryIds): array
    {
        $insertedIds = [];
        $now = now();

        collect(range(1, $count))
            ->chunk(self::BATCH_SIZE)
            ->each(function ($chunkIndexes) use ($categoryIds, $now, &$insertedIds) {
                $rows = Product::factory()
                    ->count($chunkIndexes->count())
                    ->make()
                    ->map(function ($product) use ($categoryIds, $now) {
                        $data = $product->toArray();
                        $data['category_id'] = $categoryIds[array_rand($categoryIds)];
                        $data['created_at'] = $now;
                        $data['updated_at'] = $now;
                        return $data;
                    })
                    ->toArray();

                DB::table('products')->insert($rows);
            });

        return DB::table('products')->pluck('id')->all();
    }

    private function seedOrders(int $count, array $productIds): void
    {
        $now = now();
        $startingOrderNumber = 10000 + (int) (DB::table('orders')->max('id') ?? 0);

        collect(range(1, $count))
            ->chunk(self::BATCH_SIZE)
            ->each(function ($chunkIndexes) use ($productIds, $now, $startingOrderNumber) {
                $orderRows = [];
                $itemsToInsertAfter = []; // keyed by array index within this chunk

                foreach ($chunkIndexes as $i) {
                    $productId = $productIds[array_rand($productIds)];
                    $product = DB::table('products')->select('price')->find($productId);
                    $qty = random_int(1, 3);
                    $lineTotal = $product ? $product->price * $qty : 0;

                    $order = Order::factory()->make()->toArray();
                    $order['order_number'] = 'ORD-' . ($startingOrderNumber + $i);
                    $order['product_id'] = $productId;
                    $order['subtotal'] = $lineTotal;
                    $order['delivery_fee'] = random_int(0, 1) ? 250 : 0;
                    $order['tax_amount'] = round($lineTotal * 0.05, 2);
                    $order['discount_amount'] = 0;
                    $order['total_amount'] = $order['subtotal'] + $order['delivery_fee'] + $order['tax_amount'];
                    $order['created_at'] = $now;
                    $order['updated_at'] = $now;

                    $orderRows[] = $order;
                    $itemsToInsertAfter[] = [
                        'product_id' => $productId,
                        'unit_price' => $product->price ?? 0,
                        'quantity' => $qty,
                        'line_total' => $lineTotal,
                    ];
                }

                DB::table('orders')->insert($orderRows);

                // Fetch the IDs we just inserted (by order_number, which is unique)
                // so we can attach order_items with correct foreign keys.
                $orderNumbers = array_column($orderRows, 'order_number');
                $insertedOrders = DB::table('orders')
                    ->whereIn('order_number', $orderNumbers)
                    ->pluck('id', 'order_number');

                $itemRows = [];
                foreach ($orderRows as $index => $orderRow) {
                    $orderId = $insertedOrders[$orderRow['order_number']] ?? null;
                    if (!$orderId) {
                        continue;
                    }
                    $item = $itemsToInsertAfter[$index];
                    $itemRows[] = [
                        'order_id' => $orderId,
                        'product_id' => $item['product_id'],
                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'line_total' => $item['line_total'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($itemRows)) {
                    DB::table('order_items')->insert($itemRows);
                }
            });
    }
}
