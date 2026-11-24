<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReviewPhoto;
use App\Models\SiteSetting;
use App\Rules\PhoneNumber;
use App\Rules\PhoneNumberList;
use App\Support\ContactInfo;
use App\Support\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public site, from the superadmin's side: who customers contact (shown on the Contact page, the receipt
 * and Home) and the photos customers added to their reviews, any of which can be taken down.
 */
class SiteController extends Controller
{
    public function index(): View
    {
        return view('staff.site.index', [
            'contact' => ContactInfo::get(),
            'shop' => ShopSettings::get(),
            'stored' => SiteSetting::allValues(),
            'reviews' => Order::query()
                ->whereNotNull('rating')
                ->with('reviewPhoto:id,order_id,width,height,updated_at')
                ->latest('reviewed_at')
                ->limit(60)
                ->get(['id', 'order_number', 'customer_name', 'rating', 'review_text', 'reviewed_at', 'review_hidden_at']),
        ]);
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_name' => ['nullable', 'string', 'max:60'],
            'contact_phone' => ['required', 'string', new PhoneNumber],
            'contact_whatsapp' => ['nullable', 'string', new PhoneNumberList],
            'instagram_handle' => ['required', 'string', 'max:30', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
        ], [
            'instagram_handle.regex' => 'Use only letters, numbers, dots and underscores, like brread.official.',
        ]);

        $validated['instagram_handle'] = ltrim($validated['instagram_handle'], '@');
        SiteSetting::put(array_map(fn ($value) => $value === null ? null : trim($value), $validated), $request->user()->id);

        return back()->with('status', 'Contact details saved. The site shows them right away.');
    }

    /**
     * One section of the shop's settings (see App\Support\ShopSettings): where and when the counter is open,
     * whether orders are taken, and what the site promises.
     */
    public function updateShop(Request $request, string $section): RedirectResponse
    {
        $time = ['required', 'date_format:H:i'];
        $rules = match ($section) {
            'location' => [
                'address_short' => ['required', 'string', 'max:40'],
                'address_lines' => ['required', 'string', 'max:300'],
                'maps_query' => ['required', 'string', 'max:300'],
            ],
            'hours' => [
                'hours_open' => $time,
                'hours_close' => [...$time, 'after:hours_open'],
                'counter_closed' => ['boolean'],
                'counter_closed_note' => ['nullable', 'string', 'max:80', 'required_if_accepted:counter_closed'],
            ],
            'orders' => [
                'orders_open' => ['boolean'],
                'orders_paused_message' => ['nullable', 'string', 'max:160'],
            ],
            'promises' => [
                'delivery_minutes' => ['required', 'integer', 'between:5,240'],
                'frozen_minutes' => ['required', 'integer', 'between:10,600'],
                'promo_text' => ['required', 'string', 'max:160'],
            ],
            default => abort(404),
        };

        $validated = $request->validate($rules, [
            'hours_close.after' => 'Closing time must be after opening time.',
            'counter_closed_note.required_if_accepted' => 'Say why the counter is closed (for example: Closed for Lebaran, back on the 5th).',
        ]);

        // unticked switches are not sent by the browser, so they are saved as off
        foreach (['counter_closed', 'orders_open'] as $switch) {
            if (array_key_exists($switch, $rules)) {
                $validated[$switch] = $request->boolean($switch) ? '1' : '0';
            }
        }

        SiteSetting::put(array_map(fn ($value) => $value === null ? null : trim((string) $value), $validated), $request->user()->id);

        $done = [
            'location' => 'Address saved.',
            'hours' => 'Opening hours saved.',
            'orders' => $validated['orders_open'] ?? '1' ? 'Orders are open.' : 'Orders are paused. Customers see your message and cannot check out.',
            'promises' => 'Delivery promises and promo saved.',
        ][$section];

        return back()->with('status', $done.' The site shows it right away.');
    }

    /** Keeps a review off the public pages, or puts it back. Nothing is deleted. */
    public function toggleReview(Order $order): RedirectResponse
    {
        abort_unless($order->hasReview(), 404);
        $hide = $order->review_hidden_at === null;
        $order->update(['review_hidden_at' => $hide ? now() : null]);

        return back()->with('status', 'Review of order #'.$order->order_number.($hide ? ' is hidden from the site.' : ' shows on the site again.'));
    }

    public function removePhoto(ReviewPhoto $photo): RedirectResponse
    {
        $number = $photo->order?->order_number;
        $photo->delete();

        return back()->with('status', 'Photo taken down'.($number ? ' from order #'.$number : '').'. The review text stays.');
    }
}
