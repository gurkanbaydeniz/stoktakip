<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BatchResource;
use App\Http\Resources\ProductResource;
use App\Models\Batch;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bildirim/uyarı altyapısının veri kaynağı: yaklaşan SKT, geçmiş SKT,
 * kritik seviyenin altındaki stoklar. Mobil/Web istemciler bu ucu
 * periyodik yoklayarak uyarı gösterebilir (ileride push'a dönüşür).
 */
class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = validator(['days' => $request->query('days', 30)], [
            'days' => ['integer', 'min:1', 'max:365'],
        ])->validate();
        $days = $data['days'];

        $companyId = $request->user()->company_id;

        $expiring = Batch::query()
            ->where('batches.company_id', $companyId)
            ->with(['product'])
            ->withRemainingQuantity()
            ->expiringWithin($days)
            ->orderBy('expiry_date')
            ->get();

        $expired = Batch::query()
            ->where('batches.company_id', $companyId)
            ->with(['product'])
            ->withRemainingQuantity()
            ->expired()
            ->orderBy('expiry_date')
            ->get();

        $lowStock = Product::query()
            ->where('products.company_id', $companyId)
            ->belowCriticalStock() // kendi stock_quantity SUM+HAVING join'ini kurar
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'days' => $days,
                'expiring_batches' => BatchResource::collection($expiring),
                'expired_batches' => BatchResource::collection($expired),
                'low_stock_products' => ProductResource::collection($lowStock),
            ],
        ]);
    }
}
