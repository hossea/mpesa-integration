<?php
// app/Http/Controllers/PaymentConfigController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserPaymentConfig;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class PaymentConfigController extends Controller
{
    /**
     * Get all payment configurations for current user/merchant
     */
    public function index(Request $request)
    {
        $userId = $request->user_id ?? Auth::id();

        $configs = UserPaymentConfig::where('user_id', $userId)
            ->orWhereNull('user_id') // Include public/shared configs
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * Create new payment configuration
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'config_name' => 'required|string|max:255',
            'type' => 'required|in:till,paybill',
            'shortcode' => 'required|string|max:20',
            'account_number' => 'required_if:type,paybill|nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_default' => 'nullable|boolean',
            'user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $config = UserPaymentConfig::create([
            'user_id' => $request->user_id ?? Auth::id(),
            'config_name' => $request->config_name,
            'type' => $request->type,
            'shortcode' => $request->shortcode,
            'account_number' => $request->account_number,
            'description' => $request->description,
            'is_default' => $request->is_default ?? false,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->type) . ' configuration created successfully',
            'data' => $config
        ], 201);
    }

    /**
     * Get single payment configuration
     */
    public function show($id)
    {
        $config = UserPaymentConfig::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    /**
     * Update payment configuration
     */
    public function update(Request $request, $id)
    {
        $config = UserPaymentConfig::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'config_name' => 'nullable|string|max:255',
            'type' => 'nullable|in:till,paybill',
            'shortcode' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $config->update($request->only([
            'config_name',
            'type',
            'shortcode',
            'account_number',
            'description',
            'is_active',
            'is_default'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Configuration updated successfully',
            'data' => $config
        ]);
    }

    /**
     * Delete payment configuration
     */
    public function destroy($id)
    {
        $config = UserPaymentConfig::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration not found'
            ], 404);
        }

        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Configuration deleted successfully'
        ]);
    }

    /**
     * Set configuration as default
     */
    public function setDefault($id)
    {
        $config = UserPaymentConfig::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration not found'
            ], 404);
        }

        $config->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Configuration set as default',
            'data' => $config
        ]);
    }

    /**
     * Get default configuration by type
     */
    public function getDefault(Request $request, $type)
    {
        $userId = $request->user_id ?? Auth::id();

        $config = UserPaymentConfig::where('user_id', $userId)
            ->where('type', $type)
            ->default()
            ->active()
            ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => "No default {$type} configuration found"
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    /**
     * Get all Till configurations
     */
    public function getTills(Request $request)
    {
        $userId = $request->user_id ?? Auth::id();

        $configs = UserPaymentConfig::where('user_id', $userId)
            ->till()
            ->active()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * Get all Paybill configurations
     */
    public function getPaybills(Request $request)
    {
        $userId = $request->user_id ?? Auth::id();

        $configs = UserPaymentConfig::where('user_id', $userId)
            ->paybill()
            ->active()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }
}
