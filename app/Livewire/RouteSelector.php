<?php

namespace App\Livewire;

use App\Services\StravaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
// Removed: Illuminate\Support\Facades\File; // Not used directly here anymore for GPX
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\WithFileUploads; // <-- Added
use phpGPX\phpGPX;           // <-- Added
use Illuminate\Http\UploadedFile; // For type hinting

class RouteSelector extends Component
{
    use WithFileUploads; // <-- Added: Use the Livewire trait for file uploads

    public $routes = [];
    public $isLoading = true;
    public $errorMessage = null;

    // Properties for GPX Upload
    public $gpxFile; // This will hold the uploaded file object
    public $gpxFileName; // To display the filename to the user
    public $gpxProcessingError = null; // To show errors related to GPX processing
    public bool $stravaConnected = false; // New property
    public string $initialActiveTab = 'strava'; // To pass to Alpine


    // Validation rules - add rules for gpxFile
    protected function rules()
    {
        return [
            'gpxFile' => 'nullable|file|mimetypes:application/gpx+xml,application/xml,text/xml|max:5120',
        ];
    }

    public function mount(StravaService $stravaService)
    {$user = Auth::user();
        if ($user && $user->strava_user_id && $user->strava_access_token) {
            $this->stravaConnected = true;
            $this->initialActiveTab = 'strava';
            $this->loadRoutes($stravaService);
        } else {
            $this->stravaConnected = false;
            $this->initialActiveTab = 'gpx'; // Default to GPX tab if Strava not connected
            $this->isLoading = false; // Not loading Strava routes
            $this->errorMessage = 'Strava account not connected. Please connect it via your profile to select Strava routes, or upload a GPX file.';
        }
    }
    public function loadRoutes(StravaService $stravaService)
    {
        if (!$this->stravaConnected) { // Don't try to load if not connected
            $this->isLoading = false;
            $this->routes = [];
            // $this->errorMessage is already set in mount
            $this->dispatch('routes-loaded'); // Still dispatch for map cleanup logic
            return;
        }

        $this->isLoading = true;
        $this->errorMessage = null; // Clear previous non-connection errors
        $user = Auth::user()->fresh();
        $fetchedRoutes = $stravaService->getUserRoutes($user);

        if ($fetchedRoutes === null) {
            $this->errorMessage = 'Could not fetch routes from Strava. Check connection or try again later.';
            $this->routes = [];
        } elseif ((is_object($fetchedRoutes) && $fetchedRoutes->isEmpty()) || (is_array($fetchedRoutes) && empty($fetchedRoutes))) {
            $this->errorMessage = null; // No API error, just no routes
            $this->routes = [];
        } else {
            $this->routes = is_array($fetchedRoutes) ? $fetchedRoutes : $fetchedRoutes->all();
        }

        $this->isLoading = false;
        $this->dispatch('routes-loaded');
        Log::info("Dispatched 'routes-loaded' event after loading Strava routes.");
    }

    /**
     * Livewire hook, automatically called when the $gpxFile property is updated.
     */
    public function updatedGpxFile(UploadedFile $file) // Type hint is good
    {
        $this->validateOnly('gpxFile'); // Validate just the gpxFile property
        $this->gpxFileName = $file->getClientOriginalName();
        $this->gpxProcessingError = null; // Clear any previous GPX processing error
        Log::info('GPX file selected by user.', ['filename' => $this->gpxFileName]);
    }

    /**
     * Processes the uploaded GPX file and redirects to the PlanForm.
     */
    public function processGpxFile()
    {
        $this->validateOnly('gpxFile'); // Validate before processing
        $this->gpxProcessingError = null;

        if (!$this->gpxFile) {
            $this->gpxProcessingError = 'Please select a GPX file to upload.';
            Log::warning('processGpxFile called without a file selected.');
            return;
        }

        // *** GET ORIGINAL FILENAME FOR LOGGING ***
        $originalFilename = $this->gpxFile->getClientOriginalName();
        Log::info('Attempting to process GPX file.', ['filename' => $originalFilename]);

        try {
            // *** START: MISSING FILE READING AND CLEANING LOGIC ***
            $tempFilePath = $this->gpxFile->getRealPath();
            Log::info("Temporary GPX File Path: " . $tempFilePath);

            $gpxFileContent = file_get_contents($tempFilePath);

            if ($gpxFileContent === false) {
                Log::error('Failed to read content from temporary GPX file.', ['path' => $tempFilePath]);
                $this->gpxProcessingError = 'Could not read the uploaded GPX file.';
                return;
            }

            // Log for debugging what's being read
            Log::info("GPX File Content (first 250 chars before clean): " . substr($gpxFileContent, 0, 250));

            $bom = pack('H*','EFBBBF');
            $gpxFileContentClean = preg_replace("/^$bom/", '', $gpxFileContent);

            if (strlen($gpxFileContentClean) < strlen($gpxFileContent)) {
                Log::info("UTF-8 BOM detected and removed from GPX content.");
            }
            if (trim($gpxFileContentClean) === '') {
                 Log::error('GPX file content is empty or whitespace after BOM removal.', ['filename' => $originalFilename]);
                 $this->gpxProcessingError = 'The GPX file appears to be empty.';
                 return;
            }
            Log::info("GPX File Content Cleaned (first 250 chars): " . substr($gpxFileContentClean, 0, 250));
            // *** END: MISSING FILE READING AND CLEANING LOGIC ***

            $gpxParser = new phpGPX();
            $gpx = $gpxParser->parse($gpxFileContentClean); // Now $gpxFileContentClean IS defined

            $routeName = $gpx->metadata->name ?? 'Uploaded GPX Route - ' . Str::limit($originalFilename, 30); // Use $originalFilename

            $totalDistanceMeters = 0;
            $totalElevationGain = 0;
            $startCoords = null;
            $firstTrackPointTime = null;

            if (!empty($gpx->tracks)) {
                foreach ($gpx->tracks as $track) {
                    $totalDistanceMeters += $track->stats->distance;
                    $totalElevationGain += $track->stats->cumulativeElevationGain;
                    if (!$startCoords && !empty($track->segments) && !empty($track->segments[0]->points)) {
                        $firstPoint = $track->segments[0]->points[0];
                        if ($firstPoint->latitude && $firstPoint->longitude) {
                            $startCoords = ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
                            if ($firstPoint->time) $firstTrackPointTime = $firstPoint->time;
                        }
                    }
                }
            } elseif (!empty($gpx->routes)) {
                foreach ($gpx->routes as $gpxRouteData) { // Renamed variable for clarity
                    $totalDistanceMeters += $gpxRouteData->stats->distance;
                    $totalElevationGain += $gpxRouteData->stats->cumulativeElevationGain;
                    if (!$startCoords && !empty($gpxRouteData->points)) {
                        $firstPoint = $gpxRouteData->points[0];
                        if ($firstPoint->latitude && $firstPoint->longitude) {
                            $startCoords = ['latitude' => $firstPoint->latitude, 'longitude' => $firstPoint->longitude];
                            if ($firstPoint->time) $firstTrackPointTime = $firstPoint->time;
                        }
                    }
                }
            }

            if ($totalDistanceMeters <= 0) {
                $this->gpxProcessingError = 'The GPX file seems to contain no valid track/route data (distance is zero or less).';
                Log::warning('GPX Processing: No distance or zero distance.', ['filename' => $originalFilename]);
                return;
            }

            $routeParams = [
                'routeId' => 'gpx-' . Str::uuid()->toString(),
                'routeName' => urlencode($routeName),
                'distance' => round($totalDistanceMeters / 1000, 2),
                'elevation' => round($totalElevationGain),
                'source' => 'gpx',
            ];

            if ($startCoords) {
                $routeParams['startLat'] = $startCoords['latitude'];
                $routeParams['startLng'] = $startCoords['longitude'];
            } else {
                Log::warning('GPX Processing: Start coordinates not found.', ['filename' => $originalFilename]);
            }

            Log::info("GPX file processed. Redirecting to plan creation form.", $routeParams);
            $this->gpxFile = null;
            $this->gpxFileName = null;
            return redirect()->route('plans.create.form', $routeParams);

        } catch (\Throwable $e) { // Catch Throwable for wider error capture
            Log::error('Error processing GPX file: ' . $e->getMessage(), [
                'filename' => $originalFilename, // Use the stored original filename
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
            ]);

            if (str_contains(strtolower($e->getMessage()), 'xml') || str_contains(strtolower($e->getMessage()), 'parse error')) {
                 $this->gpxProcessingError = 'The GPX file could not be parsed. It might be corrupted or not a valid GPX/XML format. Please verify the file.';
            } else {
                $this->gpxProcessingError = 'An unexpected error occurred while processing the GPX file. ' . Str::limit($e->getMessage(), 80);
            }
            // Do NOT reset gpxFile or gpxFileName here so user can see what they tried to upload
            return;
        }
    }
    /**
     * Triggered by the "Select" button for a Strava route.
     */
    public function confirmSelection(string $routeId) // routeId is from Strava
    {
        Log::info("Attempting to confirm selection for Strava Route ID: " . $routeId);
        $selectedRoute = collect($this->routes)->firstWhere('id', $routeId); // Assuming Strava 'id' is unique

        if ($selectedRoute) {
            Log::debug("Strava route found:", $selectedRoute);

            // Assuming distance from Strava API is in meters
            $distanceInKm = ($selectedRoute['distance'] ?? 0);
            $routeParams = [
                'routeId' => (string) $routeId, // Ensure it's a string
                'routeName' => urlencode($selectedRoute['name']),
                'distance' => round($distanceInKm, 2),
                'elevation' => round($selectedRoute['elevation_gain'] ?? 0),
                'source' => 'strava', // Add source parameter
            ];

            Log::info("Redirecting to plans.create.form (Strava route) with params:", $routeParams);
            return redirect()->route('plans.create.form', $routeParams);

        }

        Log::error("Strava Route ID {$routeId} NOT FOUND in current routes list during confirmation.");
        session()->flash('error', 'Selected Strava route could not be found. Please refresh and try again.');
    }

    public function render()
    {
        return view('livewire.route-selector')
                ->layout('layouts.app');
    }
}
