<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Http\Requests\CustomerRequest;
class CustomerController extends Controller
{
    public function index(Request $request){
       $customer = Customer::query()
                ->when($request->search,function($query, $search){
                    $query->where(function ($q) use ($search){
                        $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                    });
                })
            ->latest()
            ->paginate(10);
            return response()->json([
                'success' =>true,
                'data'=>$customer,
                'message'=>'Customer Detail Fetched.'
            ],200);
      
    }

    public function store(CustomerRequest $request)
    {
        $customer = Customer::create($request->validated());
        auditLog($request->user()->id, 'create', Customer::class, $customer->id, null, $customer->toArray());
        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
    }

    // Show one customer
    public function show(Customer $customer)
    {
        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer){
        $oldCustomer = $customer->only(['name','email','phone','address']);
        $customer->update($request->validated());
        auditLog($request->user()->id, 'update', Customer::class, $customer->id, $oldCustomer, $customer->fresh()->only(['name','email','phone','address']));
        return response()->json([
            'success'=>true,
            'data'=>$customer->fresh()
        ]);
    }
     public function destroy(Customer $customer)
    {
        $customer->delete();
        auditLog(request()->user()->id, 'delete', Customer::class, $customer->id, $customer->toArray(), null);
        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }
}
