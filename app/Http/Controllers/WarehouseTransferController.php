<?php

namespace App\Http\Controllers;

use App\DataTables\WarehouseTransferDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Workdo\Pos\Entities\Pos;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use App\Models\WarehouseTransfer;


class WarehouseTransferController extends Controller
{
    /**
     * Display a listing of warehouse transfers.
     *
     * @authenticated
     * @response view="warehouses-transfer.index"
     */
    public function index(WarehouseTransferDataTable $dataTable)
    {
        return $dataTable->render('warehouses-transfer.index');
    }

    /**
     * Show the form for creating a new warehouse transfer.
     *
     * @authenticated
     * @response view="warehouses-transfer.create"
     */
    public function create()
    {
        $from_warehouses      = Warehouse ::where('created_by', '=', creatorId())->where('workspace',getActiveWorkSpace())->get();
        $to_warehouses     = warehouse::where('created_by', '=', creatorId())->where('workspace',getActiveWorkSpace())->get()->pluck('name', 'id');
        $to_warehouses->prepend('Select Warehouse', '');
        $ware_pro= WarehouseProduct::join('product_services', 'warehouse_products.product_id', '=', 'product_services.id')
            ->pluck('name','product_id');
        $ware_pro->prepend('Select products', '');
        return view('warehouses-transfer.create', compact('from_warehouses','to_warehouses','ware_pro'));
    }

    /**
     * Store a newly created warehouse transfer.
     *
     * @authenticated
     * @bodyParam from_warehouse integer required The source warehouse ID. Example: 1
     * @bodyParam to_warehouse integer required The destination warehouse ID. Example: 2
     * @bodyParam product_id integer required The product ID. Example: 1
     * @bodyParam quantity integer required The quantity to transfer. Example: 10
     * @bodyParam date string The transfer date. Example: 2025-05-01
     * @response redirect to="warehouses-transfer.index"
     */
    public function store(Request $request)
    {

        if(\Auth::user()->isAbleTo('warehouse create'))
        {

            $validator = \Validator::make(
                $request->all(), [
                    'from_warehouse' => 'required',
                    'to_warehouse' => 'required',
                    'product_id' => 'required',
                    'quantity' => 'required',
                ]
            );
            if($validator->fails())
            {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $fromWarehouse    = WarehouseProduct::where('warehouse_id',$request->from_warehouse)
                ->where('product_id',$request->product_id)->first();

            if($request->quantity <= $fromWarehouse->quantity)
            {
                $warehouse_transfer                  = new WarehouseTransfer();
                $warehouse_transfer->from_warehouse  = $request->from_warehouse;
                $warehouse_transfer->to_warehouse    = $request->to_warehouse;
                $warehouse_transfer->product_id      = $request->product_id;
                $warehouse_transfer->quantity        = $request->quantity;
                $warehouse_transfer->date            = $request->date;
                $warehouse_transfer->workspace       = getActiveWorkSpace();
                $warehouse_transfer->created_by      = creatorId();
                $warehouse_transfer->save();
            }
            else
            {
                return redirect()->route('warehouses-transfer.index')->with('error', __('Product out of stock!.'));
            }
            WarehouseTransfer::warehouse_transfer_qty($request->from_warehouse,$request->to_warehouse,$request->product_id,$request->quantity);

            return redirect()->route('warehouses-transfer.index')->with('success', __('The warehouse transfer has been created successfully'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Get products and available "to" warehouses for a selected warehouse via AJAX.
     *
     * @authenticated
     * @bodyParam warehouse_id integer required The source warehouse ID. Example: 1
     * @response status=200 scenario="success" {"ware_products": {1: "Product A"}, "to_warehouses": {2: "Warehouse B"}}
     */
    public function getproduct(Request $request)
    {
        if($request->warehouse_id == 0)
        {
            $ware_products= WarehouseProduct::join('product_services', 'warehouse_products.product_id', '=', 'product_services.id')
                ->get()
                ->pluck('name', 'product_id')->toArray();
            $to_warehouses     = warehouse::where('created_by', '=', creatorId())->where('workspace',getActiveWorkSpace())->pluck('name', 'id');
        }
        else
        {
            $ware_products= WarehouseProduct::join('product_services', 'warehouse_products.product_id', '=', 'product_services.id')
                ->where('warehouse_products.warehouse_id', $request->warehouse_id)
                ->get()
                ->pluck('name', 'product_id')->toArray();
            $to_warehouses     = warehouse::where('id','!=',$request->warehouse_id)->where('created_by', '=', creatorId())->where('workspace',getActiveWorkSpace())->pluck('name', 'id');
        }
        $result = [];
        $result['ware_products'] = $ware_products;
        $result['to_warehouses'] = $to_warehouses;
        return response()->json($result);
    }

    /**
     * Get product quantities for a selected product via AJAX.
     *
     * @authenticated
     * @bodyParam product_id integer required The product ID. Example: 1
     * @response status=200 scenario="success" {1: 50, 2: 30}
     */
    public function getquantity(Request $request)
    {
        if($request->product_id == 0)
        {
            $pro_qty = WarehouseProduct::where('created_by', '=', creatorId())->where('workspace',getActiveWorkSpace())
                ->get()->pluck('quantity', 'product_id')->toArray();
        }
        else
        {
            $pro_qty = WarehouseProduct::where('created_by', '=', creatorId())->where('workspace',getActiveWorkSpace())
                ->where('product_id', $request->product_id)
                ->get()->pluck('quantity');
        }
        return response()->json($pro_qty);
    }


    /**
     * Display the specified warehouse transfer (redirects to index).
     *
     * @authenticated
     * @urlParam id integer required The transfer ID. Example: 1
     * @response redirect to="warehouses-transfer.index"
     */
    public function show($id)
    {
        return redirect()->route('warehouses-transfer.index');
    }

    /**
     * Show the form for editing a warehouse transfer.
     *
     * @authenticated
     * @urlParam id integer required The transfer ID. Example: 1
     * @response view="pos::edit"
     */
    public function edit($id)
    {
        return view('pos::edit');
    }

    /**
     * Update the specified warehouse transfer (not implemented).
     *
     * @authenticated
     * @urlParam id integer required The transfer ID. Example: 1
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified warehouse transfer.
     *
     * @authenticated
     * @urlParam id integer required The transfer ID. Example: 1
     * @response redirect to="warehouses-transfer.index"
     */


    public function destroy($id)
    {
        if(\Auth::user()->isAbleTo('warehouse delete'))
        {
            $warehouseTransfer = WarehouseTransfer::find($id);
            if($warehouseTransfer->created_by == creatorId()  && $warehouseTransfer->workspace == getActiveWorkSpace())
            {
                WarehouseTransfer::warehouse_transfer_qty($warehouseTransfer->to_warehouse,$warehouseTransfer->from_warehouse,$warehouseTransfer->product_id,$warehouseTransfer->quantity,'delete');

                $warehouseTransfer->delete();

                return redirect()->route('warehouses-transfer.index')->with('success', __('The warehouse Transfer has been deleted'));
            }
            else
            {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
