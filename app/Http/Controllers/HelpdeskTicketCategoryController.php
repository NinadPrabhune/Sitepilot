<?php

namespace App\Http\Controllers;

use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelpdeskTicketCategoryController extends Controller
{
    /**
     * List all helpdesk ticket categories.
     *
     * @authenticated
     * @response view="ticket_category.index"
     */
    public function index()
    {
        if (Auth::user()->isAbleTo('helpdeskticket setup manage')) {
            $categories = HelpdeskTicketCategory::get();
            return view('ticket_category.index', compact('categories'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new ticket category.
     *
     * @authenticated
     * @response view="ticket_category.create"
     */
    public function create()
    {
        if (Auth::user()->isAbleTo('helpdeskticket setup create')) {
            return view('ticket_category.create');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Store a newly created ticket category.
     *
     * @authenticated
     * @bodyParam name string required Category name. Example: Bug Report
     * @bodyParam color string required Category color (hex). Example: #ff0000
     * @response view="ticket_category.index" (redirect)
     */
    public function store(Request $request)
    {
        if (Auth::user()->isAbleTo('helpdeskticket setup create')) {
            $validation = [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'color' => [
                    'required',
                    'string',
                    'max:255',
                ],
            ];
            $request->validate($validation);

            $post = [
                'name' => $request->name,
                'color' => $request->color,
            ];

            HelpdeskTicketCategory::create($post);

            return redirect()->route('helpdeskticket-category.index')->with('success', __('The category has been created successfully'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Display a ticket category (redirects to index).
     *
     * @authenticated
     * @urlParam helpdeskTicketCategory int required Category ID. Example: 1
     * @response view="ticket_category.index" (redirect)
     */
    public function show(HelpdeskTicketCategory $helpdeskTicketCategory)
    {
        return redirect()->route('helpdeskticket-category.index')->with('error', __('Permission denied.'));

    }

    /**
     * Show the form for editing a ticket category.
     *
     * @authenticated
     * @urlParam id int required Category ID. Example: 1
     * @response view="ticket_category.edit"
     */
    public function edit($id)
    {
        if (Auth::user()->isAbleTo('helpdeskticket setup edit')) {
            $category = HelpdeskTicketCategory::find($id);
            return view('ticket_category.edit', compact('category'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Update a ticket category.
     *
     * @authenticated
     * @urlParam id int required Category ID. Example: 1
     * @bodyParam name string required Category name. Example: Feature Request
     * @bodyParam color string required Category color (hex). Example: #00ff00
     * @response view="ticket_category.index" (redirect)
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->isAbleTo('helpdeskticket setup edit')) {
            $category        = HelpdeskTicketCategory::find($id);
            $category->name  = $request->name;
            $category->color = $request->color;
            $category->save();

            return redirect()->route('helpdeskticket-category.index')->with('success', __('The category details are updated successfully'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Delete a ticket category.
     *
     * @authenticated
     * @urlParam id int required Category ID. Example: 1
     * @response view="ticket_category.index" (redirect)
     */
    public function destroy($id)
    {
        if (Auth::user()->isAbleTo('helpdeskticket setup delete')) {
            $tickets = HelpdeskTicket::where('category', $id)->get();
            if (count($tickets) == 0) {
                $category = HelpdeskTicketCategory::find($id);
                $category->delete();
                return redirect()->route('helpdeskticket-category.index')->with('success', __('The category has been deleted'));
            } else {
                return redirect()->route('helpdeskticket-category.index')->with('error', __('This category is Used on Ticket.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
