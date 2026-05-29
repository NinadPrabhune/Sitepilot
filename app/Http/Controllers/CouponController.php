<?php

namespace App\Http\Controllers;

use App\DataTables\CouponDataTable;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\UserCoupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{

    /**
     * List all coupons
     *
     * @authenticated
     * @response view="coupon.index"
     */
    public function index(CouponDataTable $dataTable)
    {
        if(\Auth::user()->isAbleTo('coupon manage'))
        {
            return  $dataTable->render('coupon.index');
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    /**
     * Show create coupon form
     *
     * @authenticated
     * @response view="coupon.create"
     */
    public function create()
    {
        if(\Auth::user()->isAbleTo('coupon create'))
        {
            $coupanType = Coupon::$couponType;
            $plans = Plan::pluck('name','id');
            return view('coupon.create',compact('coupanType','plans'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    /**
     * Store a new coupon
     *
     * @authenticated
     * @bodyParam name string required Coupon name. Max 255 chars.
     * @bodyParam type string required Coupon type. Must be one of: percentage, flat, fixed.
     * @bodyParam minimum_spend numeric required Minimum spend amount. Must be greater than 0.
     * @bodyParam maximum_spend numeric required Maximum spend amount. Must be greater than 0.
     * @bodyParam discount numeric required Discount amount. Must be greater than 0.
     * @bodyParam usage_limit_per_coupon numeric required Usage limit per coupon. Must be greater than 0.
     * @bodyParam usage_limit_per_user numeric required Usage limit per user. Must be greater than 0.
     * @bodyParam coupon_type string required Coupon generation type. Must be auto or manual.
     * @bodyParam expiry_date string required Expiry date. Format: Y-m-d.
     * @bodyParam autoCode string Coupon code for auto-generated coupons. Required if coupon_type is auto. Must be unique.
     * @bodyParam manualCode string Coupon code for manual coupons. Required if coupon_type is manual. Must be unique.
     * @bodyParam included_module array Included module IDs for fixed type coupons.
     * @bodyParam excluded_module array Excluded module IDs for fixed type coupons.
     * @response redirect="coupons.index"
     */
    public function store(Request $request)
    {
        if(\Auth::user()->isAbleTo('coupon create'))
        {
            $validator = \Validator::make(
                $request->all(), [
                                'name' => 'required|max:255',
                                'type' => 'required|in:'.implode(',',array_keys(Coupon::$couponType)),
                                'minimum_spend'=>'required|numeric|gt:0',
                                'maximum_spend'=>'required|numeric|gt:0',
                                'discount' => 'required|numeric|gt:0',
                                'usage_limit_per_coupon' => 'required|numeric|gt:0',
                                'usage_limit_per_user' => 'required|numeric|gt:0',
                                'coupon_type' => 'required|in:auto,manual',
                                'expiry_date' => 'required|date_format:Y-m-d',
                                'autoCode' => 'required_if:coupon_type,auto|unique:coupons,code',
                                'manualCode' => 'required_if:coupon_type,manual|unique:coupons,code',
                                'included_module' => 'array',
                                'excluded_module' => 'array',
                            ]
            );

            $validator->sometimes('included_module', 'required', function ($input) {
                return (empty($input->excluded_module) && empty($input->included_module)) && $input->type == 'fixed';
            });

            $validator->sometimes('excluded_module', 'required', function ($input) {
                return (empty($input->excluded_module) && empty($input->included_module)) && $input->type == 'fixed';
            });

            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $coupon                     = new Coupon();
            $coupon->name               = $request->name;
            $coupon->type               = $request->type;
            $coupon->minimum_spend      = $request->minimum_spend;
            $coupon->maximum_spend      = $request->maximum_spend;
            $coupon->discount           = $request->discount;
            $coupon->limit              = $request->usage_limit_per_coupon;
            $coupon->limit_per_user     = $request->usage_limit_per_user;
            $coupon->expiry_date        = $request->expiry_date;

            if($request->type == 'fixed'){
                $coupon->included_module  = !empty($request->included_module) ? implode(',',$request->included_module) : null;
                $coupon->excluded_module  = !empty($request->excluded_module) ? implode(',',$request->excluded_module) : null;
            }

            if($request->coupon_type == 'manual')
            {
                $coupon->code = strtoupper($request->manualCode);
            }
            else
            {
                $coupon->code = $request->autoCode;
            }

            $coupon->save();

            return redirect()->route('coupons.index')->with('success', __('The coupon has been created successfully'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    /**
     * Show coupon details
     *
     * @authenticated
     * @urlParam coupon int required The coupon ID.
     * @response view="coupon.view"
     */
    public function show(Coupon $coupon)
    {
        $userCoupons = UserCoupon::where('coupon', $coupon->id)->get();

        return view('coupon.view', compact('userCoupons'));
    }


    /**
     * Show edit coupon form
     *
     * @authenticated
     * @urlParam coupon int required The coupon ID.
     * @response view="coupon.edit"
     */
    public function edit(Coupon $coupon)
    {
        if(\Auth::user()->isAbleTo('coupon edit'))
        {
            $coupanType = Coupon::$couponType;
            $plans = Plan::pluck('name','id');
            return view('coupon.edit', compact('coupon','coupanType','plans'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    /**
     * Update a coupon
     *
     * @authenticated
     * @urlParam coupon int required The coupon ID.
     * @bodyParam name string required Coupon name. Max 255 chars.
     * @bodyParam type string required Coupon type. Must be one of: percentage, flat, fixed.
     * @bodyParam minimum_spend numeric required Minimum spend amount. Must be greater than 0.
     * @bodyParam maximum_spend numeric required Maximum spend amount. Must be greater than 0.
     * @bodyParam discount numeric required Discount amount. Must be greater than 0.
     * @bodyParam usage_limit_per_coupon numeric required Usage limit per coupon. Must be greater than 0.
     * @bodyParam usage_limit_per_user numeric required Usage limit per user. Must be greater than 0.
     * @bodyParam expiry_date string required Expiry date. Format: Y-m-d.
     * @bodyParam code string required Coupon code. Must be unique except for this coupon.
     * @bodyParam included_module array Included module IDs for fixed type coupons.
     * @bodyParam excluded_module array Excluded module IDs for fixed type coupons.
     * @response redirect="coupons.index"
     */
    public function update(Request $request, Coupon $coupon)
    {
        if(\Auth::user()->isAbleTo('coupon edit'))
        {
            $validator = \Validator::make(
                $request->all(), [
                                'name' => 'required|max:255',
                                'type' => 'required|in:'.implode(',',array_keys(Coupon::$couponType)),
                                'minimum_spend'=>'required|numeric|gt:0',
                                'maximum_spend'=>'required|numeric|gt:0',
                                'discount' => 'required|numeric|gt:0',
                                'usage_limit_per_coupon' => 'required|numeric|gt:0',
                                'usage_limit_per_user' => 'required|numeric|gt:0',
                                'expiry_date' => 'required|date_format:Y-m-d',
                                'code' => 'required|unique:coupons,code,'.$coupon->id,
                                'included_module' => 'array',
                                'excluded_module' => 'array',
                            ]
            );
            if($validator->fails())
            {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $coupon                     = Coupon::find($coupon->id);
            $coupon->name               = $request->name;
            $coupon->type               = $request->type;
            $coupon->minimum_spend      = $request->minimum_spend;
            $coupon->maximum_spend      = $request->maximum_spend;
            $coupon->discount           = $request->discount;
            $coupon->limit              = $request->usage_limit_per_coupon;
            $coupon->limit_per_user     = $request->usage_limit_per_user;
            $coupon->expiry_date        = $request->expiry_date;
            $coupon->code = $request->code;
            if($request->type == 'fixed'){
                $coupon->included_module  = !empty($request->included_module) ? implode(',',$request->included_module) : null;
                $coupon->excluded_module  = !empty($request->excluded_module) ? implode(',',$request->excluded_module) : null;
            }
            $coupon->save();

            return redirect()->route('coupons.index')->with('success', __('The coupon details are updated successfully'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    /**
     * Delete a coupon
     *
     * @authenticated
     * @urlParam coupon int required The coupon ID.
     * @response redirect="coupons.index"
     */
    public function destroy(Coupon $coupon)
    {
        if(\Auth::user()->isAbleTo('coupon delete'))
        {
            $coupon->delete();

            return redirect()->route('coupons.index')->with('success', __('The coupon has been deleted'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function applyCoupon(Request $request)
    {
        $plan = Plan::find($request->plan_id);

        if($plan && $request->coupon != '')
        {
            $price = ($request->duration == 'Year') ? $plan->package_price_yearly : $plan->package_price_monthly;
            $coupons  = Coupon::where('code', strtoupper($request->coupon))->where('is_active', '1')->first();

            if(!empty($coupons) && intval($price) > 0)
            {
                $usedCoupun = $coupons->used_coupon();
                $userUsedCoupon = \Auth::user()->user_coupon_user($coupons);
                if($coupons->limit == $usedCoupun)
                {
                    return response()->json(
                        [
                            'is_success' => false,
                            'price' => $price,
                            'message' => __('This coupon code has expired.'),
                        ]
                    );
                }
                else
                {

                    if ($usedCoupun >= $coupons->limit ||
                        $userUsedCoupon >= $coupons->limit_per_user ||
                        $coupons->minimum_spend > $price ||
                        $coupons->maximum_spend < $price ||
                        $coupons->expiry_date < date('Y-m-d')) {
                        return response()->json(
                            [
                                'is_success' => false,
                                'price' => $price,
                                'message' => __('You Can Not Apply This Coupon!.'),
                            ]
                        );
                    }

                    switch ($coupons->type) {
                        case 'percentage':
                            $discountValue = ($price / 100) * $coupons->discount;
                            $finalPrice = $price - $discountValue;
                            break;
                        case 'flat':
                            $finalPrice = $price - $coupons->discount;
                            break;
                        case 'fixed':
                            if ((!empty($coupons->included_module) && in_array($plan->id, explode(',', $coupons->included_module))) ||
                                (empty($coupons->included_module) && !in_array($plan->id, explode(',', $coupons->excluded_module)))) {
                                $finalPrice = $price - $coupons->discount;
                            } else {
                                return response()->json(
                                    [
                                        'is_success' => false,
                                        'price' => $price,
                                        'message' => __('You Can Not Apply This Coupon!.'),
                                    ]
                                );
                            }
                            break;
                        default:
                            return response()->json(
                                [
                                    'is_success' => false,
                                    'price' => $price,
                                    'message' => __('You Can Not Apply This Coupon!.'),
                                ]
                            );
                    }

                    return response()->json(
                        [
                            'is_success' => true,
                            'final_price' => $finalPrice,
                            'price' => $price,
                            'message' => __('Coupon code has applied successfully.'),
                        ]
                    );
                }
            }
            else
            {
                return response()->json(
                    [
                        'is_success' => false,
                        'price' => $price,
                        'message' => __('This coupon code is invalid or has expired.'),
                    ]
                );
            }
        }
    }
}
