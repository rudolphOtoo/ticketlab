<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single post-login landing point: staff are routed to their own surface, attendees see their passes.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            abort(401);
        }

        $staffHome = $user->staffHomeUrl();

        if ($staffHome !== null) {
            return redirect()->to($staffHome);
        }

        $orders = Order::query()
            ->where(static function ($query) use ($user): void {
                $query->where('user_id', $user->id);

                // Guest checkouts are matched by email only once the address is proven to belong to this account.
                if ($user->hasVerifiedEmail()) {
                    $query->orWhere('customer_email', $user->email);
                }
            })
            ->withCount('attendees')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('dashboard', ['user' => $user, 'orders' => $orders]);
    }
}
