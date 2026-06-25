<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentDocument;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $agents = Agent::ordered()
            ->with('documents')
            ->withCount(['activityEvents as actions_this_week' => function ($query) {
                $query->where('created_at', '>=', now()->subWeek());
            }])
            ->get()
            ->map(function (Agent $agent) {
                $recentEvents = $agent->activityEvents()
                    ->with('brand')
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(fn ($event) => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'event_type' => $event->event_type,
                        'severity' => $event->severity,
                        'brand' => $event->brand ? [
                            'name' => $event->brand->name,
                            'slug' => $event->brand->slug,
                            'color' => $event->brand->color,
                        ] : null,
                        'created_at' => $event->created_at->toIso8601String(),
                        'relative_time' => $event->created_at->diffForHumans(),
                    ]);

                return [
                    'id' => $agent->id,
                    'codename' => $agent->codename,
                    'display_name' => $agent->display_name,
                    'role' => $agent->role,
                    'description' => $agent->description,
                    'avatar_url' => $agent->avatar_url,
                    'function_area' => $agent->function_area,
                    'status' => $agent->status,
                    'is_active' => $agent->is_active,
                    'last_active_at' => $agent->last_active_at?->diffForHumans(),
                    'actions_this_week' => (int) $agent->actions_this_week,
                    'recent_events' => $recentEvents,
                    'documents' => $agent->documents->map(fn ($doc) => [
                        'id' => $doc->id,
                        'label' => $doc->label,
                        'url' => $doc->url,
                        'mime_type' => $doc->mime_type,
                        'size_bytes' => $doc->size_bytes,
                    ]),
                ];
            });

        return Inertia::render('Agents/Index', [
            'agents' => $agents,
        ]);
    }

    public function create()
    {
        return Inertia::render('Agents/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codename' => ['required', 'string', 'max:100', 'unique:agents,codename'],
            'display_name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'function_area' => ['nullable', 'string', 'in:orchestration,mining,enrichment,drafting,triage,research'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,paused,maintenance'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'default:0'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $image = @imagecreatefromstring(file_get_contents($file->getRealPath() ?: $file->path()));
            if ($image !== false) {
                $origW = imagesx($image);
                $origH = imagesy($image);
                $maxSize = 256;
                if ($origW > $maxSize || $origH > $maxSize) {
                    $ratio = min($maxSize / $origW, $maxSize / $origH);
                    $newW = (int) round($origW * $ratio);
                    $newH = (int) round($origH * $ratio);
                    $resized = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    $tempPath = sys_get_temp_dir() . '/' . uniqid('avatar_') . '.jpg';
                    imagejpeg($resized, $tempPath, 80);
                    imagedestroy($resized);
                    imagedestroy($image);
                    $validated['avatar_path'] = Storage::disk('public')
                        ->putFile('agents/avatars', new \Illuminate\Http\File($tempPath));
                    @unlink($tempPath);
                } else {
                    $validated['avatar_path'] = $file->store('agents/avatars', 'public');
                }
            } else {
                $validated['avatar_path'] = $file->store('agents/avatars', 'public');
            }
        }

        $agent = Agent::create($validated);

        return redirect()->route('agents.index')
            ->with('success', 'Agent created successfully.');
    }

    public function edit(Agent $agent)
    {
        $agent->load('documents');

        return Inertia::render('Agents/Edit', [
            'agent' => [
                'id' => $agent->id,
                'codename' => $agent->codename,
                'display_name' => $agent->display_name,
                'role' => $agent->role,
                'description' => $agent->description,
                'avatar_url' => $agent->avatar_url,
                'function_area' => $agent->function_area,
                'status' => $agent->status,
                'is_active' => $agent->is_active,
                'sort_order' => $agent->sort_order,
                'token_last_four' => $agent->token_last_four,
                'last_active_at' => $agent->last_active_at?->diffForHumans(),
                'actions_this_week' => $agent->actionsThisWeek(),
                'documents' => $agent->documents->map(fn ($doc) => [
                    'id' => $doc->id,
                    'label' => $doc->label,
                    'file_path' => $doc->file_path,
                    'url' => $doc->url,
                    'mime_type' => $doc->mime_type,
                    'size_bytes' => $doc->size_bytes,
                ]),
            ],
        ]);
    }

    public function update(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'codename' => ['required', 'string', 'max:100', 'unique:agents,codename,'.$agent->id],
            'display_name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'function_area' => ['nullable', 'string', 'in:orchestration,mining,enrichment,drafting,triage,research'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,paused,maintenance'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar
            if ($agent->avatar_path) {
                Storage::disk('public')->delete($agent->avatar_path);
            }

            // Server-side resize with GD (safety net)
            $file = $request->file('avatar');
            $image = imagecreatefromstring(file_get_contents($file->getRealPath() ?: $file->path()));
            if ($image !== false) {
                $origW = imagesx($image);
                $origH = imagesy($image);
                $maxSize = 256;
                if ($origW > $maxSize || $origH > $maxSize) {
                    $ratio = min($maxSize / $origW, $maxSize / $origH);
                    $newW = (int) round($origW * $ratio);
                    $newH = (int) round($origH * $ratio);
                    $resized = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    $tempPath = sys_get_temp_dir() . '/' . uniqid('avatar_') . '.jpg';
                    imagejpeg($resized, $tempPath, 80);
                    imagedestroy($resized);
                    imagedestroy($image);
                    $validated['avatar_path'] = Storage::disk('public')
                        ->putFile('agents/avatars', new \Illuminate\Http\File($tempPath));
                    @unlink($tempPath);
                } else {
                    $validated['avatar_path'] = $file->store('agents/avatars', 'public');
                    imagedestroy($image);
                }
            } else {
                $validated['avatar_path'] = $file->store('agents/avatars', 'public');
            }
        }

        $agent->update($validated);

        return redirect()->route('agents.index')
            ->with('success', 'Agent updated successfully.');
    }

    public function destroy(Agent $agent)
    {
        // Delete avatar
        if ($agent->avatar_path) {
            Storage::disk('public')->delete($agent->avatar_path);
        }

        // Documents are deleted by cascade on the FK
        $agent->delete();

        return redirect()->route('agents.index')
            ->with('success', 'Agent deleted.');
    }

    public function generateToken(Agent $agent)
    {
        $plain = $agent->generateToken();

        // Log token generation to activity feed
        app(ActivityLogger::class)->log([
            'agent_id' => $agent->id,
            'source' => 'laravel.agent-controller',
            'event_type' => 'system',
            'title' => "Token generated for {$agent->display_name}",
            'body' => "A new API bearer token was generated for {$agent->display_name} ({$agent->codename}). The previous token is invalidated.",
            'severity' => 'info',
        ]);

        return response()->json([
            'token' => $plain,
            'token_last_four' => $agent->token_last_four,
        ]);
    }

    // Document management
    public function uploadDocument(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'], // 10MB max
        ]);

        $file = $request->file('file');
        $path = $file->store('agents/documents', 'public');

        $doc = $agent->documents()->create([
            'label' => $validated['label'],
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return response()->json([
            'id' => $doc->id,
            'label' => $doc->label,
            'url' => $doc->url,
            'mime_type' => $doc->mime_type,
            'size_bytes' => $doc->size_bytes,
        ], 201);
    }

    public function deleteDocument(Agent $agent, AgentDocument $document)
    {
        if ($document->agent_id !== $agent->id) {
            abort(404);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}
