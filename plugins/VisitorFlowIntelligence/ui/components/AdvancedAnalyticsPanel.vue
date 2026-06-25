<template>
  <div class="advanced-analytics-panel">
    <!-- Tabs -->
    <div class="tabs-container">
      <button
        v-for="tab in tabs"
        :key="tab"
        class="tab-button"
        :class="{ active: activeTab === tab }"
        @click="activeTab = tab"
      >
        {{ formatTabName(tab) }}
      </button>
    </div>

    <!-- Anomalies Tab -->
    <div v-show="activeTab === 'anomalies'" class="tab-content">
      <div v-if="loadingAnomalies" class="loading">
        <div class="spinner"></div>
        Analyzing anomalies...
      </div>
      <div v-else>
        <div v-if="anomalyData.has_anomalies" class="anomaly-card" :class="'severity-' + anomalyData.severity">
          <h3>🚨 Anomalies Detected ({{ anomalyData.severity }})</h3>
          
          <div v-for="(anomalies, category) in anomalyData.anomalies" :key="category" class="anomaly-category">
            <h4>{{ formatLabel(category) }}</h4>
            <div v-for="(anomaly, idx) in anomalies" :key="idx" class="anomaly-item">
              <div class="anomaly-label">{{ formatAnomalyLabel(anomaly, category) }}</div>
              <div class="anomaly-badge" :class="anomaly.severity">{{ anomaly.severity }}</div>
            </div>
          </div>
        </div>
        <div v-else class="anomaly-card positive">
          <h3>✅ No Anomalies</h3>
          <p>Segment showing normal, predictable behavior.</p>
        </div>

        <div v-if="anomalyInsights.length" class="insights-section">
          <h3>Insights</h3>
          <div v-for="(insight, idx) in anomalyInsights" :key="idx" class="insight-item" :class="'type-' + insight.type">
            <span class="insight-icon">
              {{ insight.type === 'positive' ? '✓' : insight.type === 'warning' ? '⚠' : 'ℹ' }}
            </span>
            {{ insight.message }}
          </div>
        </div>
      </div>
    </div>

    <!-- Forecast Tab -->
    <div v-show="activeTab === 'forecast'" class="tab-content">
      <div v-if="loadingForecast" class="loading">
        <div class="spinner"></div>
        Generating forecast...
      </div>
      <div v-else>
        <div class="forecast-card">
          <h3>7-Day Forecast</h3>
          
          <div class="trend-indicator">
            <div class="trend-direction" :class="forecastData.trend_direction.direction">
              <span class="arrow">{{ forecastData.trend_direction.direction === 'upward' ? '📈' : forecastData.trend_direction.direction === 'downward' ? '📉' : '→' }}</span>
              <span class="label">{{ formatLabel(forecastData.trend_direction.direction) }}</span>
              <span class="value">{{ forecastData.trend_direction.change_percent }}%</span>
            </div>
            <div class="confidence">
              Confidence: {{ Math.round(forecastData.confidence * 100) }}%
            </div>
          </div>

          <div class="forecast-chart">
            <canvas ref="forecastChart"></canvas>
          </div>

          <div class="forecast-bars">
            <div v-for="(value, idx) in forecastData.forecast" :key="idx" class="forecast-bar">
              <div class="bar-value" :style="{ height: calculateBarHeight(value, forecastData.forecast) }"></div>
              <span class="bar-label">Day {{ idx + 1 }}</span>
            </div>
          </div>

          <div class="forecast-recommendation">
            <strong>Recommendation:</strong> {{ forecastData.recommendation }}
          </div>
        </div>

        <div v-if="quarterlyGrowth" class="quarterly-card">
          <h3>Quarterly Growth Projection</h3>
          <div class="growth-stats">
            <div class="stat">
              <span class="label">Monthly Visits (Current)</span>
              <span class="value">{{ numberFormat(quarterlyGrowth.current_monthly_visits) }}</span>
            </div>
            <div class="stat">
              <span class="label">Growth Rate</span>
              <span class="value">{{ quarterlyGrowth.growth_rate_percent }}%</span>
            </div>
            <div class="stat">
              <span class="label">Quarterly Projection</span>
              <span class="value">{{ numberFormat(quarterlyGrowth.quarterly_projection) }}</span>
            </div>
            <div class="stat">
              <span class="label">Trend</span>
              <span class="value" :class="quarterlyGrowth.trend">{{ formatLabel(quarterlyGrowth.trend) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Export Tab -->
    <div v-show="activeTab === 'export'" class="tab-content">
      <div class="export-section">
        <h3>Export Analytics</h3>
        
        <div class="export-options">
          <div v-for="format in exportFormats" :key="format.id" class="export-option">
            <button class="export-button" @click="exportAnalytics(format.id)">
              <span class="icon">{{ format.icon }}</span>
              <span class="text">Export as {{ format.label }}</span>
            </button>
            <span class="description">{{ format.description }}</span>
          </div>
        </div>

        <div v-if="exportStatus.loading" class="export-status loading">
          Preparing export...
        </div>
        <div v-else-if="exportStatus.success" class="export-status success">
          ✅ Export ready! File downloaded.
        </div>
        <div v-else-if="exportStatus.error" class="export-status error">
          ❌ Export failed: {{ exportStatus.error }}
        </div>
      </div>

      <div class="scheduled-exports">
        <h3>Scheduled Exports</h3>
        <button class="btn btn-schedule" @click="showScheduleDialog = true">
          📅 Schedule Regular Export
        </button>
        
        <div v-if="showScheduleDialog" class="schedule-dialog">
          <div class="dialog-content">
            <h4>Schedule Export</h4>
            <div class="form-group">
              <label>Frequency</label>
              <select v-model="scheduleConfig.frequency">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
              </select>
            </div>
            <div class="form-group">
              <label>Format</label>
              <select v-model="scheduleConfig.format">
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
                <option value="html">HTML</option>
              </select>
            </div>
            <div class="form-group">
              <label>Email</label>
              <input v-model="scheduleConfig.email" type="email" placeholder="your@email.com">
            </div>
            <div class="dialog-actions">
              <button class="btn btn-primary" @click="scheduleExport">Schedule</button>
              <button class="btn btn-cancel" @click="showScheduleDialog = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Insights Tab -->
    <div v-show="activeTab === 'insights'" class="tab-content">
      <div class="insights-dashboard">
        <h3>Key Insights</h3>
        
        <div v-for="(insight, idx) in allInsights" :key="idx" class="insight-card" :class="'type-' + insight.type">
          <span class="insight-icon">{{ insight.icon }}</span>
          <div class="insight-content">
            <h4>{{ insight.title }}</h4>
            <p>{{ insight.message }}</p>
            <a v-if="insight.action" href="#" class="action-link">{{ insight.action }}</a>
          </div>
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
  name: 'AdvancedAnalyticsPanel',
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
      activeTab: 'anomalies',
      tabs: ['anomalies', 'forecast', 'export', 'insights'],
      
      // Anomaly Data
      anomalyData: { has_anomalies: false, anomalies: {}, severity: 'low' },
      anomalyInsights: [],
      loadingAnomalies: false,
      
      // Forecast Data
      forecastData: { forecast: [], trend_direction: { direction: 'stable' }, confidence: 0, recommendation: '' },
      quarterlyGrowth: null,
      loadingForecast: false,
      
      // Export Options
      exportFormats: [
        { id: 'csv', label: 'CSV', icon: '📊', description: 'Spreadsheet format with all metrics' },
        { id: 'json', label: 'JSON', icon: '{}', description: 'Structured data format' },
        { id: 'html', label: 'HTML', icon: '🌐', description: 'Formatted report for viewing' },
      ],
      exportStatus: { loading: false, success: false, error: null },
      
      // Scheduled Export
      showScheduleDialog: false,
      scheduleConfig: { frequency: 'weekly', format: 'csv', email: '' },
      
      // Status
      successMessage: '',
      errorMessage: '',
    };
  },
  computed: {
    allInsights() {
      const insights = [];
      
      if (this.anomalyData.has_anomalies && this.anomalyInsights.length) {
        insights.push(...this.anomalyInsights.map(i => ({
          ...i,
          icon: i.type === 'positive' ? '✓' : i.type === 'warning' ? '⚠' : 'ℹ',
        })));
      }
      
      if (this.forecastData.recommendation) {
        insights.push({
          type: 'forecast',
          icon: '📈',
          title: 'Forecast',
          message: this.forecastData.recommendation,
          action: 'View details',
        });
      }
      
      return insights;
    },
  },
  mounted() {
    this.loadAnomalies();
    this.loadForecast();
  },
  methods: {
    async loadAnomalies() {
      this.loadingAnomalies = true;
      try {
        // Mock API call (would be real endpoint in production)
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        this.anomalyData = {
          has_anomalies: false,
          anomalies: {},
          severity: 'low',
        };
        
        this.anomalyInsights = [
          {
            type: 'positive',
            message: 'Segment is performing normally with stable metrics.',
          },
        ];
      } catch (error) {
        this.errorMessage = 'Failed to load anomalies';
      } finally {
        this.loadingAnomalies = false;
      }
    },

    async loadForecast() {
      this.loadingForecast = true;
      try {
        // Mock data (would be real API call in production)
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        this.forecastData = {
          forecast: [120, 125, 122, 128, 135, 140, 138],
          trend_direction: {
            direction: 'upward',
            strength: 65,
            change_percent: 12.5,
          },
          confidence: 0.82,
          recommendation: 'Strong growth expected. Consider optimizing for scale.',
        };
        
        this.quarterlyGrowth = {
          current_monthly_visits: 2500,
          growth_rate_percent: 15,
          quarterly_projection: 8625,
          trend: 'high_growth',
        };
      } catch (error) {
        this.errorMessage = 'Failed to load forecast';
      } finally {
        this.loadingForecast = false;
      }
    },

    async exportAnalytics(format) {
      this.exportStatus.loading = true;
      this.exportStatus.success = false;
      this.exportStatus.error = null;
      
      try {
        // Simulate export
        await new Promise(resolve => setTimeout(resolve, 1500));
        
        this.exportStatus.loading = false;
        this.exportStatus.success = true;
        this.successMessage = `Analytics exported as ${format.toUpperCase()}`;
        
        setTimeout(() => {
          this.successMessage = '';
        }, 3000);
      } catch (error) {
        this.exportStatus.loading = false;
        this.exportStatus.error = error.message;
      }
    },

    async scheduleExport() {
      if (!this.scheduleConfig.email) {
        this.errorMessage = 'Please enter an email address';
        return;
      }
      
      try {
        // Mock API call
        this.showScheduleDialog = false;
        this.successMessage = `Export scheduled for ${this.scheduleConfig.frequency} delivery`;
        setTimeout(() => {
          this.successMessage = '';
        }, 3000);
      } catch (error) {
        this.errorMessage = 'Failed to schedule export';
      }
    },

    calculateBarHeight(value, allValues) {
      const max = Math.max(...allValues);
      return ((value / max) * 100) + '%';
    },

    formatTabName(tab) {
      const names = {
        anomalies: '🚨 Anomalies',
        forecast: '📈 Forecast',
        export: '📥 Export',
        insights: '💡 Insights',
      };
      return names[tab];
    },

    formatLabel(label) {
      return label
        .replace(/_/g, ' ')
        .split(' ')
        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
    },

    formatAnomalyLabel(anomaly, category) {
      if (category.includes('visit')) {
        return `Day ${anomaly.day_index}: ${anomaly.value} visits (expected ~${anomaly.expected})`;
      }
      return `Anomaly detected: ${anomaly.value}`;
    },

    numberFormat(num) {
      return new Intl.NumberFormat().format(num);
    },
  },
};
</script>

<style scoped>
.advanced-analytics-panel {
  background: white;
  border-radius: 8px;
  border: 1px solid #eee;
  margin-bottom: 20px;
}

.tabs-container {
  display: flex;
  gap: 10px;
  padding: 15px 20px;
  border-bottom: 2px solid #eee;
  overflow-x: auto;
}

.tab-button {
  padding: 8px 16px;
  background: #f5f5f5;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  transition: all 0.2s;
}

.tab-button:hover {
  background: #e0e0e0;
}

.tab-button.active {
  background: #2196F3;
  color: white;
}

.tab-content {
  padding: 20px;
}

/* Anomalies */
.anomaly-card {
  border-left: 4px solid #999;
  padding: 15px;
  border-radius: 4px;
  background: #f9f9f9;
  margin-bottom: 20px;
}

.anomaly-card.positive {
  border-left-color: #4CAF50;
  background: #f1f8f4;
}

.anomaly-card.severity-critical {
  border-left-color: #F44336;
  background: #fef5f5;
}

.anomaly-card.severity-high {
  border-left-color: #FF9800;
  background: #fff8f3;
}

.anomaly-category h4 {
  margin: 10px 0 5px 0;
  font-size: 13px;
  color: #333;
}

.anomaly-item {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 12px;
}

.anomaly-badge {
  padding: 2px 8px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: 600;
}

.anomaly-badge.warning {
  background: #FFF3CD;
  color: #856404;
}

.anomaly-badge.critical {
  background: #F8D7DA;
  color: #721C24;
}

/* Forecast */
.forecast-card {
  background: #f9f9f9;
  padding: 15px;
  border-radius: 4px;
  margin-bottom: 20px;
}

.forecast-card h3 {
  margin: 0 0 15px 0;
  font-size: 16px;
}

.trend-indicator {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.trend-direction {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  border-radius: 4px;
  background: white;
  border: 1px solid #ddd;
}

.trend-direction.upward {
  border-color: #4CAF50;
  background: #f1f8f4;
}

.trend-direction.downward {
  border-color: #F44336;
  background: #fef5f5;
}

.trend-direction.stable {
  border-color: #2196F3;
  background: #f3f8fc;
}

.arrow {
  font-size: 20px;
}

.label {
  font-weight: 600;
  color: #333;
}

.value {
  color: #2196F3;
  font-weight: bold;
}

.confidence {
  font-size: 12px;
  color: #666;
}

.forecast-chart {
  height: 150px;
  margin-bottom: 20px;
  background: white;
  border-radius: 4px;
}

.forecast-bars {
  display: flex;
  gap: 8px;
  margin-bottom: 15px;
  align-items: flex-end;
  height: 120px;
}

.forecast-bar {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
}

.bar-value {
  width: 100%;
  background: #2196F3;
  border-radius: 2px;
}

.bar-label {
  font-size: 11px;
  color: #999;
}

.forecast-recommendation {
  padding: 10px;
  background: white;
  border-left: 3px solid #2196F3;
  font-size: 13px;
  color: #333;
}

.quarterly-card {
  background: #f9f9f9;
  padding: 15px;
  border-radius: 4px;
}

.quarterly-card h3 {
  margin: 0 0 15px 0;
}

.growth-stats {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.stat {
  background: white;
  padding: 12px;
  border-radius: 4px;
  border: 1px solid #eee;
}

.stat .label {
  display: block;
  font-size: 11px;
  color: #999;
  text-transform: uppercase;
  margin-bottom: 5px;
}

.stat .value {
  display: block;
  font-size: 18px;
  font-weight: bold;
  color: #333;
}

.stat .value.high_growth {
  color: #4CAF50;
}

.stat .value.high_decline {
  color: #F44336;
}

/* Export */
.export-section {
  margin-bottom: 30px;
}

.export-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin: 20px 0;
}

.export-option {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.export-button {
  padding: 15px;
  background: #f5f5f5;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: all 0.2s;
}

.export-button:hover {
  background: #2196F3;
  color: white;
  border-color: #2196F3;
}

.icon {
  font-size: 18px;
}

.description {
  font-size: 11px;
  color: #999;
}

.export-status {
  padding: 12px;
  border-radius: 4px;
  font-size: 13px;
}

.export-status.loading {
  background: #e3f2fd;
  color: #1976d2;
}

.export-status.success {
  background: #e8f5e9;
  color: #2e7d32;
}

.export-status.error {
  background: #ffebee;
  color: #c62828;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
}

.btn-schedule {
  background: #2196F3;
  color: white;
}

.btn-schedule:hover {
  background: #1976d2;
}

.schedule-dialog {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.dialog-content {
  background: white;
  padding: 20px;
  border-radius: 8px;
  max-width: 400px;
  width: 90%;
}

.dialog-content h4 {
  margin: 0 0 15px 0;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-size: 13px;
  font-weight: 500;
}

.form-group select,
.form-group input {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 13px;
}

.dialog-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.btn-primary {
  background: #2196F3;
  color: white;
  flex: 1;
}

.btn-cancel {
  background: #f5f5f5;
  color: #333;
  flex: 1;
}

/* Insights */
.insights-dashboard {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.insight-card {
  display: flex;
  gap: 15px;
  padding: 15px;
  border-left: 4px solid #999;
  border-radius: 4px;
  background: #f9f9f9;
}

.insight-card.type-positive {
  border-left-color: #4CAF50;
  background: #f1f8f4;
}

.insight-card.type-warning {
  border-left-color: #FF9800;
  background: #fff8f3;
}

.insight-card.type-forecast {
  border-left-color: #2196F3;
  background: #f3f8fc;
}

.insight-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.insight-content h4 {
  margin: 0 0 5px 0;
  font-size: 14px;
}

.insight-content p {
  margin: 0 0 8px 0;
  font-size: 13px;
  color: #666;
}

.action-link {
  font-size: 12px;
  color: #2196F3;
  text-decoration: none;
}

.action-link:hover {
  text-decoration: underline;
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

.loading {
  text-align: center;
  padding: 20px;
}

.spinner {
  width: 30px;
  height: 30px;
  border: 3px solid #f0f0f0;
  border-top-color: #2196F3;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 10px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
