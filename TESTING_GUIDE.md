# Testing Guide - Smart Irrigation Backend

## Quick Test Steps

### 1. Check Database Connection
```bash
php artisan tinker
```
Then run:
```php
\App\Models\Zone::count();
\App\Models\Sensor::count();
\App\Models\Telemetry::count();
```

### 2. Test Simulator (One Cycle)
```bash
php artisan simulate:hardware --interval=1
```
Press `CTRL+C` after a few seconds to stop.

**Expected Output:**
```
Hardware Simulator started (interval: 1s)... Press CTRL+C to stop.
  Sensor 1 (soil_sensor): Moisture = 45.23%
  Sensor 2 (pump): Status = OFF, Current = 0A
  Sensor 3 (flow): Flow = 0 L/min
Cycle completed at 2025-11-26 23:00:00
```

### 3. Test API Endpoints

#### Start the server:
```bash
php artisan serve
```

#### Test in another terminal or browser:

**Get Zones:**
```bash
curl http://localhost:8000/api/zones
```

**Get Sensors:**
```bash
curl http://localhost:8000/api/sensors
```

**Get Latest Telemetry:**
```bash
curl http://localhost:8000/api/telemetry/latest
```

**Get Telemetry History:**
```bash
curl http://localhost:8000/api/telemetry/history
```

**Get Alerts:**
```bash
curl http://localhost:8000/api/alerts
```

### 4. Test Simulator API Endpoint

**Simulate Leak:**
```bash
curl -X POST http://localhost:8000/api/simulate \
  -H "Content-Type: application/json" \
  -d '{"type": "leak", "sensor_id": 3}'
```

**Simulate Overload:**
```bash
curl -X POST http://localhost:8000/api/simulate \
  -H "Content-Type: application/json" \
  -d '{"type": "overload", "sensor_id": 2}'
```

**Simulate Rain:**
```bash
curl -X POST http://localhost:8000/api/simulate \
  -H "Content-Type: application/json" \
  -d '{"type": "rain", "zone_id": 1}'
```

### 5. Test Action Worker

In a separate terminal:
```bash
php artisan actions:process
```

Then create an action manually:
```bash
php artisan tinker
```
```php
\App\Models\Action::create([
    'sensor_id' => 2,
    'action' => 'pump_on',
    'status' => 'pending'
]);
```

The action worker should process it and create telemetry.

### 6. Test Rule Engine

```bash
php artisan rules:evaluate
```

This should evaluate all rules and create actions/alerts if conditions are met.

## Troubleshooting

### Issue: API returns empty array `[]`

**Solution:** 
1. Make sure sensors exist: `\App\Models\Sensor::count()`
2. Run simulator to generate telemetry: `php artisan simulate:hardware`
3. Wait a few seconds for telemetry to be generated

### Issue: Simulator shows "No sensors found"

**Solution:**
1. Create a zone:
```bash
php artisan tinker
```
```php
\App\Models\Zone::create(['name' => 'Zone 1']);
```

2. Create sensors:
```php
$zone = \App\Models\Zone::first();
\App\Models\Sensor::create(['zone_id' => $zone->id, 'type' => 'soil_sensor', 'name' => 'Soil Sensor 1']);
\App\Models\Sensor::create(['zone_id' => $zone->id, 'type' => 'pump', 'name' => 'Pump 1']);
\App\Models\Sensor::create(['zone_id' => $zone->id, 'type' => 'flow', 'name' => 'Flow Sensor 1']);
```

### Issue: API hangs or times out

**Possible causes:**
1. Database connection issue - check `.env` file
2. Missing foreign key constraints - run migrations: `php artisan migrate:fresh`
3. Infinite loop in code - check Laravel logs: `storage/logs/laravel.log`

### Issue: Telemetry not being generated

**Check:**
1. Sensors have valid `type` field (soil_sensor, pump, flow, valve)
2. Sensors have `zone_id` set
3. Database table structure is correct: `php artisan migrate:status`

## Expected API Response Examples

### GET /api/telemetry/latest
```json
[
  {
    "sensor_id": 1,
    "sensor_name": "Soil Sensor 1",
    "sensor_type": "soil_sensor",
    "zone_id": 1,
    "zone_name": "Zone 1",
    "telemetry": {
      "moisture": {
        "value": 45.23,
        "recorded_at": "2025-11-26T23:00:00Z"
      }
    }
  },
  {
    "sensor_id": 2,
    "sensor_name": "Pump 1",
    "sensor_type": "pump",
    "zone_id": 1,
    "zone_name": "Zone 1",
    "telemetry": {
      "current": {
        "value": 0,
        "recorded_at": "2025-11-26T23:00:00Z"
      },
      "pump_status": {
        "value": 0,
        "recorded_at": "2025-11-26T23:00:00Z"
      }
    }
  }
]
```

### GET /api/zones
```json
[
  {
    "id": 1,
    "name": "Zone 1",
    "notes": null,
    "created_at": "2025-11-26T20:00:00Z",
    "updated_at": "2025-11-26T20:00:00Z",
    "sensors": [
      {
        "id": 1,
        "zone_id": 1,
        "type": "soil_sensor",
        "name": "Soil Sensor 1",
        "meta": null
      }
    ]
  }
]
```

## Running Full System Test

1. **Terminal 1 - Start API Server:**
```bash
php artisan serve
```

2. **Terminal 2 - Start Simulator:**
```bash
php artisan simulate:hardware
```

3. **Terminal 3 - Start Action Worker:**
```bash
php artisan actions:process
```

4. **Terminal 4 - Test API:**
```bash
# Wait a few seconds, then:
curl http://localhost:8000/api/telemetry/latest
```

You should see telemetry data being generated and updated in real-time!









