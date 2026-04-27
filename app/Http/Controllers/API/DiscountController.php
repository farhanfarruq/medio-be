<?php
/*
 * File Path: /home/farhan/Documents/VsCode Project/optik-medio-ecommerce/medio-be/app/Http/Controllers/API/DiscountController.php
 */

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    /**
     * Validate a discount code
     */
    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $discount = Discount::where('code', $request->code)->first();

        if (!$discount) {
            return response()->json(['message' => 'Kode diskon tidak ditemukan.'], 404);
        }

        if (!$discount->isValid()) {
            return response()->json(['message' => 'Kode diskon sudah tidak berlaku atau kuota habis.'], 422);
        }

        return response()->json([
            'message' => 'Kode diskon berhasil diterapkan.',
            'discount' => [
                'id'    => $discount->id,
                'code'  => $discount->code,
                'type'  => $discount->type,
                'value' => $discount->value,
            ]
        ]);
    }
}
