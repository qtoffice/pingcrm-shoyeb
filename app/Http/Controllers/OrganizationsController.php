<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request as HttpRequest;

class OrganizationsController extends Controller
{
    public function index(): Response
    {
        $userId = Auth::user()->id;
        $path = "org/column/{$userId}";
        $settings = Settings::where('path', $path)->first();
        $visibleColumns = $settings ? json_decode($settings->value, true) : [];

        $allColumns = Organization::allColumns();

        $columnsToSelect = array_intersect($allColumns, $visibleColumns);

        //if visible column does not exist in all columns, then set default columns
        if (empty($visibleColumns) || empty($columnsToSelect)) {
            $columnsToSelect = Organization::defaultColumns();
        }

        return Inertia::render('Organizations/Index', [
            'visibleColumns' => $visibleColumns,
            'filters' => Request::all('search', 'trashed'),
            'organizations' => Auth::user()->account->organizations()
                ->select($columnsToSelect)
                ->orderBy('name')
                ->filter(Request::only('search', 'trashed'))
                ->paginate(10)
                ->withQueryString()
                ->through(fn($organization) => $organization->only($columnsToSelect)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Organizations/Create');
    }

    public function store(): RedirectResponse
    {
        Auth::user()->account->organizations()->create(
            Request::validate([
                'name' => ['required', 'max:100'],
                'email' => ['nullable', 'max:50', 'email'],
                'phone' => ['nullable', 'max:50'],
                'address' => ['nullable', 'max:150'],
                'city' => ['nullable', 'max:50'],
                'region' => ['nullable', 'max:50'],
                'country' => ['nullable', 'max:2'],
                'postal_code' => ['nullable', 'max:25'],
            ])
        );

        return Redirect::route('organizations')->with('success', 'Organization created.');
    }

    public function edit(Organization $organization): Response
    {
        return Inertia::render('Organizations/Edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'email' => $organization->email,
                'phone' => $organization->phone,
                'address' => $organization->address,
                'city' => $organization->city,
                'region' => $organization->region,
                'country' => $organization->country,
                'postal_code' => $organization->postal_code,
                'deleted_at' => $organization->deleted_at,
                'contacts' => $organization->contacts()->orderByName()->get()->map->only('id', 'name', 'city', 'phone'),
            ],
        ]);
    }

    public function update(Organization $organization): RedirectResponse
    {
        $organization->update(
            Request::validate([
                'name' => ['required', 'max:100'],
                'email' => ['nullable', 'max:50', 'email'],
                'phone' => ['nullable', 'max:50'],
                'address' => ['nullable', 'max:150'],
                'city' => ['nullable', 'max:50'],
                'region' => ['nullable', 'max:50'],
                'country' => ['nullable', 'max:2'],
                'postal_code' => ['nullable', 'max:25'],
            ])
        );

        return Redirect::back()->with('success', 'Organization updated.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $organization->delete();

        return Redirect::back()->with('success', 'Organization deleted.');
    }

    public function restore(Organization $organization): RedirectResponse
    {
        $organization->restore();

        return Redirect::back()->with('success', 'Organization restored.');
    }

    public function saveColumnVisibility(HttpRequest $request): RedirectResponse
    {
        $userId = Auth::user()->id;
        $path = "org/column/{$userId}";

        $settings = Settings::updateOrCreate(
            ['path' => $path],
            ['value' => json_encode($request->input('columns'))]
        );

        return Redirect::back();
    }
}
