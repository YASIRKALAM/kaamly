<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Customer\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * List customer addresses
     */
    public function listAddresses(Request $request): JsonResponse
    {
        $profile = $request->user()->customerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Customer profile not initialized.', null, 404);
        }

        $addresses = CustomerAddress::where('customer_profile_id', $profile->id)
            ->selectRaw("id, customer_profile_id, label, address_line_1, address_line_2, area, city, postal_code, is_default, ST_Y(location::geometry) as latitude, ST_X(location::geometry) as longitude, created_at")
            ->orderBy('is_default', 'desc')
            ->get();

        return $this->successResponse($addresses);
    }

    /**
     * Create a new address for the customer
     */
    public function createAddress(Request $request): JsonResponse
    {
        $profile = $request->user()->customerProfile;
        if (!$profile) {
            return $this->errorResponse('PROFILE_NOT_FOUND', 'Customer profile not initialized.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'label' => 'required|string|max:50',
            'address_line_1' => 'required|string',
            'address_line_2' => 'nullable|string',
            'area' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Address validation failed.', $validator->errors(), 422);
        }

        $validated = $validator->validated();

        $address = DB::transaction(function () use ($profile, $validated) {
            if (!empty($validated['is_default'])) {
                CustomerAddress::where('customer_profile_id', $profile->id)->update(['is_default' => false]);
            }

            $addr = CustomerAddress::create([
                'customer_profile_id' => $profile->id,
                'label' => $validated['label'],
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'area' => $validated['area'],
                'city' => $validated['city'],
                'postal_code' => $validated['postal_code'] ?? null,
                'is_default' => $validated['is_default'] ?? false,
            ]);

            $addr->setLocation($validated['latitude'], $validated['longitude']);
            $addr->save();

            if (!empty($validated['is_default'])) {
                $profile->update(['default_address_id' => $addr->id]);
            }

            return $addr;
        });

        return $this->successResponse($address, 'Address successfully created.', [], 201);
    }
}
