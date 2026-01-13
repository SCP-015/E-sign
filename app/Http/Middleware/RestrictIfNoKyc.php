<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictIfNoKyc
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check if KYC is verified
        $isKycVerified = strtolower($user->kyc_status) === 'verified';
        
        // Check if signature is setup
        $hasSignature = $user->signatures()->exists();
        
        // Check if certificate exists (optional but good for e-sign)
        $hasCertificate = $user->certificate()->exists();

        // Allow signature setup routes even if signature is missing
        if ($request->is('api/signatures*') && $request->isMethod('post')) {
            if (!$isKycVerified) {
                return response()->json([
                    'message' => 'Please complete your KYC first.',
                    'requires_kyc' => true,
                ], 403);
            }
            return $next($request);
        }

        if (!$isKycVerified || !$hasSignature) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Please complete your KYC and setup your signature first.',
                    'requires_kyc' => !$isKycVerified,
                    'requires_signature' => !$hasSignature,
                    'requires_certificate' => !$hasCertificate,
                ], 403);
            }

            // For web requests, we might redirect to a KYC setup page
            // But since this is likely an API-driven app, JSON response is primary
        }

        return $next($request);
    }
}
