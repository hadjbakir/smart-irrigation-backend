# Smart Irrigation System - Backend

> An intelligent, automated irrigation management system with weather integration, real-time monitoring, and predictive maintenance.

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## 📋 Table of Contents

- [Features](#-features)
- [Prerequisites](#-prerequisites)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Database Setup](#-database-setup)
- [Running the System](#-running-the-system)
- [API Documentation](#-api-documentation)
- [Testing](#-testing)
- [Project Structure](#-project-structure)
- [Troubleshooting](#-troubleshooting)
- [Contributing](#-contributing)

---

## ✨ Features

- 🤖 **Automated Rule Engine** - Intelligent irrigation decisions based on soil moisture, weather, and system health
- 🌦️ **Weather Integration** - Real-time weather data from OpenWeatherMap with automatic rain detection
- 📊 **Real-Time Monitoring** - Live telemetry data from sensors (moisture, pumps, valves, flow, pressure)
- 🚨 **Alert System** - Multi-level alerts (info, warning, critical) for system events
- 🔧 **Manual Mode** - Per-zone custom rules and thresholds
- 📈 **Historical Analytics** - Telemetry history with charts and statistics
- ⚡ **Action Execution** - Background worker for processing irrigation commands
- 🧪 **Testing Tools** - Built-in endpoints for error case simulation

---

## 📦 Prerequisites

Before you begin, ensure you have the following installed:

- **PHP** >= 8.2
- **Composer** (PHP package manager)
- **MySQL** or **MariaDB** (database)
- **Git** (for cloning)
- **OpenWeatherMap API Key** (optional, for weather features)

### Check Your Installation

```bash
php -v          # Should show PHP 8.2 or higher
composer -V      # Should show Composer version
mysql --version  # Should show MySQL/MariaDB version
```

---

## 🚀 Installation

### Step 1: Clone the Repository

```bash
git clone https://github.com/YOUR_USERNAME/smart-irrigation-backend.git
cd smart-irrigation-backend
```

### Step 2: Install Dependencies

```bash
composer install
```

This will install all PHP dependencies defined in `composer.json`.

### Step 3: Copy Environment File

```bash
cp .env.example .env
```

### Step 4: Generate Application Key

```bash
php artisan key:generate
```

This creates a unique encryption key for your application.

---



## 🗄️ Database Setup

### Step 1: Run Migrations

This creates all necessary database tables:

```bash
php artisan migrate
```

**Expected output:**
```
Running migrations...
2025_11_24_191535_create_zones_table ................... DONE
2025_11_25_221851_create_sensors_table ................. DONE
2025_11_26_190901_create_telemetry_table .............. DONE
...
```

### Step 2: Seed Sample Data (Optional)

This creates sample zones and sensors for testing:

```bash
php artisan db:seed
```

**What gets created:**
- 2 sample zones (Zone 1, Zone 2)
- Multiple sensors per zone (soil sensors, pumps, valves, flow sensors)
- Ready-to-use test data

---

## 🏃 Running the System

The system consists of multiple components that run simultaneously. You'll need **4 terminal windows**.

### Terminal 1: Start API Server

```bash
php artisan serve
```

**Output:**
```
INFO  Server running on [http://127.0.0.1:8000]
```

**Keep this running!** This is your API server.

### Terminal 2: Start Hardware Simulator

This simulates sensor data generation:

```bash
php artisan simulate:hardware --interval=2
```

**Options:**
- `--interval=2` - Update every 2 seconds (default: 3)
- Press `CTRL+C` to stop

**What it does:**
- Generates realistic sensor data (moisture, pump current, flow rate)
- Updates every 2 seconds
- Automatically triggers rule engine evaluation

### Terminal 3: Start Action Worker

This processes irrigation commands:

```bash
php artisan actions:process
```

**What it does:**
- Processes pending actions (pump on/off, valve open/close)
- Verifies action execution
- Retries failed actions

### Terminal 4: 

For weather:

```bash
php artisan weather:check --lat=36.8065 --lon=10.1815
```



---

## 📡 API Documentation

### Base URL

```
http://localhost:8000/api
```

### Available Endpoints

#### Zones

```bash
# Get all zones
GET /api/zones

# Get zone details
GET /api/zones/{id}

# Create zone
POST /api/zones
Body: {"name": "Zone 1", "notes": "North field"}

# Update zone
PUT /api/zones/{id}
Body: {"name": "Updated Name"}

# Delete zone
DELETE /api/zones/{id}
```

#### Sensors

```bash
# Get all sensors
GET /api/sensors

# Get sensor details
GET /api/sensors/{id}

# Create sensor
POST /api/sensors
Body: {
  "zone_id": 1,
  "type": "soil_sensor",
  "name": "Soil Sensor 1",
  "meta": {}
}

# Update sensor
PUT /api/sensors/{id}

# Delete sensor
DELETE /api/sensors/{id}
```

#### Telemetry

```bash
# Get latest telemetry for all sensors
GET /api/telemetry/latest

# Get telemetry history
GET /api/telemetry/history?sensor_id=1&metric=moisture&from=2025-01-01&to=2025-01-31

# Get telemetry statistics
GET /api/telemetry/statistics?sensor_id=1&metric=moisture
```

#### Zone Rules (Manual Mode)

```bash
# Get zone rules
GET /api/zones/{id}/rules

# Update zone rules
PUT /api/zones/{id}/rules
Body: {
  "moisture_threshold": 30.0,
  "moisture_target": 60.0,
  "enable_low_moisture": true,
  ...
}

# Reset to defaults
POST /api/zones/{id}/rules/reset
```

#### Weather

```bash
# Get current weather
GET /api/weather/current?lat=36.8065&lon=10.1815

# Get weather forecast
GET /api/weather/forecast?lat=36.8065&lon=10.1815&days=5

# Check weather and create alerts
POST /api/weather/check
Body: {"lat": 36.8065, "lon": 10.1815}

# Get weather history
GET /api/weather/history?lat=36.8065&lon=10.1815
```

#### Alerts

```bash
# Get all alerts
GET /api/alerts?handled=false

# Get alert details
GET /api/alerts/{id}

# Mark alert as handled
PUT /api/alerts/{id}/handle
```

#### Control

```bash
# Control pump manually
POST /api/pump/{sensor_id}/on
POST /api/pump/{sensor_id}/off

# Simulate anomalies (for testing)
POST /api/simulate
Body: {
  "type": "leak",
  "sensor_id": 3
}
```

#### Test Endpoints

```bash
# Test error cases
POST /api/test/pump-max-runtime
Body: {"zone_id": 1}

POST /api/test/valve-max-runtime
POST /api/test/action-execution-failed
POST /api/test/pressure-leak
POST /api/test/pressure-blockage
POST /api/test/low-battery
POST /api/test/stuck-sensor
POST /api/test/pump-overload
POST /api/test/leak-detection
```

### Testing with cURL

```bash
# Get all zones
curl http://localhost:8000/api/zones

# Get latest telemetry
curl http://localhost:8000/api/telemetry/latest

# Get alerts
curl http://localhost:8000/api/alerts

# Simulate leak
curl -X POST http://localhost:8000/api/simulate \
  -H "Content-Type: application/json" \
  -d '{"type": "leak", "sensor_id": 3}'
```

### Testing with Browser

Open in your browser:
- `http://localhost:8000/api/zones`
- `http://localhost:8000/api/telemetry/latest`
- `http://localhost:8000/api/alerts`

---

## 🧪 Testing

### Quick System Test

1. **Start all services** (4 terminals as described above)
2. **Wait 10-15 seconds** for data to generate
3. **Check API endpoints:**

```bash
# Should return zones
curl http://localhost:8000/api/zones

# Should return telemetry data
curl http://localhost:8000/api/telemetry/latest

# Should return alerts (if any)
curl http://localhost:8000/api/alerts
```

### Test Scenarios

#### Test 1: Low Moisture Detection

1. Wait for soil moisture to drop below 30%
2. Check alerts: Should see "Low moisture detected" alert
3. Check actions: Should see "pump_on" action created
4. Wait for action worker to process
5. Check telemetry: Pump should turn ON

#### Test 2: Rain Detection

```bash
curl -X POST http://localhost:8000/api/weather/check \
  -H "Content-Type: application/json" \
  -d '{"lat": 36.8065, "lon": 10.1815}'
```

Check alerts: Should see "Rain detected" alert

#### Test 3: Leak Detection

```bash
curl -X POST http://localhost:8000/api/test/leak-detection \
  -H "Content-Type: application/json" \
  -d '{"zone_id": 1}'
```

Check alerts: Should see critical "Leak detected" alert

#### Test 4: Pump Overload

```bash
curl -X POST http://localhost:8000/api/test/pump-overload \
  -H "Content-Type: application/json" \
  -d '{"zone_id": 1}'
```

Check alerts: Should see critical "Pump overload" alert

---

## 📁 Project Structure

```
smart-irrigation-backend/
├── app/
│   ├── Console/
│   │   └── Commands/          # Artisan commands
│   │       ├── SensorSimulator.php
│   │       ├── ProcessActions.php
│   │       ├── EvaluateRules.php
│   │       └── CheckWeather.php
│   ├── Http/
│   │   ├── Controllers/       # API controllers
│   │   └── Middleware/        # CORS middleware
│   ├── Models/                # Eloquent models
│   │   ├── Zone.php
│   │   ├── Sensor.php
│   │   ├── Telemetry.php
│   │   ├── Action.php
│   │   ├── Alert.php
│   │   └── Weather.php
│   └── Services/              # Business logic
│       ├── RuleEngine.php
│       └── WeatherService.php
├── database/
│   ├── migrations/            # Database migrations
│   └── seeders/              # Database seeders
├── routes/
│   └── api.php               # API routes
├── config/
│   └── services.php          # Service configurations
├── .env                      # Environment variables (not in git)
├── .env.example              # Example environment file
└── README.md                 # This file
```

---

## 🔧 Troubleshooting

### Problem: "Class not found" errors

**Solution:**
```bash
composer dump-autoload
```

### Problem: Database connection error

**Check:**
1. MySQL is running: `mysql -u root -p`
2. Database exists: `SHOW DATABASES;`
3. `.env` file has correct credentials
4. Run: `php artisan config:clear`

### Problem: No telemetry data

**Check:**
1. Simulator is running: `php artisan simulate:hardware`
2. Database is seeded: `php artisan db:seed`
3. Sensors exist: `php artisan tinker` → `\App\Models\Sensor::count()`

### Problem: API returns empty array

**Solution:**
1. Wait 10-15 seconds for data to generate
2. Check simulator is running
3. Verify database has data

### Problem: Weather API not working

**Check:**
1. API key is set in `.env`
2. API key is valid (test at openweathermap.org)
3. Check logs: `storage/logs/laravel.log`

### Problem: Actions not executing

**Check:**
1. Action worker is running: `php artisan actions:process`
2. Actions exist: Check `actions` table in database
3. Check logs for errors

### Problem: CORS errors (frontend)

**Solution:**
CORS middleware is already configured. If issues persist:
1. Check `app/Http/Middleware/Cors.php`
2. Verify frontend URL is allowed
3. Clear cache: `php artisan config:clear`

---

## 📚 Additional Documentation

- [Backend Architecture](BACKEND_ARCHITECTURE.md) - Detailed system architecture
- [System Logic Summary](SYSTEM_LOGIC_SUMMARY.md) - How the system works
- [Investor Presentation](INVESTOR_PRESENTATION.md) - Business overview
- [Quick Overview](QUICK_OVERVIEW.md) - Quick reference guide
- [Testing Guide](TESTING_GUIDE.md) - Detailed testing instructions

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 🙏 Acknowledgments

- Built with [Laravel](https://laravel.com)
- Weather data from [OpenWeatherMap](https://openweathermap.org)
- Frontend built with [Next.js](https://nextjs.org)

---

## 📞 Support

For questions or issues:
- Open an issue on GitHub
- Check the documentation files
- Review the troubleshooting section

---

**Happy Coding! 🌱💧**
