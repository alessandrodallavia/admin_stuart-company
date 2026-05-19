<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Lead;

class LeadController extends Controller
{
    private function generateUniqueCode()
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Lead::where('uuid', $code)->exists());

        return $code;
    }

    public function storeClick(Request $request)
    {
        $existingUuid = $request->input('uuid');

        if ($existingUuid && Lead::where('uuid', $existingUuid)->exists()) {
            return response()->json([
                'uuid' => $existingUuid,
                'reused' => true,
            ]);
        }

        $uuid = $this->generateUniqueCode();

        $userAgent = $request->userAgent();

        $device = 'desktop';

        if (preg_match('/mobile/i', $userAgent)) {
            $device = 'mobile';
        } elseif (preg_match('/tablet/i', $userAgent)) {
            $device = 'tablet';
        }

        Lead::create([
            'uuid' => $uuid,
            'status' => 'pre',

            'utm_source' => session('utm_source'),
            'utm_medium' => session('utm_medium'),
            'utm_campaign' => session('utm_campaign'),
            'utm_term' => session('utm_term'),
            'utm_content' => session('utm_content'),

            'gclid' => session('gclid'),
            'fbclid' => session('fbclid'),

            'landing_page' => $request->input('page'),
            'entry_page' => url()->current(),
            'referrer' => $request->headers->get('referer'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device' => $device
        ]);

        return response()->json([
            'uuid' => $uuid,
            'reused' => false,
        ]);
    }
}
