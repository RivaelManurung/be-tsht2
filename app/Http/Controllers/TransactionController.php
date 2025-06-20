<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Gudang;
use App\Models\User;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public static function middleware(): array
    {
        return [
            'auth:api',
            new Middleware('permission:view_transaction', only: ['index']),
            new Middleware('permission:create_transaction', only: ['store']),
            new Middleware('permission:update_transaction', only: ['update', 'updateBarangDetails']),
        ];
    }

    public function index(Request $request)
    {
        $query = Transaction::with([
            'user',
            'transactionType',
            'transactionDetails.barang',
            'transactionDetails.gudang'
        ]);

        if (!$request->user()->hasRole('superadmin')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('transaction_type_id')) {
            $query->where('transaction_type_id', $request->transaction_type_id);
        }

        if ($request->filled('transaction_code')) {
            $query->where('transaction_code', 'LIKE', "%{$request->transaction_code}%");
        }

        if ($request->filled(['transaction_date_start', 'transaction_date_end'])) {
            $query->whereBetween('transaction_date', [$request->transaction_date_start, $request->transaction_date_end]);
        }

        return TransactionResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_type_id' => 'required|integer|exists:transaction_types,id',
            'description' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.barang_kode' => 'required|string|exists:barangs,barang_kode',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->transactionService->processTransaction($request);
        if (!$result['success']) {
            return response()->json(['message' => $result['message'], 'error' => $result['error']], 422);
        }
        return response()->json([
            'message' => 'Transaction berhasil dibuat!',
            'data' => new TransactionResource($result['data'])
        ]);
    }

    public function checkBarcode($barcode)
    {
        $result = $this->transactionService->checkBarcode($barcode);

        if ($result['success'] === 'false') {
            return response()->json($result, 404);
        }

        return response()->json($result, 200);
    }

    public function show($id)
    {
        $transaction = $this->transactionService->find($id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        return new TransactionResource($transaction);
    }

    public function update(Request $request, $id)
    {
        Log::info('Update transaction request', ['id' => $id, 'data' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'transaction_type_id' => 'required|integer|exists:transaction_types,id',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.barang_kode' => 'required|string|exists:barangs,barang_kode',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed', ['errors' => $validator->errors()]);
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $validated = $validator->validated();

        $user = User::find($validated['user_id']);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->role_id !== 1) {
            $gudang = Gudang::where('user_id', $user->id)->first();
            if (!$gudang) {
                return response()->json(['message' => 'No warehouse assigned to this user'], 403);
            }
        }

        $result = $this->transactionService->updateTransaction($id, $validated);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'data' => new TransactionResource($result['data'])
            ], 200);
        } else {
            return response()->json([
                'message' => $result['message'],    
                'error' => $result['error']
            ], 400);
        }
    }
    public function updateBarangDetails(Request $request)
    {
        Log::info('updateBarangDetails request received', $request->all());

        $validator = Validator::make($request->all(), [
            'barang_id' => ['required', 'integer', 'exists:barangs,id'],
            'type' => ['required', 'string', 'in:stok,nama'],
            'new_value' => ['required', $request->input('type') === 'stok' ? 'integer' : 'string'],
            'gudang_id' => ['required', 'integer', 'exists:gudangs,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $user = User::find($validated['user_id']);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->role_id !== 1) {
            $gudang = Gudang::where('id', $validated['gudang_id'])
                ->where('user_id', $user->id)
                ->first();

            if (!$gudang) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update this warehouse.',
                ], 403);
            }
        }

        $result = $this->transactionService->updateBarangDetails(
            $validated['barang_id'],
            $validated['type'],
            $validated['new_value'],
            $user,
            $validated['gudang_id']
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
