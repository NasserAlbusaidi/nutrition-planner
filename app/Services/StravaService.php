<?php
 namespace App\Services;

 use App\Models\User;
 use Illuminate\Support\Facades\Auth;
 use Illuminate\Support\Facades\Crypt;
 use Illuminate\Support\Facades\Http;
 use Illuminate\Support\Facades\Log;
 use Carbon\Carbon; // For handling expiry time

 class StravaService
 {
    protected string $baseUrl = 'https://www.strava.com/api/v3';
    protected string $tokenUrl = 'https://www.strava.com/oauth/token';

     /**
      * Refresh the Strava access token using the refresh token.
      *
      * @param User $user
      * @return bool True on success, false on failure.
      */
     protected function refreshToken(User $user): bool
     {
         if (!$user->strava_refresh_token) {
             Log::warning("Strava refresh token missing for user: {$user->id}");
             return false;
         }

         try {
             // Decrypt the refresh token before using it
             $refreshToken = Crypt::decryptString($user->strava_refresh_token);

             $response = Http::asForm()->post($this->tokenUrl, [
                 'client_id' => config('services.strava.client_id'),
                 'client_secret' => config('services.strava.client_secret'),
                 'grant_type' => 'refresh_token',
                 'refresh_token' => $refreshToken,
             ]);

             if ($response->successful()) {
                 $data = $response->json();

                 // Update user with new tokens (encrypt them!)
                 $user->update([
                     'strava_access_token' => Crypt::encryptString($data['access_token']),
                     'strava_refresh_token' => Crypt::encryptString($data['refresh_token']), // Strava might send a new refresh token
                     'strava_token_expires_at' => Carbon::createFromTimestamp($data['expires_at']),
                 ]);

                 Log::info("Strava token refreshed successfully for user: {$user->id}");
                 return true;
             } else {
                 Log::error("Strava token refresh failed for user: {$user->id}. Status: " . $response->status() . " Body: " . $response->body());
                 // Optional: Clear tokens if refresh fails permanently?
                 // $user->update(['strava_access_token' => null, 'strava_refresh_token' => null, 'strava_token_expires_at' => null, 'strava_user_id' => null]);
                 return false;
             }
         } catch (\Exception $e) {
             Log::error("Exception during Strava token refresh for user: {$user->id}. Error: " . $e->getMessage());
             return false;
         }
     }

     /**
      * Get a valid access token for the user, refreshing if necessary.
      *
      * @param User $user
      * @return string|null The access token or null if unavailable/refresh failed.
      */
     protected function getValidAccessToken(User $user): ?string
     {
          if (!$user->strava_access_token || !$user->strava_token_expires_at) {
             Log::warning("Strava access token or expiry missing for user: {$user->id}");
             return null;
          }

          // Check if token is expired or expires within the next minute (buffer)
          if ($user->strava_token_expires_at->isPast() || $user->strava_token_expires_at->subMinute()->isPast()) {
             Log::info("Strava token expired or expiring soon for user: {$user->id}. Attempting refresh.");
             if (!$this->refreshToken($user)) {
                 return null; // Refresh failed
             }
             // Reload user model to get updated tokens after refresh
             $user->refresh();
          }

          try {
              // Decrypt and return the valid access token
              return Crypt::decryptString($user->strava_access_token);
          } catch (\Exception $e) {
              Log::error("Failed to decrypt Strava access token for user: {$user->id}. Error: " . $e->getMessage());
              return null;
          }
     }

     /**
      * Get the list of routes for the authenticated user.
      *
      * @param User $user
      * @return array|null Array of routes or null on failure.
      */
      public function getUserRoutes(User $user): ?array
     {
         $accessToken = $this->getValidAccessToken($user);

         if (!$accessToken) {
             return null; // Could not get a valid token
         }

         try {
             // Fetch routes (adjust per_page as needed)
             $response = Http::withToken($accessToken)
                 ->get("{$this->baseUrl}/athlete/routes", [
                     'page' => 1,
                     'per_page' => 50 // Fetch up to 50 routes initially
                 ]);

             if ($response->successful()) {
                 // Return only the necessary data, INCLUDING summary_polyline
                 return collect($response->json())->map(function ($route) {
                     // Use null coalescing operator (??) for safety
                     $summaryPolyline = $route['map']['summary_polyline'] ?? null;

                     return [
                         'id' => $route['id_str'], // Use string ID
                         'name' => $route['name'],
                         'distance' => round($route['distance'] / 1000, 2), // Convert meters to km
                         'elevation_gain' => round($route['elevation_gain'], 0), // Round elevation
                         'summary_polyline' => $summaryPolyline, // <<< ADDED THIS LINE
                     ];
                 })->all(); // Convert the collection back to an array
             } else {
                 Log::error("Failed to fetch Strava routes for user: {$user->id}. Status: " . $response->status() . " Body: " . $response->body());
                 // Handle specific errors like 401 Unauthorized even after potential refresh
                 if ($response->status() === 401) {
                      Log::error("Strava API returned 401 Unauthorized for user: {$user->id}. Token might be fully invalid.");
                 }
                 return null;
             }
         } catch (\Exception $e) {
             Log::error("Exception fetching Strava routes for user: {$user->id}. Error: " . $e->getMessage());
             return null;
         }
     }

     // Add getRouteGpx method here later (Step 4.5)
     /**
     * Get the GPX data for a specific route.
     *
     * @param User $user
     * @param string $routeId The string ID of the route.
     * @return string|null The GPX XML content as a string, or null on failure.
     */
    public function getRouteGpx(User $user, string $routeId): ?string
    {
        $accessToken = $this->getValidAccessToken($user);

        if (!$accessToken) {
            return null; // Could not get a valid token
        }

        try {
            $response = Http::withToken($accessToken)
                ->get("{$this->baseUrl}/routes/{$routeId}/export_gpx"); // Use routeId in URL

            if ($response->successful()) {
                // Return the raw body content, which is the GPX XML
                return $response->body();
            } else {
                Log::error("Failed to fetch Strava GPX for route: {$routeId}, user: {$user->id}. Status: " . $response->status() . " Body: " . $response->body());
                 if ($response->status() === 401) {
                     Log::error("Strava API returned 401 Unauthorized fetching GPX for user: {$user->id}. Token might be fully invalid.");
                 } elseif ($response->status() === 404) {
                     Log::error("Strava API returned 404 Not Found fetching GPX for route: {$routeId}, user: {$user->id}. Route might not exist or be private.");
                 }
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Exception fetching Strava GPX for route: {$routeId}, user: {$user->id}. Error: " . $e->getMessage());
            return null;
        }
    }

 }
