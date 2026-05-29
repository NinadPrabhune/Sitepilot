<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the main dashboard page.
     *
     * @response view="dashboard.index"
     */
    public function index()
    {
        return view('dashboard.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @response view="null"
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @bodyParam name string required Resource name. Example: New Item
     * @response view="null"
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @urlParam id string required Resource ID. Example: 1
     * @response view="null"
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @urlParam id string required Resource ID. Example: 1
     * @response view="null"
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @urlParam id string required Resource ID. Example: 1
     * @bodyParam name string required Resource name. Example: Updated Name
     * @response view="null"
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @urlParam id string required Resource ID. Example: 1
     * @response view="null"
     */
    public function destroy(string $id)
    {
        //
    }

}
