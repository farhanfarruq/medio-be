<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ShippingAddress;
use App\Services\RajaOngkirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(private RajaOngkirService $rajaOngkir) {}

    public function provinces(): JsonResponse
    {
        return response()->json($this->rajaOngkir->getProvinces());
    }

    public function cities(Request $request): JsonResponse
    {
        return response()->json(
            $this->rajaOngkir->getCities($request->province_id ?? ""),
        );
    }

    public function districts(Request $request): JsonResponse
    {
        return response()->json(
            $this->rajaOngkir->getDistricts($request->city_id ?? ""),
        );
    }

    public function cost(Request $request): JsonResponse
    {
        $request->validate([
            "destination_district_id" => "required|string",
            "weight" => "required|integer|min:1",
        ]);

        $costs = $this->rajaOngkir->calculateAllCouriers(
            $request->destination_district_id,
            $request->weight,
        );

        return response()->json($costs);
    }

    public function getAddresses(Request $request): JsonResponse
    {
        $addresses = ShippingAddress::where("user_id", $request->user()->id)
            ->orderByDesc("is_default")
            ->get();

        return response()->json($addresses);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "recipient_name" => "required|string|max:255",
            "phone" => "required|string|max:20",
            "province" => "required|string",
            "province_id" => "required",
            "city" => "required|string",
            "city_id" => "required",
            "district" => "required|string",
            "district_id" => "required",
            "postal_code" => "required|string|max:10",
            "address" => "required|string",
            "is_default" => "boolean",
        ]);

        if (!empty($validated["is_default"])) {
            ShippingAddress::where("user_id", $request->user()->id)->update([
                "is_default" => false,
            ]);
        }

        $address = ShippingAddress::create([
            ...$validated,
            "province_id" => (string) $validated["province_id"],
            "city_id" => (string) $validated["city_id"],
            "district_id" => (string) $validated["district_id"],
            "user_id" => $request->user()->id,
            "is_default" => $validated["is_default"] ?? false,
        ]);

        return response()->json($address, 201);
    }

    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $address = ShippingAddress::where("id", $id)
            ->where("user_id", $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            "recipient_name" => "string|max:255",
            "phone" => "string|max:20",
            "province" => "string",
            "province_id" => "nullable",
            "city" => "string",
            "city_id" => "nullable",
            "district" => "string",
            "district_id" => "nullable",
            "postal_code" => "string|max:10",
            "address" => "string",
            "is_default" => "boolean",
        ]);

        if (!empty($validated["is_default"])) {
            ShippingAddress::where("user_id", $request->user()->id)->update([
                "is_default" => false,
            ]);
        }

        if (array_key_exists("province_id", $validated)) {
            $validated["province_id"] = (string) $validated["province_id"];
        }

        if (array_key_exists("city_id", $validated)) {
            $validated["city_id"] = (string) $validated["city_id"];
        }

        if (array_key_exists("district_id", $validated)) {
            $validated["district_id"] = (string) $validated["district_id"];
        }

        $address->update($validated);

        return response()->json($address);
    }

    public function destroyAddress(Request $request, int $id): JsonResponse
    {
        ShippingAddress::where("id", $id)
            ->where("user_id", $request->user()->id)
            ->firstOrFail()
            ->delete();

        return response()->json(["message" => "Alamat berhasil dihapus"]);
    }
}
