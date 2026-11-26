<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DownloadQuotationController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->validate(['estimate' => ['nullable', 'string', 'max:100']])['estimate'] ?? '';

        return view('admin.estimate-downloads', ['search' => $search,
            'estimates' => Quotation::query()->where('status', '!=', 'draft')->with('shop:id,name')
                ->when($search !== '', fn ($query) => $query->where('quotation_number', 'like', '%'.$search.'%'))
                ->latest('id')->paginate(20)->withQueryString()]);
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Quotation $quotation): Response
    {
        $user = $request->user();
        abort_unless(match ($user->role) {
            'admin' => true,
            'customer' => $quotation->customer_id === $user->id,
            default => $quotation->shop_id === $user->managedShop()?->id,
        }, 404);
        abort_if($quotation->status === 'draft', 404);

        $quotation->load(['customer', 'shop.barangay', 'vehicle', 'items',
            'appointment.service', 'appointment.bookedServices.service']);
        $status = $quotation->documentStatus();

        return Pdf::loadView('components.quotation-document', compact('quotation', 'status'))
            ->setPaper('a4')->download("estimate-{$quotation->id}.pdf")
            ->header('Cache-Control', 'private, no-store');
    }
}
