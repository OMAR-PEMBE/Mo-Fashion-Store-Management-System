<?php

namespace App\Http\Controllers;

use App\Services\BusinessSettingsService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request, BusinessSettingsService $service)
    {
        $service->authorize($request->user());
        $values = $service->values();

        return view('settings.edit', ['values' => $values, 'revision' => $service->revision($values)]);
    }

    public function update(Request $request, BusinessSettingsService $service)
    {
        $service->save($request->all(), $request->user());

        return redirect()->route('settings.edit')->with('status', 'Business settings updated.');
    }
}
