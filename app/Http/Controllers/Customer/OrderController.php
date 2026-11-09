<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['orderItems.component', 'review'])
            ->latest('id')
            ->paginate(10);

        return view('customer.orders.index', [
            'orders' => $orders,
        ]);
    }

    public function markReceived(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        if (! $order->canBeReceived()) {
            return redirect()->route('orders.index')->with('order_error', 'Pesanan ini sudah selesai atau tidak dapat diubah statusnya.');
        }

        $order->update([
            'status' => 'completed',
        ]);

        return redirect()->route('orders.index')->with('order_status', 'Pesanan #'.$order->order_number.' telah Anda terima! Silakan berikan rating dan ulasan Anda.');
    }

    public function storeReview(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        if (! $order->isCompleted()) {
            throw ValidationException::withMessages([
                'comment' => 'Pesanan harus selesai / sudah diterima sebelum dapat memberikan rating dan ulasan.',
            ]);
        }

        if ($order->review()->exists()) {
            throw ValidationException::withMessages([
                'comment' => 'Anda sudah memberikan ulasan untuk pesanan ini.',
            ]);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:5', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ], [
            'rating.required' => 'Silakan pilih rating bintang 1 hingga 5.',
            'comment.min' => 'Ulasan minimal 5 karakter.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imagePath = 'data:'.$imageFile->getMimeType().';base64,'.base64_encode(file_get_contents($imageFile->getRealPath()));
        }

        Review::create([
            'user_id' => $request->user()->id,
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'image_path' => $imagePath,
            'is_featured' => true,
        ]);

        return redirect()->route('orders.index')->with('order_status', 'Terima kasih! Ulasan dan foto kreasi sandwich Anda telah berhasil dikirim.');
    }
}
