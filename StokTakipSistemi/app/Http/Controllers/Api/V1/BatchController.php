<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchStoreRequest;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BatchController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Parti listesi. ?product_id=, ?filter=expiring|expired, ?days= (expiring için).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $days = (int) ($request->query('days', 30));

        $batches = Batch::query()
            ->where('batches.company_id', $request->user()->company_id)
            ->when($request->filled('product_id'), fn ($q) => $q->where('batches.product_id', $request->integer('product_id')))
            ->with('product')
            ->withRemainingQuantity()
            ->when($request->query('filter') === 'expiring', fn ($q) => $q->expiringWithin($days))
            ->when($request->query('filter') === 'expired', fn ($q) => $q->expired())
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC, received_at ASC')
            ->paginate(20);

        return BatchResource::collection($batches);
    }

    public function show(Request $request, Batch $batch): BatchResource
    {
        abort_unless($batch->company_id === $request->user()->company_id, 404);

        return new BatchResource($batch->load('product')->loadRemainingQuantity());
    }

    /**
     * Stok girişi: SKT'li parti oluşturur ve "in" hareketini yazar (yalnızca admin).
     */
    public function store(BatchStoreRequest $request): JsonResponse
    {
        $product = Product::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($request->integer('product_id'));

        $batch = $this->stockService->stockIn($request->user(), $product, $request->validated());

        return (new BatchResource($batch->load('product')->loadRemainingQuantity()))
            ->response()
            ->setStatusCode(201);
    }
}
