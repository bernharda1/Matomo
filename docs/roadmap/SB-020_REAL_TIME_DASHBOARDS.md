# SB-020: Real-time Dashboards (Initial Implementation)

**Status:** In Progress (Planning Phase)  
**Branch:** `SB-020-real-time-dashboards`  
**Phase:** Phase 3 Sprint 3 (Weeks 20-22)

---

## Overview

SB-020 implements real-time analytics dashboards with WebSocket streaming:

- ✅ RealtimeProcessor (data collection & aggregation)
- ✅ WebSocketBroadcaster (client subscription management)
- ✅ RealtimeAPI (REST endpoints for real-time data)
- 🔄 WebSocket server integration (next iteration)
- 🔄 Real-time UI components (next iteration)

**Key Goals:** Live dashboards updating every 10 seconds, <100ms latency

---

## Step 1: Real-time Data Processor (SB-020.1)

### RealtimeProcessor (`Service/RealtimeProcessor.php`)

**Features:**

| Feature | Implementation | Status |
|---------|-----------------|--------|
| Real-time flows | Last 50 visitors | ✅ |
| Real-time transitions | Last 100 transitions | ✅ |
| Real-time dropoffs | Last 50 dropoff events | ✅ |
| Visitor count | Current + 30-min trend | ✅ |
| Comprehensive data | All combined | ✅ |
| Stream support | Generator-based streaming | ✅ |
| Segment support | Filtered real-time data | ✅ |

**API Methods:**

```php
// Get real-time flows (last 50 visitors)
$flows = $processor->getRealtimeFlows();
// Returns: [flows[], total_visitors, timestamp]

// Get real-time transitions
$transitions = $processor->getRealtimeTransitions();
// Returns: [transitions[], top_transitions[], total_transitions]

// Get real-time dropoffs
$dropoffs = $processor->getRealtimeDropoffs();
// Returns: [dropoffs[], top_dropoff_locations[], total_dropoffs]

// Get visitor count trend
$count = $processor->getRealtimeVisitorCount();
// Returns: [current_visitors, trend_30_min[]]

// Get all data at once
$all = $processor->getComprehensiveRealtimeData();
// Returns: [flows, transitions, dropoffs, visitor_count]

// Stream events (for WebSocket)
$processor->streamRealtimeEvents(function($data) {
    // Called every 10 seconds with latest data
});
```

---

## Step 2: WebSocket Broadcaster (SB-020.2)

### WebSocketBroadcaster (`Service/WebSocketBroadcaster.php`)

**Features:**

| Feature | Implementation | Status |
|---------|-----------------|--------|
| Client subscription | Per-site + segment | ✅ |
| Client unsubscription | Cleanup management | ✅ |
| Broadcast to all | Event distribution | ✅ |
| Broadcast to site | Site-specific filtering | ✅ |
| Broadcast to segment | Segment-specific filtering | ✅ |
| Heartbeat mechanism | Keep-alive pings | ✅ |
| Connection timeout | Auto-cleanup (5 min) | ✅ |
| Statistics tracking | Connected clients, per-site | ✅ |

**API Methods:**

```php
// Subscribe client
WebSocketBroadcaster::subscribe(
    clientId: 'user_123',
    siteId: 1,
    segment: 'deviceType==mobile',
    onMessage: fn($msg) => echo $msg
);

// Unsubscribe
WebSocketBroadcaster::unsubscribe('user_123');

// Broadcast to all
WebSocketBroadcaster::broadcast('event_type', $data);

// Broadcast to site
WebSocketBroadcaster::broadcastToSite(1, 'event_type', $data);

// Broadcast to segment
WebSocketBroadcaster::broadcastToSegment(1, 'deviceType==mobile', 'event_type', $data);

// Send heartbeats
WebSocketBroadcaster::sendHeartbeat();

// Get statistics
$stats = WebSocketBroadcaster::getStatistics();
// Returns: [total_connected, sites[], segments[]]
```

---

## Step 3: Real-time API (SB-020.3)

### RealtimeAPI (`API/RealtimeAPI.php`)

**Endpoints:**

| Endpoint | Method | Parameters | Response |
|----------|--------|-----------|----------|
| `/getRealtimeFlows` | GET | idSite, segment? | Flows data |
| `/getRealtimeTransitions` | GET | idSite, segment? | Transitions |
| `/getRealtimeDropoffs` | GET | idSite, segment? | Dropoffs |
| `/getRealtimeVisitorCount` | GET | idSite, segment? | Visitor count |
| `/getComprehensiveRealtimeData` | GET | idSite, segment? | All combined |
| `/subscribeToRealtimeEvents` | POST | idSite, clientId, segment? | Subscription status |
| `/unsubscribeFromRealtimeEvents` | POST | clientId | Unsubscription status |
| `/getRealtimeStatistics` | GET | - | Statistics |
| `/broadcastEvent` | POST | idSite, eventType, data, segment? | Broadcast status |

---

## Real-time Data Flow

### Architecture Diagram

```
Visitor Events
      ↓
FlowEventRepository (raw data)
      ↓
RealtimeProcessor (aggregates)
      ↓
WebSocketBroadcaster (distributes)
      ├→ WebSocket Connections (live updates)
      ├→ Polling Clients (REST API)
      └→ Dashboard UI (renders in real-time)
```

### Data Update Cycle

```
Every 10 seconds:
1. RealtimeProcessor queries latest events
2. Aggregates into flows/transitions/dropoffs
3. WebSocketBroadcaster sends to all subscribers
4. UI receives update and renders
5. Chart/animations reflect live data

Latency:
- Event to database: 1-2s
- Query + aggregation: 1-2s
- Broadcasting: < 100ms
- Network latency: 50-200ms
- UI render: 100-300ms
- Total: 2-3 seconds
```

---

## Next Implementation Phases

### Phase 3B: WebSocket Server Integration
- Ratchet/ReactPHP WebSocket server
- Connection management
- Heartbeat/ping-pong
- Error handling & reconnection

### Phase 3C: Real-time UI Components
- Vue.js components for dashboards
- Chart.js animations
- Live path flow visualization
- Real-time visitor counter

### Phase 3D: Performance Optimization
- Message compression (gzip)
- Delta updates (only changes)
- Client-side caching
- Automatic reconnection

---

## Configuration

### Environment Variables

```bash
# Real-time settings
VISITORFLOW_REALTIME_UPDATE_INTERVAL=10        # seconds
VISITORFLOW_REALTIME_HISTORY_LIMIT=50          # recent events
VISITORFLOW_WEBSOCKET_MAX_CLIENTS=1000         # concurrent
VISITORFLOW_WEBSOCKET_TIMEOUT=300              # seconds (5 min)
VISITORFLOW_WEBSOCKET_HEARTBEAT_INTERVAL=30    # seconds
```

---

## Security Considerations

### Authentication
- Validate client credentials before subscribe
- Check site access permissions
- Rate limit per client (100 events/min)

### Data Privacy
- Filter by user segment permissions
- Exclude sensitive data
- Audit logging for broadcasts

### Connection Management
- Max 1000 concurrent clients per server
- Auto-cleanup dead connections (5 min timeout)
- Heartbeat validation (ping/pong)

---

## Performance Targets

| Metric | Target | Status |
|--------|--------|--------|
| Event to dashboard | < 5 seconds | 🎯 |
| WebSocket latency | < 100ms | 🎯 |
| Memory per client | < 1 MB | 🎯 |
| CPU per 1000 clients | < 5% | 🎯 |
| Database queries | 1 per 10s | 🎯 |

---

## Files Created

| File | Lines | Purpose |
|------|-------|---------|
| Service/RealtimeProcessor.php | 180 | Real-time data aggregation |
| Service/WebSocketBroadcaster.php | 220 | Client subscription & broadcasting |
| API/RealtimeAPI.php | 160 | REST API endpoints |

**Total: +560 lines (foundation)**

---

## Status & Next Steps

**Completed (This Session):**
- ✅ RealtimeProcessor implementation
- ✅ WebSocketBroadcaster implementation
- ✅ RealtimeAPI endpoints

**Next (Continuation):**
- 📋 WebSocket server setup
- 📋 Real-time UI components
- 📋 Performance optimization
- 📋 Testing & integration

---

**SB-020 Foundation Ready!** 🚀
