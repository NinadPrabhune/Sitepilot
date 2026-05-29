<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoiceItem;
use App\Http\Requests\StorePurchaseInvoiceItemRequest;
use App\Http\Requests\UpdatePurchaseInvoiceItemRequest;

class PurchaseInvoiceItemController extends Controller
{
    /**
     * Display a listing of purchase invoice items.
     *
     * @authenticated
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new purchase invoice item.
     *
     * @authenticated
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created purchase invoice item.
     *
     * @authenticated
     */
    public function store(StorePurchaseInvoiceItemRequest $request)
    {
        //
    }

    /**
     * Display the specified purchase invoice item.
     *
     * @authenticated
     */
    public function show(PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        //
    }

    /**
     * Show the form for editing the specified purchase invoice item.
     *
     * @authenticated
     */
    public function edit(PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        //
    }

    /**
     * Update the specified purchase invoice item.
     *
     * @authenticated
     */
    public function update(UpdatePurchaseInvoiceItemRequest $request, PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        //
    }

    /**
     * Remove the specified purchase invoice item.
     *
     * @authenticated
     */
    public function destroy(PurchaseInvoiceItem $purchaseInvoiceItem)
    {
        //
    }
}
