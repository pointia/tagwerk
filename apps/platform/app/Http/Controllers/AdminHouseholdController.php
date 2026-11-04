<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\ActiveHousehold;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The household switch in the admin panel's top bar, for someone who
 * manages more than one. Unlike support access it leaves no trace: these
 * are the account's own households.
 */
final class AdminHouseholdController extends Controller
{
    public function __invoke(Request $request, ActiveHousehold $active): RedirectResponse
    {
        $data = $request->validate(['household_id' => ['required', 'integer']]);
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_if($active->switch($user, $request, (int) $data['household_id']) === null, 404);

        return redirect()->to(Dashboard::getUrl(panel: 'admin'));
    }
}
