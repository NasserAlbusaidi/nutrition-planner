<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt; // For encrypting tokens
use Illuminate\Support\Facades\Log;    // For logging errors
use Laravel\Socialite\Facades\Socialite;

class StravaController extends Controller
{
    /**
     * Redirect the user to the Strava authentication page.
     */
    public function redirectToStrava()
    {
        // Define the necessary scopes
        $scopes = [
            'read',           // Read public profile data
            'read_all',       // Read private profile data
            'activity:read',  // Read public activities
            'activity:read_all' // Read private activities (needed for routes)
        ];

        return Socialite::driver('strava')
            ->scopes($scopes)
            ->redirect();
    }

    /**
     * Obtain the user information from Strava.
     */
    public function handleStravaCallback(Request $request)
    {
        try {
            $stravaUser = Socialite::driver('strava')->user();

            $user = Auth::user(); // Get the authenticated Laravel user

            // Update the user model with Strava details
            // IMPORTANT: Encrypt tokens before storing!
            $user->update([
                'strava_user_id' => $stravaUser->getId(),
                // 'name' => $user->name ?? $stravaUser->getName(), // Optional: update name if empty
                'strava_access_token' => Crypt::encryptString($stravaUser->token),
                'strava_refresh_token' => Crypt::encryptString($stravaUser->refreshToken),
                'strava_token_expires_at' => now()->addSeconds($stravaUser->expiresIn),
            ]);

            return redirect()->route('profile.edit')->with('message', 'Strava account connected successfully!');

        } catch (\Exception $e) {
            Log::error('Strava Callback Error: ' . $e->getMessage());
            return redirect()->route('profile.edit')->with('error', 'Failed to connect Strava account. Please try again.');
        }
    }

    /**
     * Disconnect the user's Strava account.
     */
    public function disconnectStrava(Request $request)
    {
        $user = Auth::user();
        $user->update([
            'strava_user_id' => null,
            'strava_access_token' => null,
            'strava_refresh_token' => null,
            'strava_token_expires_at' => null,
        ]);

        // Optional: Make an API call to Strava to deauthorize the app
        // Requires making an HTTP request using the stored access token
        // Endpoint: https://www.strava.com/oauth/deauthorize
        // try {
        //     // Decrypt token first if encrypted
        //     // Http::withToken(Crypt::decryptString($user->strava_access_token_before_clearing))
        //     //     ->post('https://www.strava.com/oauth/deauthorize');
        // } catch (\Exception $e) {
        //     Log::error('Strava Deauthorization Error: ' . $e->getMessage());
        //     // Don't necessarily stop the user from disconnecting locally
        // }


        return redirect()->route('profile.edit')->with('message', 'Strava account disconnected.');
    }
}
