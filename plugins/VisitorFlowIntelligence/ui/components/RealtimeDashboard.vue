<template>
  <div class="realtime-dashboard">
    <!-- Header -->
    <div class="header">
      <h1>Real-time Dashboard</h1>
      <div class="connection-status" :class="connectionStatus">
        <span class="dot"></span>
        {{ connectionStatus === 'connected' ? 'Live' : 'Disconnected' }}
      </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
      <div class="stat-card visitors">
        <div class="stat-value">{{ visitorCount }}</div>
        <div class="stat-label">Active Visitors</div>
        <canvas id="visitorChart" width="100" height="40"></canvas>
      </div>
      <div class="stat-card flows">
        <div class="stat-value">{{ flowCount }}</div>
        <div class="stat-label">Visitor Flows</div>
      </div>
      <div class="stat-card transitions">
        <div class="stat-value">{{ transitionCount }}</div>
        <div class="stat-label">Page Transitions</div>
      </div>
      <div class="stat-card dropoffs">
        <div class="stat-value">{{ dropoffCount }}</div>
        <div class="stat-label">Dropoffs</div>
      </div>
    </div>

    <!-- Real-time Flows -->
    <div class="section flows-section">
      <h2>Real-time Visitor Flows</h2>
      <div class="flows-list">
        <div v-for="flow in flows" :key="flow.visitor_id" class="flow-item">
          <div class="flow-header">
            <span class="visitor-id">Visitor #{{ flow.visitor_id }}</span>
            <span class="flow-depth">{{ flow.actions }} actions</span>
            <span class="flow-duration">{{ formatDuration(flow.duration) }}</span>
          </div>
          <div class="flow-path">
            <div v-for="(step, idx) in flow.path" :key="idx" class="path-step">
              <span class="step-text">{{ step }}</span>
              <span v-if="idx < flow.path.length - 1" class="step-arrow">→</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Top Transitions -->
    <div class="section transitions-section">
      <h2>Top Transitions</h2>
      <div class="transitions-list">
        <div v-for="(trans, idx) in topTransitions" :key="idx" class="transition-item">
          <span class="transition-label">{{ trans.from }} → {{ trans.to }}</span>
          <span class="transition-count">{{ trans.count }} times</span>
          <div class="transition-bar" :style="{ width: (trans.count / maxTransition * 100) + '%' }"></div>
        </div>
      </div>
    </div>

    <!-- Top Dropoff Locations -->
    <div class="section dropoffs-section">
      <h2>Top Dropoff Locations</h2>
      <div class="dropoffs-list">
        <div v-for="(dropoff, idx) in topDropoffs" :key="idx" class="dropoff-item">
          <span class="dropoff-location">{{ dropoff.location }}</span>
          <span class="dropoff-count">{{ dropoff.count }} dropoffs</span>
          <div class="dropoff-bar" :style="{ width: (dropoff.count / maxDropoff * 100) + '%' }"></div>
        </div>
      </div>
    </div>

    <!-- Last Update -->
    <div class="footer">
      <small>Last update: {{ lastUpdate }} | Updating every {{ updateInterval }}s</small>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RealtimeDashboard',
  props: {
    siteId: {
      type: Number,
      required: true,
    },
    segment: {
      type: String,
      default: null,
    },
    wsUrl: {
      type: String,
      default: 'ws://localhost:8080/realtime',
    },
    updateInterval: {
      type: Number,
      default: 10,
    },
  },
  data() {
    return {
      connectionStatus: 'disconnected',
      ws: null,
      visitorCount: 0,
      flowCount: 0,
      transitionCount: 0,
      dropoffCount: 0,
      flows: [],
      topTransitions: [],
      topDropoffs: [],
      lastUpdate: 'Never',
      maxTransition: 1,
      maxDropoff: 1,
      clientId: null,
    };
  },
  mounted() {
    this.clientId = 'client_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    this.connectWebSocket();
  },
  beforeUnmount() {
    if (this.ws) {
      this.ws.close();
    }
  },
  methods: {
    connectWebSocket() {
      try {
        this.ws = new WebSocket(this.wsUrl);

        this.ws.onopen = () => {
          console.log('WebSocket connected');
          this.connectionStatus = 'connected';
          this.subscribe();
        };

        this.ws.onmessage = (event) => {
          this.handleMessage(JSON.parse(event.data));
        };

        this.ws.onerror = (error) => {
          console.error('WebSocket error:', error);
          this.connectionStatus = 'error';
        };

        this.ws.onclose = () => {
          console.log('WebSocket disconnected');
          this.connectionStatus = 'disconnected';
          // Reconnect after 3 seconds
          setTimeout(() => this.connectWebSocket(), 3000);
        };
      } catch (error) {
        console.error('WebSocket connection failed:', error);
        this.connectionStatus = 'error';
      }
    },

    subscribe() {
      if (this.ws && this.ws.readyState === WebSocket.OPEN) {
        this.ws.send(JSON.stringify({
          action: 'subscribe',
          site_id: this.siteId,
          segment: this.segment,
          client_id: this.clientId,
        }));
      }
    },

    handleMessage(message) {
      if (message.type === 'subscribed') {
        console.log('Successfully subscribed to realtime updates');
        return;
      }

      if (message.type === 'realtime_data') {
        this.updateDashboard(message.data);
      }
    },

    updateDashboard(data) {
      this.lastUpdate = new Date().toLocaleTimeString();

      // Update visitor count
      if (data.visitor_count) {
        this.visitorCount = data.visitor_count.current_visitors;
      }

      // Update flows
      if (data.flows) {
        this.flows = data.flows.flows.slice(0, 10); // Show latest 10
        this.flowCount = data.flows.total_visitors;
      }

      // Update transitions
      if (data.transitions) {
        this.topTransitions = this.formatTransitions(data.transitions.top_transitions);
        this.transitionCount = data.transitions.total_transitions;
        this.maxTransition = Math.max(...this.topTransitions.map(t => t.count), 1);
      }

      // Update dropoffs
      if (data.dropoffs) {
        this.topDropoffs = this.formatDropoffs(data.dropoffs.top_dropoff_locations);
        this.dropoffCount = data.dropoffs.total_dropoffs;
        this.maxDropoff = Math.max(...this.topDropoffs.map(d => d.count), 1);
      }
    },

    formatTransitions(transitions) {
      if (!transitions || typeof transitions !== 'object') return [];
      
      return Object.entries(transitions).slice(0, 10).map(([key, count]) => {
        const [from, to] = key.split('->');
        return { from, to, count };
      });
    },

    formatDropoffs(dropoffs) {
      if (!dropoffs || typeof dropoffs !== 'object') return [];
      
      return Object.entries(dropoffs).slice(0, 10).map(([location, count]) => {
        return { location, count };
      });
    },

    formatDuration(seconds) {
      if (seconds < 60) return `${seconds}s`;
      if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
      return `${Math.floor(seconds / 3600)}h`;
    },
  },
};
</script>

<style scoped>
.realtime-dashboard {
  padding: 20px;
  background: #f5f5f5;
  border-radius: 8px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #ddd;
}

.header h1 {
  margin: 0;
  color: #333;
}

.connection-status {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 500;
}

.connection-status.connected {
  background: #d4edda;
  color: #155724;
}

.connection-status.disconnected {
  background: #f8d7da;
  color: #721c24;
}

.connection-status .dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  animation: pulse 2s infinite;
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 30px;
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stat-value {
  font-size: 32px;
  font-weight: bold;
  color: #333;
  margin-bottom: 10px;
}

.stat-label {
  font-size: 12px;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.section {
  background: white;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 20px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.section h2 {
  margin: 0 0 15px 0;
  font-size: 16px;
  color: #333;
  border-bottom: 1px solid #eee;
  padding-bottom: 10px;
}

.flows-list {
  max-height: 400px;
  overflow-y: auto;
}

.flow-item {
  padding: 12px 0;
  border-bottom: 1px solid #eee;
}

.flow-item:last-child {
  border-bottom: none;
}

.flow-header {
  display: flex;
  gap: 15px;
  margin-bottom: 8px;
  font-size: 12px;
  color: #666;
}

.visitor-id {
  font-weight: 500;
  color: #333;
}

.flow-path {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 12px;
}

.path-step {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.step-text {
  background: #f0f0f0;
  padding: 4px 8px;
  border-radius: 4px;
  color: #333;
}

.step-arrow {
  color: #999;
}

.transitions-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.transition-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.transition-label {
  min-width: 150px;
  font-size: 13px;
  color: #333;
}

.transition-count {
  min-width: 60px;
  text-align: right;
  font-size: 12px;
  font-weight: 500;
  color: #666;
}

.transition-bar {
  height: 20px;
  background: linear-gradient(90deg, #4CAF50, #8BC34A);
  border-radius: 3px;
  flex: 1;
  min-width: 50px;
}

.dropoff-item {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.dropoff-location {
  min-width: 150px;
  font-size: 13px;
  color: #333;
}

.dropoff-count {
  min-width: 60px;
  text-align: right;
  font-size: 12px;
  font-weight: 500;
  color: #666;
}

.dropoff-bar {
  height: 20px;
  background: linear-gradient(90deg, #ff9800, #f44336);
  border-radius: 3px;
  flex: 1;
  min-width: 50px;
}

.footer {
  text-align: center;
  color: #999;
  margin-top: 20px;
  padding-top: 10px;
  border-top: 1px solid #eee;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

@media (max-width: 768px) {
  .stats-row {
    grid-template-columns: 1fr 1fr;
  }

  .flow-path {
    font-size: 11px;
  }

  .section {
    padding: 15px;
  }
}
</style>
