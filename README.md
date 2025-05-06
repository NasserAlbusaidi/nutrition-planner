## Description

This web application helps endurance athletes (cyclists, runners, triathletes) plan their nutrition and hydration strategy for specific activities based on:

* **Strava Routes:** Imports user's saved routes from Strava.
* **Planned Effort:** Uses user's FTP and planned intensity (e.g., Endurance, Tempo, Threshold).
* **Personal Profile:** Considers user's weight and qualitative sweat/salt loss perception.
* **Weather Conditions:** Fetches hourly weather forecasts (temperature, humidity) for the route location and planned start time using the Open-Meteo API.
* **Personal Pantry:** Uses a library of the user's preferred nutrition products (including seeded defaults).

The application calculates estimated energy expenditure, carbohydrate needs, and **weather-adjusted** fluid/sodium requirements. It then generates a time-based schedule suggesting specific products from the user's pantry to consume at set intervals throughout the activity.

This project was built to address the challenge of fueling correctly for endurance activities, especially in varying environmental conditions like the heat and humidity often experienced in Muscat, Oman.

## Features (MVP)

* User Registration & Login (Laravel Breeze)
* User Profile Management (Weight, FTP, Sweat/Salt Level)
* Secure Strava Account Connection (OAuth 2.0 via Laravel Socialite)
* Browse and Select Saved Strava Routes
* Input Planned Activity Start Time & Intensity
* Personal Nutrition Product Pantry (Add/Edit/Delete custom items)
* Default Nutrition Products (Seeded common items like bananas, SIS gels)
* Weather Forecast Integration (Open-Meteo API)
* Calculation Engine:
    * Estimates duration and average power.
    * Calculates hourly carb targets based on intensity.
    * Calculates baseline fluid/sodium needs based on profile.
    * Adjusts fluid/sodium targets based on hourly weather forecast.
    * Includes an hourly carbohydrate intake cap based on intensity.
* Plan Generation Algorithm: Creates a time-based schedule using available pantry items.
* Plan Viewer: Displays plan summary and detailed schedule.
* Dashboard: Shows recent plans and quick links.

## Tech Stack

* **Backend:** PHP / Laravel Framework
* **Frontend:** Laravel Blade + Livewire + Alpine.js (via Laravel Breeze)
* **Styling:** Tailwind CSS
* **Database:** MySQL / PostgreSQL (Configurable via Laravel)
* **APIs:**
    * Strava API (OAuth, Routes, GPX Export)
    * Open-Meteo API (Weather Forecast)
* **Key Packages:**
    * `laravel/socialite`
    * `socialiteproviders/strava`
    * `livewire/livewire`
    * `sibyx/phpgpx`

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/NasserAlbusaidi/nutrition-planner.git

    cd nutrition-planner
    ```
2.  **Install PHP Dependencies:**
    ```bash
    composer install
    ```
3.  **Install Node.js Dependencies:**
    ```bash
    npm install
    # or yarn install
    ```
4.  **Environment Setup:**
    * Copy the example environment file: `cp .env.example .env`
    * Generate an application key: `php artisan key:generate`
    * Configure your database connection details (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, etc.) in the `.env` file.
    * Configure Strava API credentials (Get these from [Strava Developers](https://developers.strava.com/)):
        ```dotenv
        STRAVA_CLIENT_ID=your_strava_client_id
        STRAVA_CLIENT_SECRET=your_strava_client_secret
        STRAVA_REDIRECT_URI=${APP_URL}/strava/callback # Ensure APP_URL is set correctly
        ```
    * *(Optional: OpenWeatherMap API Key if you switch back)*
        ```dotenv
        # OPENWEATHERMAP_API_KEY=your_openweathermap_key
        ```
5.  **Database Migration & Seeding:**
    * Create the database specified in your `.env` file.
    * Run migrations: `php artisan migrate`
    * Run seeders (to add default products): `php artisan db:seed`
6.  **Build Frontend Assets:**
    ```bash
    npm run build
    # or yarn build
    ```
7.  **Serve the Application:**
    * Using Artisan: `php artisan serve`
    * Or configure a local web server (Valet, Herd, Laragon, etc.) pointing to the `public` directory.

## Usage

1.  Register for an account or log in.
2.  Navigate to your **Profile** page and fill in your Weight, FTP, and estimated Sweat/Salt levels.
3.  Connect your **Strava** account via the button on the Profile page.
4.  Go to the **Pantry** page to add your specific nutrition products (gels, bars, drinks) or rely on the pre-seeded defaults.
5.  Click **Create New Nutrition Plan** (from the Dashboard or Navbar).
6.  Select a **Strava Route** from your list.
7.  Enter the **Planned Start Date & Time** and the **Planned Intensity**.
8.  Click **Generate Nutrition Plan**.
9.  View the generated plan summary and detailed schedule.

## API Keys & Services

* **Strava:** Requires creating an API application at [Strava Developers](https://developers.strava.com/) to obtain a Client ID and Secret. Configure the callback URL correctly.
* **Open-Meteo:** Used for weather forecasts. Currently does not require an API key for the forecast endpoint used.

## Future Enhancements / TODO

* Improve duration estimation logic (factor in elevation, allow user override).
* Refine the `PlanGenerator` algorithm (better product selection, partial servings, etc.).
* Allow manual activity input (duration/intensity without a Strava route).
* Implement running support (using pace/HR zones).
* Add user settings for carb/fluid/sodium preferences/limits.
* Visualize weather forecast on plan input/view.
* Implement proper testing (Unit, Feature).
* Add ability to edit/delete generated plans.
* Cache Strava routes/API responses.
* Improve UI/UX based on user feedback.

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues.
