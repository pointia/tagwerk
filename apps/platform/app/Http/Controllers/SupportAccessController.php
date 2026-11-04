<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\User;
use App\Services\SupportAccessService;
use Filament\Pages\Dashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The household switch in the admin panel's top bar, for the operator. */
final class SupportAccessController extends Controller
{
    public function __construct(private readonly SupportAccessService $support) {}

    public function enter(Request $request): RedirectResponse
    {
        $data = $request->validate(['household_id' => ['required', 'integer']]);
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_operator === true, 403);

        $this->support->enter($user, Household::query()->findOrFail($data['household_id']));

        return redirect()->to(Dashboard::getUrl(panel: 'admin'));
    }

    public function leave(): RedirectResponse
    {
        $this->support->leave();

        return redirect()->to(Dashboard::getUrl(panel: 'admin'));
    }
}
