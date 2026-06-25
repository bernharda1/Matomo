<template>
  <div class="dashboard-builder">
    <!-- Header -->
    <div class="builder-header">
      <div class="header-left">
        <h1>Dashboard Builder</h1>
        <p class="subtitle">Create and customize your analytics dashboards</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" @click="openTemplateModal">
          📋 From Template
        </button>
        <button class="btn btn-secondary" @click="createNewDashboard">
          ➕ New Dashboard
        </button>
      </div>
    </div>

    <!-- Main Content -->
    <div class="builder-content">
      <!-- Sidebar: Dashboards List -->
      <div class="dashboards-sidebar">
        <div class="sidebar-header">
          <h2>My Dashboards</h2>
          <div class="sidebar-stats">
            <span class="stat">{{ dashboards.length }} total</span>
          </div>
        </div>

        <div class="dashboard-search">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search dashboards..."
            @keyup.enter="searchDashboards"
            class="search-input"
          >
        </div>

        <div class="dashboards-list">
          <div
            v-for="dash in dashboards"
            :key="dash.id"
            class="dashboard-item"
            :class="{ active: selectedDashboard?.id === dash.id }"
            @click="selectDashboard(dash)"
          >
            <div class="dashboard-info">
              <h3>{{ dash.name }}</h3>
              <span class="widget-count">{{ dash.widget_count }} widgets</span>
            </div>
            <div class="dashboard-actions" @click.stop>
              <button class="btn-icon" title="Duplicate" @click="duplicateDashboard(dash.id)">
                📋
              </button>
              <button class="btn-icon" title="Delete" @click="deleteDashboard(dash.id)">
                🗑️
              </button>
            </div>
          </div>

          <div v-if="dashboards.length === 0" class="empty-state">
            <p>No dashboards yet</p>
            <p class="hint">Create one to get started</p>
          </div>
        </div>
      </div>

      <!-- Main Editor -->
      <div class="editor-main">
        <div v-if="selectedDashboard" class="dashboard-editor">
          <!-- Dashboard Settings Bar -->
          <div class="editor-toolbar">
            <div class="toolbar-left">
              <input
                v-model="selectedDashboard.name"
                type="text"
                class="dashboard-name-input"
                @change="updateDashboardName"
              >
              <span class="separator">·</span>
              <input
                v-model="selectedDashboard.description"
                type="text"
                class="dashboard-desc-input"
                placeholder="Add description..."
                @change="updateDashboardDescription"
              >
            </div>
            <div class="toolbar-right">
              <button class="btn btn-small" @click="saveDashboard">
                💾 Save
              </button>
              <button class="btn btn-small" @click="addNewWidget">
                ➕ Add Widget
              </button>
            </div>
          </div>

          <!-- Widget Grid Editor -->
          <div class="grid-editor">
            <div class="grid-canvas">
              <div
                v-for="(widget, idx) in selectedDashboard.widgets"
                :key="widget.id"
                class="widget-wrapper"
                :style="{
                  gridColumn: `span ${widget.width || 4}`,
                  gridRow: `span ${widget.height || 3}`,
                }"
                @click="selectWidget(widget)"
                :class="{ selected: selectedWidget?.id === widget.id }"
              >
                <div class="widget-card">
                  <div class="widget-header">
                    <h4>{{ widget.type }}</h4>
                    <button
                      class="btn-close"
                      @click.stop="removeWidget(widget.id)"
                      title="Remove widget"
                    >
                      ✕
                    </button>
                  </div>
                  <div class="widget-preview">
                    <p>{{ formatWidgetType(widget.type) }}</p>
                    <span class="widget-size">{{ widget.width }}×{{ widget.height }}</span>
                  </div>
                </div>
              </div>

              <!-- Add Widget Placeholder -->
              <div class="widget-add-placeholder" @click="addNewWidget">
                <div class="placeholder-content">
                  <span class="icon">➕</span>
                  <p>Add Widget</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Widget Properties Panel -->
          <div v-if="selectedWidget" class="widget-panel">
            <h3>Widget Properties</h3>
            
            <div class="property-group">
              <label>Type</label>
              <select v-model="selectedWidget.type" @change="updateSelectedWidget">
                <option value="key_metrics">Key Metrics</option>
                <option value="trends_chart">Trends Chart</option>
                <option value="traffic_sources">Traffic Sources</option>
                <option value="device_breakdown">Device Breakdown</option>
                <option value="top_pages">Top Pages</option>
                <option value="conversion_metrics">Conversions</option>
                <option value="anomaly_alerts">Anomalies</option>
                <option value="forecast_chart">Forecast</option>
                <option value="live_visitors">Live Visitors</option>
                <option value="visitor_flow">Visitor Flow</option>
              </select>
            </div>

            <div class="property-group">
              <label>Width (columns)</label>
              <input
                v-model.number="selectedWidget.width"
                type="range"
                min="2"
                max="12"
                @change="updateSelectedWidget"
              >
              <span>{{ selectedWidget.width }} cols</span>
            </div>

            <div class="property-group">
              <label>Height (rows)</label>
              <input
                v-model.number="selectedWidget.height"
                type="range"
                min="2"
                max="6"
                @change="updateSelectedWidget"
              >
              <span>{{ selectedWidget.height }} rows</span>
            </div>

            <div class="property-actions">
              <button class="btn btn-small btn-danger" @click="removeSelectedWidget">
                🗑️ Remove Widget
              </button>
            </div>
          </div>
        </div>

        <div v-else class="empty-editor">
          <div class="empty-content">
            <h2>No Dashboard Selected</h2>
            <p>Select a dashboard from the list or create a new one</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <!-- Template Selection Modal -->
    <div v-if="showTemplateModal" class="modal-overlay" @click="showTemplateModal = false">
      <div class="modal-content" @click.stop>
        <h2>Choose a Template</h2>
        <div class="template-grid">
          <div
            v-for="template in templates"
            :key="template.id"
            class="template-card"
            @click="selectTemplate(template)"
          >
            <h3>{{ template.name }}</h3>
            <p>{{ template.description }}</p>
            <span class="widget-count">{{ template.widgets.length }} widgets</span>
          </div>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showTemplateModal = false">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- New Dashboard Modal -->
    <div v-if="showNewDashboardModal" class="modal-overlay" @click="showNewDashboardModal = false">
      <div class="modal-content" @click.stop>
        <h2>Create New Dashboard</h2>
        <div class="form-group">
          <label>Dashboard Name</label>
          <input v-model="newDashboardName" type="text" placeholder="e.g., Q2 Sales Analysis">
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea v-model="newDashboardDescription" placeholder="Optional description..."></textarea>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showNewDashboardModal = false">
            Cancel
          </button>
          <button class="btn btn-primary" @click="confirmCreateDashboard">
            Create Dashboard
          </button>
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
  name: 'DashboardBuilder',
  props: {
    apiUrl: {
      type: String,
      default: '/api',
    },
  },
  data() {
    return {
      dashboards: [],
      selectedDashboard: null,
      selectedWidget: null,
      templates: [],
      searchQuery: '',
      showTemplateModal: false,
      showNewDashboardModal: false,
      newDashboardName: '',
      newDashboardDescription: '',
      successMessage: '',
      errorMessage: '',
    };
  },
  mounted() {
    this.loadDashboards();
    this.loadTemplates();
  },
  methods: {
    async loadDashboards() {
      try {
        const response = await fetch(`${this.apiUrl}/dashboard/list`);
        const data = await response.json();
        if (data.success) {
          this.dashboards = data.dashboards || [];
        }
      } catch (error) {
        this.errorMessage = 'Failed to load dashboards';
      }
    },

    async loadTemplates() {
      try {
        const response = await fetch(`${this.apiUrl}/dashboard/templates`);
        const data = await response.json();
        if (data.success) {
          this.templates = data.templates || [];
        }
      } catch (error) {
        console.error('Failed to load templates:', error);
      }
    },

    selectDashboard(dashboard) {
      this.selectedDashboard = { ...dashboard };
      this.selectedWidget = null;
    },

    selectWidget(widget) {
      this.selectedWidget = { ...widget };
    },

    async createNewDashboard() {
      this.showNewDashboardModal = true;
      this.newDashboardName = '';
      this.newDashboardDescription = '';
    },

    async confirmCreateDashboard() {
      if (!this.newDashboardName.trim()) {
        this.errorMessage = 'Please enter a dashboard name';
        return;
      }

      try {
        const response = await fetch(`${this.apiUrl}/dashboard/create`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            name: this.newDashboardName,
            description: this.newDashboardDescription,
          }),
        });

        const data = await response.json();
        if (data.success) {
          this.showNewDashboardModal = false;
          this.successMessage = 'Dashboard created successfully';
          this.loadDashboards();
        }
      } catch (error) {
        this.errorMessage = 'Failed to create dashboard';
      }
    },

    openTemplateModal() {
      this.showTemplateModal = true;
    },

    async selectTemplate(template) {
      const name = template.name + ' - ' + new Date().toLocaleDateString();
      try {
        const response = await fetch(`${this.apiUrl}/dashboard/create-from-template`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            template_id: template.id,
            dashboard_name: name,
          }),
        });

        const data = await response.json();
        if (data.success) {
          this.showTemplateModal = false;
          this.successMessage = 'Dashboard created from template';
          this.loadDashboards();
        }
      } catch (error) {
        this.errorMessage = 'Failed to create dashboard from template';
      }
    },

    async updateDashboardName() {
      if (!this.selectedDashboard) return;
      
      await this.saveDashboard();
    },

    async updateDashboardDescription() {
      if (!this.selectedDashboard) return;
      
      await this.saveDashboard();
    },

    async saveDashboard() {
      if (!this.selectedDashboard) return;

      try {
        const response = await fetch(`${this.apiUrl}/dashboard/update`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            dashboard_id: this.selectedDashboard.id,
            name: this.selectedDashboard.name,
            description: this.selectedDashboard.description,
          }),
        });

        const data = await response.json();
        if (data.success) {
          this.successMessage = 'Dashboard saved';
        }
      } catch (error) {
        this.errorMessage = 'Failed to save dashboard';
      }
    },

    async addNewWidget() {
      if (!this.selectedDashboard) {
        this.errorMessage = 'Please select a dashboard first';
        return;
      }

      try {
        const response = await fetch(`${this.apiUrl}/dashboard/widget/add`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            dashboard_id: this.selectedDashboard.id,
            type: 'key_metrics',
            config: { width: 4, height: 3 },
          }),
        });

        const data = await response.json();
        if (data.success) {
          this.loadDashboards();
          this.successMessage = 'Widget added';
        }
      } catch (error) {
        this.errorMessage = 'Failed to add widget';
      }
    },

    async removeWidget(widgetId) {
      if (!confirm('Remove this widget?')) return;

      try {
        const response = await fetch(`${this.apiUrl}/dashboard/widget/remove`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ widget_id: widgetId }),
        });

        const data = await response.json();
        if (data.success) {
          this.loadDashboards();
          this.selectedWidget = null;
          this.successMessage = 'Widget removed';
        }
      } catch (error) {
        this.errorMessage = 'Failed to remove widget';
      }
    },

    removeSelectedWidget() {
      if (this.selectedWidget) {
        this.removeWidget(this.selectedWidget.id);
      }
    },

    async updateSelectedWidget() {
      if (!this.selectedWidget) return;

      try {
        const response = await fetch(`${this.apiUrl}/dashboard/widget/update`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            widget_id: this.selectedWidget.id,
            type: this.selectedWidget.type,
            width: this.selectedWidget.width,
            height: this.selectedWidget.height,
          }),
        });

        const data = await response.json();
        if (data.success) {
          this.successMessage = 'Widget updated';
        }
      } catch (error) {
        this.errorMessage = 'Failed to update widget';
      }
    },

    async duplicateDashboard(dashboardId) {
      try {
        const response = await fetch(`${this.apiUrl}/dashboard/duplicate`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ dashboard_id: dashboardId }),
        });

        const data = await response.json();
        if (data.success) {
          this.loadDashboards();
          this.successMessage = 'Dashboard duplicated';
        }
      } catch (error) {
        this.errorMessage = 'Failed to duplicate dashboard';
      }
    },

    async deleteDashboard(dashboardId) {
      if (!confirm('Delete this dashboard? This cannot be undone.')) return;

      try {
        const response = await fetch(`${this.apiUrl}/dashboard/delete`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ dashboard_id: dashboardId }),
        });

        const data = await response.json();
        if (data.success) {
          this.loadDashboards();
          if (this.selectedDashboard?.id === dashboardId) {
            this.selectedDashboard = null;
          }
          this.successMessage = 'Dashboard deleted';
        }
      } catch (error) {
        this.errorMessage = 'Failed to delete dashboard';
      }
    },

    async searchDashboards() {
      if (!this.searchQuery.trim()) {
        this.loadDashboards();
        return;
      }

      try {
        const response = await fetch(
          `${this.apiUrl}/dashboard/search?query=${encodeURIComponent(this.searchQuery)}`
        );
        const data = await response.json();
        if (data.success) {
          this.dashboards = data.dashboards || [];
        }
      } catch (error) {
        this.errorMessage = 'Search failed';
      }
    },

    formatWidgetType(type) {
      return type
        .replace(/_/g, ' ')
        .split(' ')
        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
    },
  },
};
</script>

<style scoped>
.dashboard-builder {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: #f5f5f5;
}

.builder-header {
  background: white;
  border-bottom: 1px solid #ddd;
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.header-left h1 {
  margin: 0 0 5px 0;
  font-size: 24px;
  color: #333;
}

.subtitle {
  margin: 0;
  font-size: 13px;
  color: #999;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.builder-content {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.dashboards-sidebar {
  width: 300px;
  background: white;
  border-right: 1px solid #ddd;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.sidebar-header {
  padding: 15px;
  border-bottom: 1px solid #eee;
}

.sidebar-header h2 {
  margin: 0 0 10px 0;
  font-size: 16px;
  color: #333;
}

.sidebar-stats {
  display: flex;
  gap: 10px;
}

.stat {
  font-size: 12px;
  color: #999;
  background: #f5f5f5;
  padding: 4px 8px;
  border-radius: 3px;
}

.dashboard-search {
  padding: 10px;
}

.search-input {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 13px;
}

.dashboards-list {
  flex: 1;
  overflow-y: auto;
  padding: 10px;
}

.dashboard-item {
  padding: 10px;
  border-radius: 4px;
  cursor: pointer;
  margin-bottom: 5px;
  background: #f9f9f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.2s;
}

.dashboard-item:hover {
  background: #f0f0f0;
}

.dashboard-item.active {
  background: #e3f2fd;
  border-left: 3px solid #2196F3;
}

.dashboard-info h3 {
  margin: 0 0 3px 0;
  font-size: 13px;
  color: #333;
}

.widget-count {
  font-size: 11px;
  color: #999;
}

.dashboard-actions {
  display: flex;
  gap: 5px;
}

.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  padding: 4px;
  opacity: 0.6;
  transition: opacity 0.2s;
}

.btn-icon:hover {
  opacity: 1;
}

.empty-state {
  text-align: center;
  padding: 30px 15px;
  color: #999;
}

.empty-state p {
  margin: 5px 0;
  font-size: 12px;
}

.editor-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.dashboard-editor {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.editor-toolbar {
  background: white;
  padding: 15px;
  border-bottom: 1px solid #ddd;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}

.dashboard-name-input {
  font-size: 18px;
  font-weight: bold;
  border: none;
  padding: 5px;
  background: transparent;
  color: #333;
  min-width: 200px;
}

.dashboard-name-input:focus {
  background: #f5f5f5;
  outline: none;
}

.separator {
  color: #ddd;
}

.dashboard-desc-input {
  flex: 1;
  border: none;
  padding: 5px;
  background: transparent;
  color: #999;
  font-size: 13px;
}

.dashboard-desc-input:focus {
  background: #f5f5f5;
  outline: none;
  color: #333;
}

.toolbar-right {
  display: flex;
  gap: 10px;
}

.grid-editor {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
}

.grid-canvas {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 15px;
  grid-auto-rows: 150px;
}

.widget-wrapper {
  position: relative;
  cursor: pointer;
  transition: all 0.2s;
}

.widget-wrapper.selected {
  filter: drop-shadow(0 4px 12px rgba(33, 150, 243, 0.4));
}

.widget-card {
  background: white;
  border-radius: 8px;
  border: 2px solid #ddd;
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: 15px;
  transition: all 0.2s;
}

.widget-wrapper.selected .widget-card {
  border-color: #2196F3;
  background: #f3f8fc;
}

.widget-wrapper:hover .widget-card {
  border-color: #999;
}

.widget-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.widget-header h4 {
  margin: 0;
  font-size: 12px;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-close {
  background: none;
  border: none;
  cursor: pointer;
  color: #999;
  font-size: 16px;
  opacity: 0;
  transition: opacity 0.2s;
}

.widget-wrapper:hover .btn-close {
  opacity: 1;
}

.widget-preview {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: #999;
}

.widget-preview p {
  margin: 0 0 10px 0;
  font-size: 13px;
}

.widget-size {
  font-size: 11px;
  color: #ccc;
}

.widget-add-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px dashed #ddd;
  border-radius: 8px;
  cursor: pointer;
  background: white;
  transition: all 0.2s;
}

.widget-add-placeholder:hover {
  border-color: #2196F3;
  background: #f3f8fc;
}

.placeholder-content {
  text-align: center;
  color: #999;
}

.placeholder-content .icon {
  font-size: 24px;
  display: block;
  margin-bottom: 5px;
}

.placeholder-content p {
  margin: 0;
  font-size: 13px;
}

.widget-panel {
  position: absolute;
  right: 20px;
  bottom: 20px;
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  width: 280px;
  max-height: 400px;
  overflow-y: auto;
}

.widget-panel h3 {
  margin: 0 0 15px 0;
  font-size: 14px;
  color: #333;
}

.property-group {
  margin-bottom: 15px;
}

.property-group label {
  display: block;
  font-size: 12px;
  color: #999;
  text-transform: uppercase;
  margin-bottom: 5px;
  font-weight: 600;
}

.property-group select,
.property-group input[type="range"] {
  width: 100%;
  padding: 6px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 13px;
}

.property-group input[type="range"] {
  padding: 0;
}

.property-group span {
  display: block;
  font-size: 12px;
  color: #666;
  margin-top: 5px;
}

.property-actions {
  margin-top: 20px;
  padding-top: 15px;
  border-top: 1px solid #eee;
}

.empty-editor {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  background: white;
}

.empty-content {
  text-align: center;
  color: #999;
}

.empty-content h2 {
  margin: 0 0 10px 0;
  color: #333;
}

/* Buttons */
.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s;
}

.btn-primary {
  background: #2196F3;
  color: white;
}

.btn-primary:hover {
  background: #1976d2;
}

.btn-secondary {
  background: #f5f5f5;
  color: #333;
  border: 1px solid #ddd;
}

.btn-secondary:hover {
  background: #e0e0e0;
}

.btn-small {
  padding: 6px 12px;
  font-size: 12px;
}

.btn-danger {
  background: #f44336;
  color: white;
}

.btn-danger:hover {
  background: #da190b;
}

/* Modals */
.modal-overlay {
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

.modal-content {
  background: white;
  border-radius: 8px;
  padding: 30px;
  max-width: 600px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-content h2 {
  margin: 0 0 20px 0;
  font-size: 20px;
  color: #333;
}

.template-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.template-card {
  padding: 15px;
  border: 1px solid #ddd;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.template-card:hover {
  border-color: #2196F3;
  background: #f3f8fc;
  box-shadow: 0 2px 8px rgba(33, 150, 243, 0.2);
}

.template-card h3 {
  margin: 0 0 5px 0;
  font-size: 14px;
  color: #333;
}

.template-card p {
  margin: 0 0 10px 0;
  font-size: 12px;
  color: #999;
}

.template-card .widget-count {
  display: block;
  font-size: 11px;
  color: #2196F3;
  font-weight: 500;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-size: 13px;
  font-weight: 500;
  color: #333;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 13px;
  font-family: inherit;
}

.form-group textarea {
  min-height: 100px;
  resize: vertical;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 25px;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

/* Alerts */
.alert {
  position: fixed;
  bottom: 20px;
  right: 20px;
  padding: 12px 16px;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 2000;
  max-width: 400px;
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
  font-size: 16px;
  color: currentColor;
  opacity: 0.7;
  margin-left: 15px;
}

.alert-close:hover {
  opacity: 1;
}
</style>
