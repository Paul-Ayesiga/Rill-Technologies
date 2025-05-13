<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Jenssegers\Agent\Agent;

class SessionController extends Controller
{
    /**
     * Display the user's sessions.
     */
    public function index(Request $request)
    {
        $sessions = collect(DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderBy('last_activity', 'desc')
            ->get());

        $agent = new Agent();
        
        return Inertia::render('admin/settings/Session', [
            'sessions' => $sessions->map(function ($session) use ($agent, $request) {
                $agent->setUserAgent($session->user_agent);
                
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'last_active' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                    'user_agent' => $session->user_agent,
                    'device' => $this->getDeviceName($agent),
                    'is_current_device' => $session->id === $request->session()->getId(),
                ];
            }),
            'current_session_id' => $request->session()->getId(),
        ]);
    }

    /**
     * Destroy the specified session.
     */
    public function destroy(Request $request, string $id)
    {
        if ($id === $request->session()->getId()) {
            // If it's the current session, log the user out
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return redirect()->route('login');
        }
        
        // Otherwise just delete the session
        DB::table('sessions')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();
            
        return back()->with('success', 'Session terminated successfully.');
    }

    /**
     * Log out from all other devices.
     */
    public function logoutOthers(Request $request)
    {
        // Delete all sessions except the current one
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
            
        return back()->with('success', 'All other sessions terminated successfully.');
    }

    /**
     * Get a readable device name from the user agent.
     */
    private function getDeviceName(Agent $agent): string
    {
        if ($agent->isPhone()) {
            $device = $agent->device() ?: 'Mobile';
            $platform = $agent->platform() ?: '';
            return trim("$device $platform");
        }
        
        if ($agent->isTablet()) {
            $device = $agent->device() ?: 'Tablet';
            $platform = $agent->platform() ?: '';
            return trim("$device $platform");
        }
        
        $browser = $agent->browser() ?: 'Unknown Browser';
        $platform = $agent->platform() ?: 'Unknown OS';
        
        return "$browser on $platform";
    }
}