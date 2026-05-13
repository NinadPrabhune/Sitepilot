<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoiceItem;
use App\Http\Requests\StorePurchaseInvoiceItemRequest;
use App\Http\Requests\UpdatePurchaseInvoiceItemRequest;

class PurchaseInvoiceItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePurchaseInvoiceItemRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePurchaseInvoiceItemRequest $request, PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        //
    }
}
