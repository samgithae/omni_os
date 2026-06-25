<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $hash = hash('sha256', $token);

        $agent = Agent::where('token_hash', $hash)->where('is_active', true)->first();

        if ($agent) {
            app()->instance('currentAgent', $agent);
            $request->attributes->set('agent', $agent);
            $agent->touchActivity();

            return $next($request);
        }

        // Backward-compat fallback: check the legacy shared API token
        $expected = config('app.omni_api_token');

        if ($expected && hash_equals($expected, $token)) {
            // Legacy token — no current agent, agent_id stays null
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized.'], 401);
    }
}
