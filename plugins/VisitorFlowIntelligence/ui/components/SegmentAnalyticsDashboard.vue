<template>
  <div class="segment-analytics-dashboard">
    <!-- Header -->
    <div class="analytics-header">
      <div class="header-title">
        <h2>{{ segment.name }} Analytics</h2>
        <span class="segment-info">Created {{ formatDate(segment.created_at) }}</span>
      </div>

      <div class="header-controls">
        <select v-model="selectedPeriod" class="period-select" @change="loadAnalytics">
          <option value="week">Last 7 Days</option>
          <option value="month">Last 30 Days</option>
          <option value="quarter">Last 90 Days</option>
          <option value="year">Last Year</option>
        </select>

        <button class="btn btn-export" @click="exportAnalytics">
          <span class="icon">📥</span> Export
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-spinner">
      <div class="spinner"></div>
      <p>Loading analytics...</p>
    </div>

    <!-- Main Analytics Grid -->
    <div v-else class="analytics-content">
      <!-- Key Metrics Cards -->
      <div class="metrics-section">
        <h3>Key Metrics</h3>
        <div class="metrics-grid">
          <div v-for="(metric, key) in displayMetrics" :key="key" class="metric-card">
            <div class="metric-label">{{ formatLabel(key) }}</div>
            <div class="metric-value">{{ formatMetricValue(metric, key) }}</div>
            <div v-if="metric.trend" class="metric-trend" :class="metric.trend > 0 ? 'positive' : 'negative'">
              {{ metric.trend > 0 ? '↑' : '↓' }} {{ Math.abs(metric.trend).toFixed(1) }}%
            </div>
          </div>
        </div>
      </div>

      <!-- Trends Chart -->
      <div class="trends-section">
        <h3>Trends ({{ selectedPeriod }})</h3>
        <div class="trends-chart">
          <canvas ref="trendsChart"></canvas>
        </div>
      </div>

      <!-- Traffic Breakdown -->
      <div class="breakdown-section">
        <div class="breakdown-grid">
          <!-- Traffic Sources -->
          <div class="breakdown-card">
            <h4>Traffic Sources</h4>
            <div class="breakdown-list">
              <div v-for="source in analytics.top_sources" :key="source.traffic_source" class="breakdown-item">
                <span class="label">{{ source.traffic_source }}</span>
                <div class="bar">
                  <div class="fill" :style="{ width: calculatePercentage(source.visits, totalTraffic) + '%' }"></div>
                </div>
                <span class="value">{{ source.visits }} visits</span>
              </div>
            </div>
          </div>

          <!-- Device Breakdown -->
          <div class="breakdown-card">
            <h4>Devices</h4>
            <div class="breakdown-list">
              <div v-for="device in analytics.device_breakdown" :key="device.device_type" class="breakdown-item">
                <span class="label">{{ formatDeviceType(device.device_type) }}</span>
                <div class="bar">
                  <div class="fill" :style="{ width: calculatePercentage(device.visits, totalTraffic) + '%' }"></div>
                </div>
                <span class="value">{{ device.visits }} visits</span>
              </div>
            </div>
          </div>

          <!-- Browser Breakdown -->
          <div class="breakdown-card">
            <h4>Browsers</h4>
            <div class="breakdown-list">
              <div v-for="browser in analytics.browser_breakdown" :key="browser.browser_name" class="breakdown-item">
                <span class="label">{{ browser.browser_name }}</span>
                <div class="bar">
                  <div class="fill" :style="{ width: calculatePercentage(browser.visits, totalTraffic) + '%' }"></div>
                </div>
                <span class="value">{{ browser.visits }} visits</span>
              </div>
            </div>
          </div>

          <!-- Geographic Breakdown -->
          <div class="breakdown-card">
            <h4>Top Countries</h4>
            <div class="breakdown-list">
              <div v-for="country in analytics.geo_breakdown" :key="country.country_code" class="breakdown-item">
                <span class="label">{{ country.country_code }}</span>
                <div class="bar">
                  <div class="fill" :style="{ width: calculatePercentage(country.visits, totalTraffic) + '%' }"></div>
                </div>
                <span class="value">{{ country.visits }} visits</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Pages -->
      <div class="pages-section">
        <h3>Top Pages</h3>
        <table class="pages-table">
          <thead>
            <tr>
              <th>Page</th>
              <th>Views</th>
              <th>Unique Visits</th>
              <th>Avg. Time</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="page in analytics.top_pages" :key="page.page_name">
              <td class="page-name" :title="page.page_name">{{ truncate(page.page_name, 60) }}</td>
              <td>{{ page.views }}</td>
              <td>{{ page.unique_visits }}</td>
              <td>{{ formatSeconds(page.avg_time) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Conversions -->
      <div v-if="analytics.conversions && analytics.conversions.length" class="conversions-section">
        <h3>Conversions</h3>
        <table class="conversions-table">
          <thead>
            <tr>
              <th>Goal</th>
              <th>Conversions</th>
              <th>Converters</th>
              <th>Revenue</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="goal in analytics.conversions" :key="goal.goal_id">
              <td>Goal {{ goal.goal_id }}</td>
              <td>{{ goal.conversions }}</td>
              <td>{{ goal.unique_converters }}</td>
              <td>{{ formatCurrency(goal.revenue) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Comparison Section -->
      <div class="comparison-section">
        <h3>Compare Segments</h3>
        <div class="comparison-selector">
          <input v-model="comparisonSegmentIds" type="text" placeholder="Enter segment IDs (comma-separated)" class="comparison-input">
          <button class="btn btn-compare" @click="compareSegments">Compare</button>
        </div>
        <div v-if="comparisonData.length" class="comparison-table-wrapper">
          <table class="comparison-table">
            <thead>
              <tr>
                <th>Metric</th>
                <td v-for="comp in comparisonData" :key="comp.segment.id">{{ comp.segment.name }}</td>
              </tr>
            </thead>
            <tbody>
              <tr v-for="metric in comparisonMetrics" :key="metric">
                <th>{{ formatLabel(metric) }}</th>
                <td v-for="comp in comparisonData" :key="comp.segment.id">
                  {{ formatMetricValue(comp.metrics[metric], metric) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Status Messages -->
    <div v-if="successMessage" class="alert alert-success">
      {{ successMessage }}
      <button class="alert-close" @click="successMessage = ''">×</button>
    </div>

    <div v-if="errorMessage" class="alert alert-error">
      {{ errorMessage }}
      <button class="alert-close" @click="errorMessage = ''">×</button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SegmentAnalyticsDashboard',
  props: {
    segmentId: {
      type: Number,
      required: true,
    },
    apiUrl: {
      type: String,
      default: '/api',
    },
  },
  data() {
    return {
      segment: {},
      analytics: {
        metrics: {},
        trends: {},
        top_sources: [],
        device_breakdown: [],
        browser_breakdown: [],
        geo_breakdown: [],
        top_pages: [],
        conversions: [],
      },
      selectedPeriod: 'month',
      loading: false,
      successMessage: '',
      errorMessage: '',
      comparisonSegmentIds: '',
      comparisonData: [],
      comparisonMetrics: ['visits', 'visitors', 'bounce_rate', 'avg_session_duration'],
    };
  },
  computed: {
    displayMetrics() {
      return {
        visits: this.analytics.metrics.visits,
        visitors: this.analytics.metrics.visitors,
        bounce_rate: this.analytics.metrics.bounce_rate,
        avg_session_duration: this.analytics.metrics.avg_session_duration,
        conversion_rate: this.analytics.metrics.conversion_rate,
        avg_actions_per_visit: this.analytics.metrics.avg_actions_per_visit,
      };
    },
    totalTraffic() {
      return this.analytics.top_sources.reduce((sum, s) => sum + s.visits, 0);
    },
  },
  mounted() {
    this.loadAnalytics();
  },
  methods: {
    async loadAnalytics() {
      this.loading = true;
      try {
        const response = await fetch(
          `${this.apiUrl}/SegmentAnalyticsAPI.getSegmentAnalytics?segmentId=${this.segmentId}&period=${this.selectedPeriod}`
        );
        if (!response.ok) throw new Error('Failed to load analytics');
        
        const data = await response.json();
        this.analytics = data;
        this.segment = data.segment || {};
        
        this.$nextTick(() => {
          this.renderTrendsChart();
        });
      } catch (error) {
        this.errorMessage = `Error loading analytics: ${error.message}`;
      } finally {
        this.loading = false;
      }
    },

    async compareSegments() {
      if (!this.comparisonSegmentIds.trim()) return;
      
      try {
        const response = await fetch(
          `${this.apiUrl}/SegmentAnalyticsAPI.compareSegments?segmentIds=${this.comparisonSegmentIds}`
        );
        if (!response.ok) throw new Error('Failed to compare segments');
        
        this.comparisonData = Object.values(await response.json());
        this.successMessage = 'Comparison loaded successfully';
        setTimeout(() => {
          this.successMessage = '';
        }, 3000);
      } catch (error) {
        this.errorMessage = `Error comparing segments: ${error.message}`;
      }
    },

    async exportAnalytics() {
      try {
        const response = await fetch(
          `${this.apiUrl}/SegmentAnalyticsAPI.exportAnalytics?segmentId=${this.segmentId}&format=csv`
        );
        if (!response.ok) throw new Error('Export failed');
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `segment-${this.segmentId}-analytics.csv`;
        link.click();
        
        this.successMessage = 'Analytics exported successfully';
        setTimeout(() => {
          this.successMessage = '';
        }, 3000);
      } catch (error) {
        this.errorMessage = `Export failed: ${error.message}`;
      }
    },

    renderTrendsChart() {
      const canvas = this.$refs.trendsChart;
      if (!canvas) return;

      const ctx = canvas.getContext('2d');
      const dates = Object.keys(this.analytics.trends || {});
      const visits = dates.map(d => this.analytics.trends[d].visits || 0);

      // Simple chart rendering (using canvas directly)
      const width = canvas.width = canvas.offsetWidth;
      const height = canvas.height = 200;
      
      ctx.fillStyle = '#f0f0f0';
      ctx.fillRect(0, 0, width, height);

      if (visits.length === 0) return;

      const maxVisits = Math.max(...visits);
      const barWidth = width / visits.length;

      ctx.fillStyle = '#2196F3';
      visits.forEach((v, i) => {
        const barHeight = (v / maxVisits) * (height - 40);
        ctx.fillRect(i * barWidth + 2, height - barHeight - 20, barWidth - 4, barHeight);
      });
    },

    calculatePercentage(value, total) {
      return total > 0 ? Math.round((value / total) * 100) : 0;
    },

    formatLabel(key) {
      return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
    },

    formatMetricValue(value, key) {
      if (typeof value !== 'number') return '—';
      if (key.includes('rate') || key.includes('percentage')) return value.toFixed(1) + '%';
      if (key.includes('duration')) return this.formatSeconds(value);
      return value.toLocaleString();
    },

    formatSeconds(seconds) {
      if (!seconds) return '0s';
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
    },

    formatCurrency(value) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
      }).format(value);
    },

    formatDate(date) {
      return new Date(date).toLocaleDateString();
    },

    formatDeviceType(type) {
      return type.charAt(0).toUpperCase() + type.slice(1);
    },

    truncate(text, length) {
      return text.length > length ? text.substring(0, length) + '...' : text;
    },
  },
};
</script>

<style scoped>
.segment-analytics-dashboard {
  padding: 20px;
  background: #f9f9f9;
}

.analytics-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid #eee;
}

.header-title h2 {
  margin: 0;
  color: #333;
}

.segment-info {
  font-size: 12px;
  color: #999;
  margin-top: 5px;
  display: block;
}

.header-controls {
  display: flex;
  gap: 10px;
}

.period-select {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 13px;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
}

.btn-export,
.btn-compare {
  background: #2196F3;
  color: white;
}

.btn-export:hover,
.btn-compare:hover {
  background: #1976d2;
}

.loading-spinner {
  text-align: center;
  padding: 40px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f0f0f0;
  border-top-color: #2196F3;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 10px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Metrics Grid */
.metrics-section {
  margin-bottom: 30px;
}

.metrics-section h3 {
  margin: 0 0 15px 0;
  font-size: 16px;
  color: #333;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
}

.metric-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #eee;
  text-align: center;
}

.metric-label {
  font-size: 12px;
  color: #666;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.metric-value {
  font-size: 28px;
  font-weight: bold;
  color: #333;
  margin-bottom: 8px;
}

.metric-trend {
  font-size: 13px;
  font-weight: 500;
}

.metric-trend.positive {
  color: #4CAF50;
}

.metric-trend.negative {
  color: #F44336;
}

/* Trends */
.trends-section {
  background: white;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #eee;
  margin-bottom: 30px;
}

.trends-section h3 {
  margin: 0 0 15px 0;
  font-size: 16px;
}

.trends-chart {
  position: relative;
  height: 250px;
}

canvas {
  width: 100% !important;
  height: 100% !important;
}

/* Breakdown Section */
.breakdown-section {
  margin-bottom: 30px;
}

.breakdown-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 15px;
}

.breakdown-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #eee;
}

.breakdown-card h4 {
  margin: 0 0 15px 0;
  font-size: 14px;
  color: #333;
}

.breakdown-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.breakdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

.breakdown-item .label {
  flex: 0 0 100px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.breakdown-item .bar {
  flex: 1;
  height: 20px;
  background: #f0f0f0;
  border-radius: 3px;
  overflow: hidden;
}

.breakdown-item .bar .fill {
  height: 100%;
  background: linear-gradient(90deg, #2196F3, #1976d2);
}

.breakdown-item .value {
  flex: 0 0 70px;
  text-align: right;
  color: #999;
}

/* Tables */
.pages-section,
.conversions-section {
  background: white;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #eee;
  margin-bottom: 30px;
}

.pages-section h3,
.conversions-section h3 {
  margin: 0 0 15px 0;
  font-size: 16px;
  color: #333;
}

.pages-table,
.conversions-table {
  width: 100%;
  border-collapse: collapse;
}

.pages-table th,
.conversions-table th,
.pages-table td,
.conversions-table td {
  padding: 12px;
  text-align: left;
  font-size: 13px;
  border-bottom: 1px solid #eee;
}

.pages-table th,
.conversions-table th {
  background: #f5f5f5;
  font-weight: 600;
  color: #333;
}

.page-name {
  max-width: 400px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Comparison */
.comparison-section {
  background: white;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #eee;
  margin-bottom: 30px;
}

.comparison-section h3 {
  margin: 0 0 15px 0;
  font-size: 16px;
  color: #333;
}

.comparison-selector {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.comparison-input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 13px;
}

.comparison-table-wrapper {
  overflow-x: auto;
}

.comparison-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.comparison-table th,
.comparison-table td {
  padding: 12px;
  text-align: center;
  border: 1px solid #eee;
}

.comparison-table th {
  background: #f5f5f5;
  font-weight: 600;
  color: #333;
}

.comparison-table th:first-child,
.comparison-table td:first-child {
  text-align: left;
}

/* Alerts */
.alert {
  padding: 12px 16px;
  border-radius: 4px;
  margin-bottom: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.alert-success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.alert-error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.alert-close {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  color: currentColor;
}
</style>
