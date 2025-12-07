# Smart Irrigation System - Logical Flow & Architecture Summary

## 🎯 System Overview

The Smart Irrigation System is an **automated, rule-based irrigation management platform** that monitors soil conditions, controls pumps/valves, and responds to anomalies in real-time.

---

## 📊 System Architecture Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    SYSTEM COMPONENTS                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐      ┌──────────────┐      ┌──────────────┐ │
│  │  Simulator  │ ───> │   Database   │ <─── │  Rule Engine │ │
│  │  (Hardware  │      │  (Telemetry, │      │ (Automation) │ │
│  │   Emulator) │      │   Actions,   │      │              │ │
│  │             │      │   Alerts)    │      │              │ │
│  └──────────────┘      └──────────────┘      └──────────────┘ │
│         │                     │                     │          │
│         │                     │                     │          │
│         └────────────────────┼─────────────────────┘          │
│                               │                                 │
│                    ┌──────────▼──────────┐                      │
│                    │   Action Worker     │                      │
│                    │  (Executes Commands)│                      │
│                    └──────────┬──────────┘                      │
│                               │                                 │
│                    ┌──────────▼──────────┐                      │
│                    │   REST API          │                      │
│                    │   (Laravel Backend) │                      │
│                    └──────────┬──────────┘                      │
│                               │                                 │
│                    ┌──────────▼──────────┐                      │
│                    │   Next.js Frontend  │                      │
│                    │   (Dashboard UI)    │                      │
│                    └─────────────────────┘                      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Core Data Flow

### 1. **Telemetry Generation Cycle**

```
Simulator (Every 2-3 seconds)
    │
    ├─> Reads all sensors from database
    │
    ├─> For each sensor, generates telemetry based on type:
    │   • Soil Sensor → Moisture value (0-100%)
    │   • Pump Sensor → Current reading (Amperes)
    │   • Flow Sensor → Flow rate (L/min)
    │   • Valve Sensor → Status (OPEN/CLOSED)
    │
    └─> Saves telemetry records to database
        │
        └─> Triggers Rule Engine evaluation
```

**Logic:**
- Moisture increases when pump is ON (irrigation active)
- Moisture decreases when pump is OFF (evaporation)
- Pump current = 0 when OFF, 5-12A when ON (with occasional overloads)
- Flow rate = 0 when pump OFF, 8-12 L/min when ON (with leak detection)

---

### 2. **Rule Engine Evaluation Cycle**

```
Rule Engine (Triggered after each telemetry update)
    │
    ├─> Rule 1: Low Moisture Detection
    │   │
    │   ├─> Check: Is soil moisture < 30%?
    │   │
    │   ├─> YES → Create "pump_on" action
    │   │         Create "warning" alert
    │   │
    │   └─> NO → Continue monitoring
    │
    ├─> Rule 2: Pump Overload Detection
    │   │
    │   ├─> Check: Is pump current > 15A?
    │   │
    │   ├─> YES → Create "pump_off" action (EMERGENCY)
    │   │         Create "critical" alert
    │   │
    │   └─> NO → Continue monitoring
    │
    ├─> Rule 3: Leak Detection
    │   │
    │   ├─> Check: Is flow rate > 1.5x expected (pump ON)?
    │   │
    │   ├─> YES → Create "pump_off" action (EMERGENCY)
    │   │         Create "close_valve" action
    │   │         Create "critical" alert
    │   │
    │   └─> NO → Continue monitoring
    │
    └─> Rule 4: Rain Forecast (Manual Trigger)
        │
        └─> Cancel all pending irrigation actions
            Create "info" alert
```

**Key Points:**
- Rules evaluate **sequentially** for each zone
- Actions are **queued** in the `actions` table (status: "pending")
- Alerts are **immediately created** in the `alerts` table
- Rules prevent duplicate actions (checks for existing pending actions)

---

### 3. **Action Execution Cycle**

```
Action Worker (Runs continuously)
    │
    ├─> Polls database for pending actions
    │
    ├─> For each pending action:
    │   │
    │   ├─> Validate sensor exists and is operational
    │   │
    │   ├─> Execute action based on type:
    │   │   • pump_on → Create telemetry: pump_status=1, current=5-10A
    │   │   • pump_off → Create telemetry: pump_status=0, current=0
    │   │   • open_valve → Create telemetry: valve_status=1
    │   │   • close_valve → Create telemetry: valve_status=0
    │   │
    │   ├─> Mark action as "done" or "failed"
    │   │
    │   └─> Log execution result
    │
    └─> Repeat every 1-2 seconds
```

**Logic:**
- Actions are processed **FIFO** (First In, First Out)
- Hardware is **simulated** (in production, this would call actual hardware APIs)
- Telemetry is **automatically generated** when actions execute
- Failed actions are logged for debugging

---

### 4. **API Request Flow**

```
Frontend Request
    │
    ├─> GET /api/zones
    │   └─> Returns: All zones with nested sensors
    │
    ├─> GET /api/telemetry/latest
    │   └─> Returns: Latest telemetry per sensor (all metrics)
    │
    ├─> POST /api/pump/{id}/{action}
    │   └─> Creates action in database
    │       Returns: Success message
    │
    ├─> POST /api/simulate
    │   └─> Injects anomaly telemetry
    │       Triggers rule engine
    │       Returns: Success message
    │
    └─> GET /api/alerts
        └─> Returns: Active alerts (filtered by level, zone, etc.)
```

**Polling Pattern:**
- Frontend polls every 4 seconds
- Backend queries latest telemetry from database
- Data is **real-time** (within polling interval)

---

## 🧠 Logical Decision Tree

### Scenario 1: Normal Irrigation Cycle

```
1. Simulator generates low moisture (25%)
   │
2. Rule Engine detects: moisture < 30%
   │
3. Rule Engine creates: pump_on action
   │
4. Action Worker processes action
   │
5. Pump telemetry updated: status=ON, current=8A
   │
6. Simulator detects pump is ON
   │
7. Moisture starts increasing (25% → 30% → 35%...)
   │
8. Rule Engine detects: moisture >= 30%
   │
9. Rule Engine creates: pump_off action
   │
10. Action Worker processes action
    │
11. Pump telemetry updated: status=OFF, current=0A
    │
12. Cycle complete
```

### Scenario 2: Emergency Shutdown (Leak Detection)

```
1. Simulator generates high flow (25 L/min, expected=10 L/min)
   │
2. Rule Engine detects: flow > 15 L/min (1.5x threshold)
   │
3. Rule Engine creates: pump_off action (EMERGENCY)
   │
4. Rule Engine creates: close_valve action
   │
5. Rule Engine creates: critical alert
   │
6. Action Worker processes pump_off (priority)
   │
7. Action Worker processes close_valve
   │
8. System is now safe (pump OFF, valve CLOSED)
   │
9. Alert appears in frontend
   │
10. User can investigate and resolve
```

### Scenario 3: Manual Control

```
1. User clicks "Turn ON Pump" button in frontend
   │
2. Frontend calls: POST /api/pump/2/on
   │
3. Backend creates: pump_on action (status: pending)
   │
4. Action Worker processes action
   │
5. Pump telemetry updated: status=ON, current=8A
   │
6. Frontend polls and sees updated status
   │
7. Button changes to "Turn OFF" (green → red)
```

---

## 🔗 Component Interactions

### Database Tables & Relationships

```
zones (1) ──< (N) sensors
sensors (1) ──< (N) telemetry
sensors (1) ──< (N) actions
sensors (1) ──< (N) alerts
zones (1) ──< (N) alerts
```

**Key Relationships:**
- Each zone has multiple sensors
- Each sensor generates multiple telemetry records
- Actions target specific sensors
- Alerts can be zone-level or sensor-level

---

## ⚙️ Configuration & Thresholds

### Rule Engine Thresholds

```php
MOISTURE_THRESHOLD = 30.0%        // Start irrigation below this
PUMP_OVERLOAD_CURRENT = 15.0A     // Emergency shutdown above this
FLOW_LEAK_MULTIPLIER = 1.5x       // Leak detection threshold
```

### Simulator Settings

```php
Interval: 2-3 seconds (configurable)
Moisture Range: 0-100%
Pump Current (Normal): 5-12A
Pump Current (Overload): 16-20A (5% chance)
Flow Rate (Normal): 8-12 L/min
Flow Rate (Leak): 20-25 L/min (3% chance)
```

---

## 🚀 System Startup Sequence

```
1. Database migrations run
   └─> Creates tables: zones, sensors, telemetry, alerts, actions

2. Seeder runs
   └─> Creates 3 zones, 10 sensors (soil, pump, flow, valve)

3. Simulator starts (Terminal 1)
   └─> Begins generating telemetry every 2-3 seconds

4. Action Worker starts (Terminal 2)
   └─> Begins processing pending actions

5. API Server starts (Terminal 3)
   └─> Serves REST endpoints on port 8000

6. Frontend starts (Terminal 4)
   └─> Connects to API, polls every 4 seconds

7. System is LIVE
   └─> Telemetry → Rules → Actions → Execution → Feedback
```

---

## 🎯 Key Design Principles

1. **Separation of Concerns**
   - Simulator: Data generation
   - Rule Engine: Decision logic
   - Action Worker: Command execution
   - API: Data access
   - Frontend: User interface

2. **Event-Driven Architecture**
   - Telemetry updates trigger rule evaluation
   - Actions trigger hardware simulation
   - Alerts notify users

3. **Safety First**
   - Emergency shutdowns override normal operations
   - Duplicate action prevention
   - Error handling and logging

4. **Scalability**
   - Database-driven (can add more zones/sensors)
   - Polling-based (can switch to WebSockets)
   - Modular components (easy to extend)

---

## 📈 Data Flow Summary

```
┌─────────────┐
│  Simulator  │ Generates telemetry
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Database   │ Stores telemetry
└──────┬──────┘
       │
       ├──────────────┐
       │              │
       ▼              ▼
┌─────────────┐  ┌─────────────┐
│Rule Engine  │  │   API       │
│Evaluates    │  │   Serves    │
│Creates      │  │   Data      │
│Actions      │  │   to        │
└──────┬──────┘  │   Frontend  │
       │         └─────────────┘
       │
       ▼
┌─────────────┐
│   Actions   │ Queued in database
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   Worker    │ Executes actions
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Database   │ Updates telemetry
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Frontend   │ Displays updated data
└─────────────┘
```

---

## 🎓 In Simple Terms

**Think of it like a smart home thermostat, but for irrigation:**

1. **Sensors** = Temperature sensors (but for soil moisture, pump current, water flow)
2. **Rule Engine** = Thermostat logic (if too cold, turn on heat → if too dry, turn on pump)
3. **Actions** = Commands to turn devices on/off
4. **Action Worker** = The system that actually flips the switches
5. **Frontend** = The control panel where you see everything and can override

**The system runs automatically:**
- Monitors conditions continuously
- Makes decisions based on rules
- Executes actions when needed
- Alerts you when something's wrong
- Lets you take manual control anytime

---

## 🔍 Quick Reference

| Component | Purpose | Frequency |
|-----------|---------|-----------|
| Simulator | Generate sensor data | Every 2-3 seconds |
| Rule Engine | Evaluate conditions | After each telemetry update |
| Action Worker | Execute commands | Continuous (every 1-2 seconds) |
| API | Serve data | On-demand (HTTP requests) |
| Frontend | Display & control | Polls every 4 seconds |

---

This system is **fully automated** but allows **manual intervention** when needed. It's designed to be **safe** (emergency shutdowns), **efficient** (only irrigates when needed), and **observable** (real-time monitoring and alerts).







