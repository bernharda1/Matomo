<template>
  <div class="preset-library">
    <!-- Header -->
    <div class="library-header">
      <h2>Preset Segment Library</h2>
      <div class="library-stats">
        <span class="stat">{{ totalPresets }} presets available</span>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="search-section">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search presets..."
        class="search-input"
      >
      <select v-model="selectedCategory" class="category-filter">
        <option value="">All Categories</option>
        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
      </select>
    </div>

    <!-- Category Tabs -->
    <div class="category-tabs">
      <button
        v-for="cat in categories"
        :key="cat"
        class="category-tab"
        :class="{ active: selectedCategory === cat }"
        @click="selectedCategory = cat"
      >
        {{ cat }} ({{ countByCategory(cat) }})
      </button>
      <button
        class="category-tab"
        :class="{ active: !selectedCategory }"
        @click="selectedCategory = ''"
      >
        All ({{ totalPresets }})
      </button>
    </div>

    <!-- Presets Grid -->
    <div class="presets-grid">
      <div
        v-for="preset in filteredPresets"
        :key="preset.id"
        class="preset-card"
        @click="selectPreset(preset)"
      >
        <div class="preset-header">
          <h3>{{ preset.name }}</h3>
          <span class="preset-category">{{ preset.category }}</span>
        </div>

        <p class="preset-description">{{ preset.description }}</p>

        <div class="preset-rules">
          <div v-for="(rule, idx) in preset.rules" :key="idx" class="rule-badge">
            <span class="rule-field">{{ rule.field }}</span>
            <span class="rule-op">{{ rule.operator }}</span>
            <span class="rule-value">{{ rule.value }}</span>
          </div>
        </div>

        <div class="preset-footer">
          <button class="btn-use" @click.stop="usePreset(preset)">Use Preset</button>
          <button class="btn-copy" @click.stop="copyQuery(preset)">Copy Query</button>
        </div>
      </div>
    </div>

    <!-- No Results -->
    <div v-if="filteredPresets.length === 0" class="no-results">
      <p>No presets found matching your criteria</p>
    </div>

    <!-- Selected Preset Details -->
    <div v-if="selectedPresetDetails" class="preset-details">
      <div class="details-header">
        <h3>{{ selectedPresetDetails.name }}</h3>
        <button class="btn-close" @click="selectedPresetDetails = null">×</button>
      </div>

      <div class="details-content">
        <div class="detail-section">
          <h4>Description</h4>
          <p>{{ selectedPresetDetails.description }}</p>
        </div>

        <div class="detail-section">
          <h4>Rules</h4>
          <table class="rules-table">
            <thead>
              <tr>
                <th>Field</th>
                <th>Operator</th>
                <th>Value</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(rule, idx) in selectedPresetDetails.rules" :key="idx">
                <td>{{ rule.field }}</td>
                <td>{{ rule.operator }}</td>
                <td><code>{{ rule.value }}</code></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="detail-section">
          <h4>Query</h4>
          <div class="query-code">
            <code>{{ buildQuery(selectedPresetDetails) }}</code>
          </div>
        </div>

        <div class="detail-actions">
          <button class="btn btn-primary" @click="usePreset(selectedPresetDetails)">
            Load into Builder
          </button>
          <button class="btn btn-secondary" @click="copyQuery(selectedPresetDetails)">
            Copy Query
          </button>
          <button class="btn btn-outline" @click="selectedPresetDetails = null">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Status Messages -->
    <div v-if="successMessage" class="alert alert-success">
      {{ successMessage }}
      <button class="alert-close" @click="successMessage = ''">×</button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PresetLibraryBrowser',
  props: {
    presets: {
      type: Array,
      required: true,
    },
    onSelectPreset: {
      type: Function,
      default: null,
    },
  },
  data() {
    return {
      searchQuery: '',
      selectedCategory: '',
      selectedPresetDetails: null,
      successMessage: '',
    };
  },
  computed: {
    filteredPresets() {
      let filtered = this.presets;

      // Filter by category
      if (this.selectedCategory) {
        filtered = filtered.filter(p => p.category === this.selectedCategory);
      }

      // Filter by search
      if (this.searchQuery) {
        const query = this.searchQuery.toLowerCase();
        filtered = filtered.filter(p =>
          p.name.toLowerCase().includes(query) ||
          p.description.toLowerCase().includes(query)
        );
      }

      return filtered;
    },

    totalPresets() {
      return this.presets.length;
    },

    categories() {
      const cats = [...new Set(this.presets.map(p => p.category))];
      return cats.sort();
    },
  },
  methods: {
    countByCategory(category) {
      return this.presets.filter(p => p.category === category).length;
    },

    selectPreset(preset) {
      this.selectedPresetDetails = preset;
    },

    usePreset(preset) {
      if (this.onSelectPreset) {
        this.onSelectPreset(preset);
      }
      this.successMessage = `Loaded preset: ${preset.name}`;
      setTimeout(() => {
        this.successMessage = '';
        this.selectedPresetDetails = null;
      }, 2000);
    },

    copyQuery(preset) {
      const query = this.buildQuery(preset);
      navigator.clipboard.writeText(query).then(() => {
        this.successMessage = 'Query copied to clipboard!';
        setTimeout(() => {
          this.successMessage = '';
        }, 2000);
      });
    },

    buildQuery(preset) {
      return preset.rules
        .map(r => `${r.field}${r.operator}${r.value}`)
        .join(`;${preset.operator};`);
    },
  },
};
</script>

<style scoped>
.preset-library {
  padding: 20px;
}

.library-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 2px solid #eee;
}

.library-header h2 {
  margin: 0;
  color: #333;
}

.library-stats {
  display: flex;
  gap: 15px;
  font-size: 14px;
  color: #666;
}

.search-section {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.search-input {
  flex: 1;
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.category-filter {
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  background: white;
}

.category-tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.category-tab {
  padding: 8px 16px;
  background: #f0f0f0;
  border: 1px solid #ddd;
  border-radius: 20px;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s;
}

.category-tab:hover {
  background: #e0e0e0;
}

.category-tab.active {
  background: #2196F3;
  color: white;
  border-color: #2196F3;
}

.presets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 15px;
  margin-bottom: 30px;
}

.preset-card {
  background: white;
  border: 1px solid #ddd;
  border-radius: 6px;
  padding: 16px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
}

.preset-card:hover {
  border-color: #2196F3;
  box-shadow: 0 2px 8px rgba(33, 150, 243, 0.2);
}

.preset-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 10px;
}

.preset-header h3 {
  margin: 0;
  font-size: 15px;
  color: #333;
}

.preset-category {
  background: #e3f2fd;
  color: #1976d2;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}

.preset-description {
  margin: 8px 0 12px;
  font-size: 13px;
  color: #666;
  line-height: 1.4;
}

.preset-rules {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.rule-badge {
  display: inline-flex;
  gap: 4px;
  background: #f5f5f5;
  padding: 4px 8px;
  border-radius: 3px;
  font-size: 11px;
}

.rule-field {
  font-weight: 600;
  color: #333;
}

.rule-op {
  color: #999;
}

.rule-value {
  color: #2196F3;
}

.preset-footer {
  display: flex;
  gap: 6px;
  margin-top: auto;
}

.btn-use,
.btn-copy {
  flex: 1;
  padding: 6px 10px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 500;
  transition: all 0.2s;
}

.btn-use {
  background: #4CAF50;
  color: white;
}

.btn-use:hover {
  background: #45a049;
}

.btn-copy {
  background: #f0f0f0;
  color: #333;
  border: 1px solid #ddd;
}

.btn-copy:hover {
  background: #e0e0e0;
}

.no-results {
  text-align: center;
  padding: 40px 20px;
  color: #999;
}

.preset-details {
  background: #f9f9f9;
  border: 1px solid #ddd;
  border-radius: 6px;
  padding: 20px;
  margin-bottom: 20px;
}

.details-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid #ddd;
}

.details-header h3 {
  margin: 0;
  color: #333;
}

.btn-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #999;
}

.details-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.detail-section h4 {
  margin: 0 0 10px 0;
  font-size: 14px;
  color: #333;
}

.rules-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.rules-table th {
  background: #f0f0f0;
  padding: 8px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.rules-table td {
  padding: 8px;
  border-bottom: 1px solid #eee;
}

.rules-table code {
  background: #f5f5f5;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: monospace;
}

.query-code {
  background: #f0f0f0;
  border-left: 4px solid #2196F3;
  padding: 12px;
  border-radius: 4px;
}

.query-code code {
  font-family: monospace;
  font-size: 12px;
  word-break: break-all;
}

.detail-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
}

.btn-primary {
  background: #2196F3;
  color: white;
}

.btn-primary:hover {
  background: #1976d2;
}

.btn-secondary {
  background: #FF9800;
  color: white;
}

.btn-secondary:hover {
  background: #f57c00;
}

.btn-outline {
  background: white;
  color: #666;
  border: 1px solid #ddd;
}

.btn-outline:hover {
  background: #f5f5f5;
}

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

.alert-close {
  background: none;
  border: none;
  cursor: pointer;
  color: currentColor;
  font-size: 18px;
}
</style>
