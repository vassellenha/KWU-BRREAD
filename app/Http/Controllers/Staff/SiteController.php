<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReviewPhoto;
use App\Models\SiteSetting;
use App\Rules\PhoneNumber;
use App\Support\ContactInfo;
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
            'contact_whatsapp' => ['nullable', 'string', new PhoneNumber],
            'instagram_handle' => ['required', 'string', 'max:30', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
        ], [
            'instagram_handle.regex' => 'Use only letters, numbers, dots and underscores, like brread.official.',
        ]);

        $validated['instagram_handle'] = ltrim($validated['instagram_handle'], '@');
        SiteSetting::put(array_map(fn ($value) => $value === null ? null : trim($value), $validated), $request->user()->id);

        return back()->with('status', 'Contact details saved. The site shows them right away.');
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
