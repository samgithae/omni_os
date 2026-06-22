<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\EmailMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailMessagesController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailMessage::with(['lead:id,company_name,email,segment', 'brand:id,name,slug']);

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'ilike', "%{$s}%")
                  ->orWhereHas('lead', fn($q) => $q->where('company_name', 'ilike', "%{$s}%"));
            });
        }

        $messages = $query->orderByDesc('id')->paginate(25);

        return Inertia::render('EmailMessages/Index', [
            'messages' => $messages,
            'brands' => Brand::where('is_active', true)->get(['id', 'name', 'slug']),
            'filters' => $request->only(['brand_id', 'approval_status', 'status', 'search']),
        ]);
    }

    public function show(EmailMessage $emailMessage)
    {
        $emailMessage->load(['lead:id,company_name,email,segment', 'brand:id,name,slug,color']);

        return Inertia::render('EmailMessages/Show', [
            'message' => $emailMessage,
        ]);
    }
}
