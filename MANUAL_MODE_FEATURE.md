# Manual Mode Feature - Custom Zone Rules

## Overview

The Smart Irrigation System now supports **Manual Mode** where users can define custom rules and thresholds for each zone. This allows fine-tuned control over irrigation behavior per zone.

---

## Features

### 1. **Zone Modes**
- **Auto Mode** (default): Uses system-wide default thresholds
- **Manual Mode**: Uses zone-specific custom rules

### 2. **Customizable Rules per Zone**

Each zone can have its own:
- **Moisture Threshold**: When to start irrigation (default: 30%)
- **Moisture Target**: When to stop irrigation (default: 60%)
- **Pump Overload Current**: Emergency shutdown threshold (default: 15A)
- **Flow Leak Multiplier**: Leak detection sensitivity (default: 1.5x)
- **Rule Toggles**: Enable/disable specific rules
- **Irrigation Duration**: Maximum irrigation time (optional)
- **Schedule**: Time-based rules (future feature)

### 3. **Rule Toggles**
- Enable/disable low moisture detection
- Enable/disable pump overload protection
- Enable/disable leak detection
- Enable/disable rain forecast response

---

## API Endpoints

### Get Zone Rules
```http
GET /api/zones/{id}/rules
```

**Response:**
```json
{
  "zone_id": 1,
  "zone_name": "Zone A",
  "mode": "manual",
  "rules": {
    "id": 1,
    "zone_id": 1,
    "moisture_threshold": 25.0,
    "moisture_target": 70.0,
    "pump_overload_current": 18.0,
    "flow_leak_multiplier": 2.0,
    "enable_low_moisture": true,
    "enable_pump_overload": true,
    "enable_leak_detection": true,
    "enable_rain_forecast": true,
    "irrigation_duration_minutes": 30,
    "schedule": null
  }
}
```

### Update Zone Rules
```http
PUT /api/zones/{id}/rules
Content-Type: application/json

{
  "moisture_threshold": 25.0,
  "moisture_target": 70.0,
  "pump_overload_current": 18.0,
  "flow_leak_multiplier": 2.0,
  "enable_low_moisture": true,
  "enable_pump_overload": true,
  "enable_leak_detection": true,
  "enable_rain_forecast": false,
  "irrigation_duration_minutes": 30
}
```

### Reset Zone Rules to Defaults
```http
POST /api/zones/{id}/rules/reset
```

### Update Zone Mode
```http
PUT /api/zones/{id}
Content-Type: application/json

{
  "mode": "manual",
  "manual_notes": "Custom rules for sensitive plants"
}
```

---

## How It Works

### Auto Mode (Default)
- Zone uses system-wide default thresholds
- Rule engine evaluates all zones with same rules
- No customization needed

### Manual Mode
1. **Set zone to manual mode:**
   ```http
   PUT /api/zones/1
   { "mode": "manual" }
   ```

2. **Configure custom rules:**
   ```http
   PUT /api/zones/1/rules
   {
     "moisture_threshold": 25.0,
     "moisture_target": 70.0,
     "pump_overload_current": 18.0
   }
   ```

3. **Rule Engine behavior:**
   - Skips zones in manual mode during evaluation
   - Uses zone-specific thresholds when rules are set
   - Respects rule toggles (can disable specific rules)

---

## Example Use Cases

### Use Case 1: Sensitive Plants
**Zone A** has delicate plants that need more moisture:
```json
{
  "moisture_threshold": 40.0,  // Start irrigation earlier
  "moisture_target": 75.0,      // Keep more moisture
  "enable_leak_detection": true
}
```

### Use Case 2: Drought-Tolerant Plants
**Zone B** has drought-tolerant plants:
```json
{
  "moisture_threshold": 20.0,  // Start irrigation later
  "moisture_target": 50.0,     // Less moisture needed
  "enable_rain_forecast": false // Don't cancel for rain
}
```

### Use Case 3: High-Power Pump
**Zone C** has a more powerful pump:
```json
{
  "pump_overload_current": 20.0,  // Higher threshold
  "flow_leak_multiplier": 2.0,    // More sensitive leak detection
  "irrigation_duration_minutes": 45 // Longer max duration
}
```

### Use Case 4: Disable Specific Rules
**Zone D** wants to disable leak detection:
```json
{
  "enable_leak_detection": false,
  "enable_rain_forecast": false
}
```

---

## Database Schema

### `zones` table (new fields)
- `mode`: `auto` or `manual` (default: `auto`)
- `manual_notes`: Optional notes for manual mode

### `zone_rules` table
- `zone_id` (unique, foreign key)
- `moisture_threshold` (double, default: 30.0)
- `moisture_target` (double, default: 60.0)
- `pump_overload_current` (double, default: 15.0)
- `flow_leak_multiplier` (double, default: 1.5)
- `enable_low_moisture` (boolean, default: true)
- `enable_pump_overload` (boolean, default: true)
- `enable_leak_detection` (boolean, default: true)
- `enable_rain_forecast` (boolean, default: true)
- `irrigation_duration_minutes` (integer, nullable)
- `schedule` (JSON, nullable, for future use)

---

## Rule Engine Logic

### Before (Auto Mode Only)
```php
if ($moisture < 30.0) {
    // Start pump
}
```

### After (Zone-Specific)
```php
$rules = $zone->rules ?? ZoneRule::getDefaults();
if ($moisture < $rules->moisture_threshold) {
    // Start pump
}
```

### Manual Mode Zones
- Rule engine **skips** zones in manual mode
- User has full control via API
- Rules are still stored but not auto-evaluated

---

## Frontend Integration

### Example: Update Zone Rules
```javascript
// Set zone to manual mode
await fetch(`/api/zones/${zoneId}`, {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ mode: 'manual' })
});

// Configure custom rules
await fetch(`/api/zones/${zoneId}/rules`, {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    moisture_threshold: 25.0,
    moisture_target: 70.0,
    pump_overload_current: 18.0
  })
});
```

### Example: Get Zone Rules
```javascript
const response = await fetch(`/api/zones/${zoneId}/rules`);
const data = await response.json();
console.log(data.rules);
```

---

## Migration & Setup

1. **Run migrations:**
   ```bash
   php artisan migrate
   ```

2. **Existing zones:**
   - All existing zones default to `auto` mode
   - No rules created (uses system defaults)
   - Can be switched to manual mode anytime

3. **Create rules for zone:**
   ```bash
   # Via API or directly in database
   PUT /api/zones/1/rules
   {
     "moisture_threshold": 25.0,
     "moisture_target": 70.0
   }
   ```

---

## Benefits

1. **Flexibility**: Different zones can have different requirements
2. **Precision**: Fine-tune thresholds per zone
3. **Control**: Enable/disable specific rules per zone
4. **Safety**: Still respects emergency shutdowns (unless disabled)
5. **Backward Compatible**: Existing zones work as before

---

## Future Enhancements

- **Schedule Rules**: Time-based irrigation schedules
- **Weather Integration**: Zone-specific weather responses
- **Plant Type Presets**: Pre-configured rules for common plant types
- **Rule Templates**: Save and apply rule sets to multiple zones
- **Analytics**: Track which rules work best per zone

---

## Testing

### Test Auto Mode
```bash
# Zone uses defaults
GET /api/zones/1/rules
# Returns defaults if no rules set
```

### Test Manual Mode
```bash
# Set to manual
PUT /api/zones/1
{ "mode": "manual" }

# Set custom rules
PUT /api/zones/1/rules
{
  "moisture_threshold": 25.0,
  "moisture_target": 70.0
}

# Verify
GET /api/zones/1/rules
```

### Test Rule Engine
- Zones in manual mode are skipped during evaluation
- Zones in auto mode use their custom rules if set, otherwise defaults
- Rule toggles work as expected

---

This feature gives users **full control** over irrigation behavior while maintaining **safety defaults** and **backward compatibility**.







