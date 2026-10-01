<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockMovementStoreRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Hareket listesi. ?product_id=, ?type=in|out|adjustment.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $movements = StockMovement::query()
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->with(['product', 'user'])
            ->latest('id')
            ->paginate(25);

        return StockMovementResource::collection($movements);
    }

    /**
     * Stok çıkışı (out, FIFO) veya sayım düzeltmesi (adjustment, yalnızca admin).
     */
    public function store(StockMovementStoreRequest $request): JsonResponse
    {
        $product = Product::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($request->integer('product_id'));

        if ($request->input('type') === StockMovement::TYPE_OUT) {
            $batch = $request->filled('batch_id')
                ? Batch::query()
                    ->where('product_id', $product->id)
                    ->findOrFail($request->integer('batch_id'))
                : null;

            $movements = $this->stockService->stockOut(
                $request->user(),
                $product,
                (float) $request->input('quantity'),
                $batch,
                $request->input('note'),
            );

            $loaded = StockMovement::query()
                ->with(['product', 'user'])
                ->whereIn('id', array_column($movements, 'id'))
                ->orderBy('id')
                ->get();

            return StockMovementResource::collection($loaded)
                ->response()
                ->setStatusCode(201);
        }

        $movement = $this->stockService->adjust(
            $request->user(),
            $product,
            (float) $request->input('quantity'),
            $request->integer('batch_id') ?: null,
            $request->input('note'),
        );

        return (new StockMovementResource($movement->load(['product', 'user'])))
            ->response()
            ->setStatusCode(201);
    }
}
