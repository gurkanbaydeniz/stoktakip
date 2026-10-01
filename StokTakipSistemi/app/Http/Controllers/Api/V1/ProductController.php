<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductStoreRequest;
use App\Http\Requests\Product\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Ürün listesi. ?search= (ad/barkod) ve ?low_stock=1 filtreleri.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('products.company_id', $request->user()->company_id)
            ->withCount('batches')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.str_replace('%', '\%', $request->string('search')).'%';
                $query->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('barcode', 'like', $term));
            })
            ->when(
                $request->boolean('low_stock'),
                fn ($query) => $query->belowCriticalStock(), // kendi SUM+HAVING join'ini kurar
                fn ($query) => $query->withStockQuantity(),
            )
            ->orderBy('name')
            ->paginate(20);

        return ProductResource::collection($products);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        abort_unless($product->company_id === $request->user()->company_id, 404);

        return new ProductResource(
            $product->loadCount('batches')->load([
                'batches' => fn ($query) => $query->withRemainingQuantity()->orderByRaw('expiry_date IS NULL, expiry_date ASC'),
            ])
        );
    }

    /**
     * Yeni ürün (yalnızca admin).
     */
    public function store(ProductStoreRequest $request): JsonResponse
    {
        $product = Product::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ProductUpdateRequest $request, Product $product): ProductResource
    {
        abort_unless($product->company_id === $request->user()->company_id, 404);

        $product->update($request->validated());

        return new ProductResource($product);
    }

    /**
     * Stok geçmişi olan ürün silinemez (denetim izi korunur).
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->company_id === $request->user()->company_id, 404);

        if ($product->stockMovements()->exists() || $product->batches()->exists()) {
            abort(409, 'Bu ürüne ait parti veya stok hareketi bulunduğundan silinemez. Yalnızca hareketi olmayan ürünler silinebilir.');
        }

        $product->delete();

        return response()->json(['message' => 'Ürün silindi.']);
    }
}
