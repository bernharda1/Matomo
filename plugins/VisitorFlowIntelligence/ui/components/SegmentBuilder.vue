<template>
  <div class="segment-builder">
    <!-- Header -->
    <div class="builder-header">
      <h2>Segment Builder</h2>
      <button v-if="!isNew" class="btn-close" @click="closeBuilder">×</button>
    </div>

    <!-- Basic Info -->
    <div class="section basic-info">
      <div class="form-group">
        <label>Segment Name</label>
        <input v-model="segmentName" type="text" placeholder="e.g., Mobile Users from Germany" maxlength="255">
      </div>
      <div class="form-group">
        <label>Description (optional)</label>
        <textarea v-model="segmentDescription" placeholder="Describe this segment..." maxlength="1000"></textarea>
      </div>
    </div>

    <!-- Rules Section -->
    <div class="section rules-section">
      <div class="section-header">
        <h3>Rules</h3>
        <select v-model="operator" class="operator-select">
          <option value="AND">ALL rules match (AND)</option>
          <option value="OR">ANY rule matches (OR)</option>
        </select>
      </div>

      <!-- Rules List -->
      <div class="rules-list">
        <div v-for="(rule, idx) in rules" :key="idx" class="rule-item" :class="{ 'can-delete': rules.length > 1 }">
          <div class="rule-operator" v-if="idx > 0">
            <span>{{ operator }}</span>
          </div>

          <div class="rule-content">
            <!-- Field Selection -->
            <select v-model="rule.field" class="rule-field" @change="onFieldChange(idx)">
              <option value="">Select field...</option>
              <option value="deviceType">Device Type</option>
              <option value="country">Country</option>
              <option value="browserName">Browser</option>
              <option value="osName">Operating System</option>
              <option value="referrerType">Referrer Type</option>
              <option value="searchKeyword">Search Keyword</option>
              <option value="customVariable">Custom Variable</option>
              <option value="visitorId">Visitor ID</option>
              <option value="visitorType">Visitor Type</option>
              <option value="visitDuration">Visit Duration</option>
              <option value="actionCount">Action Count</option>
              <option value="goalConversions">Goal Conversions</option>
            </select>

            <!-- Operator Selection -->
            <select v-model="rule.operator" class="rule-operator-select" v-if="rule.field">
              <option value="==">equals</option>
              <option value="!=">does not equal</option>
              <option value="contains">contains</option>
              <option value="not_contains">does not contain</option>
              <option value=">">greater than</option>
              <option value="<">less than</option>
              <option value=">=">greater or equal</option>
              <option value="<=">less or equal</option>
              <option value="in">in list</option>
              <option value="not_in">not in list</option>
            </select>

            <!-- Value Input -->
            <input
              v-if="isTextInput(rule.field, rule.operator)"
              v-model="rule.value"
              type="text"
              class="rule-value"
              placeholder="Enter value"
            >

            <input
              v-else-if="isNumberInput(rule.field)"
              v-model.number="rule.value"
              type="number"
              class="rule-value"
              placeholder="Enter number"
            >

            <select v-else-if="isSelectInput(rule.field)" v-model="rule.value" class="rule-value">
              <option value="">Select value...</option>
              <option v-for="opt in getFieldOptions(rule.field)" :key="opt" :value="opt">{{ opt }}</option>
            </select>

            <!-- Delete Button -->
            <button v-if="rules.length > 1" class="btn-delete-rule" @click="deleteRule(idx)">
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- Add Rule Button -->
      <button class="btn-add-rule" @click="addRule">+ Add Rule</button>
    </div>

    <!-- Preview Section -->
    <div class="section preview-section">
      <h3>Segment Query Preview</h3>
      <div class="query-preview">
        <code>{{ previewQuery }}</code>
      </div>
    </div>

    <!-- Preset Quick Select -->
    <div class="section presets-section">
      <h3>Quick Presets</h3>
      <div class="presets-grid">
        <button
          v-for="preset in presets"
          :key="preset.id"
          class="preset-btn"
          @click="applyPreset(preset)"
        >
          {{ preset.name }}
        </button>
      </div>
    </div>

    <!-- Actions -->
    <div class="section actions-section">
      <button class="btn btn-primary" @click="saveSegment" :disabled="!isValid">
        {{ isNew ? 'Create Segment' : 'Update Segment' }}
      </button>
      <button class="btn btn-secondary" @click="testSegment">Test Query</button>
      <button v-if="!isNew" class="btn btn-danger" @click="deleteSegment">Delete</button>
      <button class="btn btn-outline" @click="resetForm">Reset</button>
    </div>

    <!-- Status Messages -->
    <div v-if="successMessage" class="alert alert-success">{{ successMessage }}</div>
    <div v-if="errorMessage" class="alert alert-error">{{ errorMessage }}</div>
  </div>
</template>

<script>
export default {
  name: 'SegmentBuilder',
  props: {
    siteId: {
      type: Number,
      required: true,
    },
    segmentId: {
      type: Number,
      default: null,
    },
    apiUrl: {
      type: String,
      default: '/api',
    },
    onSave: {
      type: Function,
      default: null,
    },
    onClose: {
      type: Function,
      default: null,
    },
  },
  data() {
    return {
      isNew: true,
      segmentName: '',
      segmentDescription: '',
      operator: 'AND',
      rules: [{ field: '', operator: '==', value: '' }],
      successMessage: '',
      errorMessage: '',
      presets: [
        { id: 'mobile', name: 'Mobile Visitors' },
        { id: 'desktop', name: 'Desktop Visitors' },
        { id: 'direct', name: 'Direct Traffic' },
        { id: 'search', name: 'Search Traffic' },
      ],
      fieldOptions: {
        deviceType: ['mobile', 'tablet', 'desktop'],
        country: ['de', 'at', 'ch', 'us', 'gb', 'fr', 'it'],
        browserName: ['Chrome', 'Firefox', 'Safari', 'Edge', 'IE'],
        osName: ['Windows', 'macOS', 'Linux', 'iOS', 'Android'],
        referrerType: ['direct', 'search', 'social', 'email', 'referral'],
        visitorType: ['new', 'returning', 'returning-frequent'],
      },
    };
  },
  computed: {
    isValid() {
      if (!this.segmentName) return false;
      return this.rules.every(r => r.field && r.value);
    },
    previewQuery() {
      return this.rules
        .map(r => `${r.field}${r.operator}${r.value}`)
        .join(`;${this.operator};`);
    },
  },
  mounted() {
    if (this.segmentId) {
      this.isNew = false;
      this.loadSegment();
    }
  },
  methods: {
    addRule() {
      this.rules.push({ field: '', operator: '==', value: '' });
    },

    deleteRule(idx) {
      if (this.rules.length > 1) {
        this.rules.splice(idx, 1);
      }
    },

    onFieldChange(idx) {
      // Auto-detect operator based on field type
      const field = this.rules[idx].field;
      if (['visitDuration', 'actionCount', 'goalConversions'].includes(field)) {
        this.rules[idx].operator = '>';
        this.rules[idx].value = '';
      } else if (this.isSelectInput(field)) {
        this.rules[idx].operator = '==';
        this.rules[idx].value = '';
      }
    },

    isTextInput(field, operator) {
      const textFields = ['searchKeyword', 'customVariable', 'visitorId'];
      return textFields.includes(field);
    },

    isNumberInput(field) {
      return ['visitDuration', 'actionCount', 'goalConversions'].includes(field);
    },

    isSelectInput(field) {
      return Object.keys(this.fieldOptions).includes(field);
    },

    getFieldOptions(field) {
      return this.fieldOptions[field] || [];
    },

    applyPreset(preset) {
      // Load preset rules
      const presetRules = {
        mobile: [{ field: 'deviceType', operator: '==', value: 'mobile' }],
        desktop: [{ field: 'deviceType', operator: '==', value: 'desktop' }],
        direct: [{ field: 'referrerType', operator: '==', value: 'direct' }],
        search: [{ field: 'referrerType', operator: '==', value: 'search' }],
      };

      if (presetRules[preset.id]) {
        this.rules = JSON.parse(JSON.stringify(presetRules[preset.id]));
        this.segmentName = preset.name;
        this.segmentDescription = `Auto-generated ${preset.name} segment`;
      }
    },

    async saveSegment() {
      if (!this.isValid) return;

      try {
        const payload = {
          name: this.segmentName,
          description: this.segmentDescription,
          rules: this.rules,
          operator: this.operator,
        };

        const endpoint = this.isNew ? 'createSegment' : `updateSegment/${this.segmentId}`;
        const method = this.isNew ? 'POST' : 'PUT';

        const response = await fetch(`${this.apiUrl}/SegmentAPI.${endpoint}`, {
          method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ idSite: this.siteId, ...payload }),
        });

        if (response.ok) {
          this.successMessage = this.isNew ? 'Segment created successfully!' : 'Segment updated successfully!';
          if (this.onSave) this.onSave();
          setTimeout(() => this.resetForm(), 2000);
        } else {
          this.errorMessage = 'Failed to save segment';
        }
      } catch (error) {
        this.errorMessage = `Error: ${error.message}`;
      }
    },

    async testSegment() {
      this.successMessage = `Query test: ${this.previewQuery}`;
    },

    async deleteSegment() {
      if (confirm('Are you sure you want to delete this segment?')) {
        try {
          const response = await fetch(`${this.apiUrl}/SegmentAPI.deleteSegment/${this.segmentId}`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ idSite: this.siteId }),
          });

          if (response.ok) {
            this.successMessage = 'Segment deleted successfully!';
            if (this.onSave) this.onSave();
            setTimeout(() => this.closeBuilder(), 1500);
          }
        } catch (error) {
          this.errorMessage = `Error: ${error.message}`;
        }
      }
    },

    async loadSegment() {
      try {
        const response = await fetch(`${this.apiUrl}/SegmentAPI.getSegment/${this.segmentId}`, {
          headers: { 'Content-Type': 'application/json' },
        });

        if (response.ok) {
          const segment = await response.json();
          this.segmentName = segment.name;
          this.segmentDescription = segment.description;
          this.operator = segment.operator;
          this.rules = segment.rules;
        }
      } catch (error) {
        this.errorMessage = `Error loading segment: ${error.message}`;
      }
    },

    resetForm() {
      this.segmentName = '';
      this.segmentDescription = '';
      this.operator = 'AND';
      this.rules = [{ field: '', operator: '==', value: '' }];
      this.successMessage = '';
      this.errorMessage = '';
    },

    closeBuilder() {
      if (this.onClose) this.onClose();
    },
  },
};
</script>

<style scoped>
.segment-builder {
  max-width: 900px;
  margin: 0 auto;
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.builder-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 15px;
  border-bottom: 2px solid #eee;
}

.builder-header h2 {
  margin: 0;
  color: #333;
}

.btn-close {
  background: none;
  border: none;
  font-size: 28px;
  cursor: pointer;
  color: #999;
}

.section {
  margin-bottom: 30px;
  padding: 20px;
  background: #f9f9f9;
  border-radius: 6px;
  border: 1px solid #eee;
}

.section h3 {
  margin: 0 0 15px 0;
  font-size: 16px;
  color: #333;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.operator-select {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: #333;
  font-size: 14px;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  font-family: inherit;
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

.rules-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
  margin-bottom: 15px;
}

.rule-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 15px;
  background: white;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.rule-operator {
  min-width: 50px;
  font-weight: 600;
  color: #666;
  text-align: center;
  padding-top: 10px;
}

.rule-content {
  display: flex;
  flex: 1;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.rule-field,
.rule-operator-select,
.rule-value {
  padding: 8px 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  flex: 1;
  min-width: 120px;
}

.btn-delete-rule {
  padding: 6px 12px;
  background: #f44336;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  white-space: nowrap;
}

.btn-delete-rule:hover {
  background: #d32f2f;
}

.btn-add-rule {
  padding: 10px 16px;
  background: #4CAF50;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
}

.btn-add-rule:hover {
  background: #45a049;
}

.query-preview {
  background: #f0f0f0;
  padding: 12px;
  border-radius: 4px;
  border-left: 4px solid #2196F3;
  font-family: monospace;
  font-size: 13px;
  color: #333;
  word-break: break-all;
}

.presets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 10px;
}

.preset-btn {
  padding: 10px;
  background: #e3f2fd;
  color: #1976d2;
  border: 1px solid #90caf9;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s;
}

.preset-btn:hover {
  background: #bbdefb;
}

.actions-section {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-start;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
}

.btn-primary {
  background: #2196F3;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #1976d2;
}

.btn-primary:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.btn-secondary {
  background: #FF9800;
  color: white;
}

.btn-secondary:hover {
  background: #f57c00;
}

.btn-danger {
  background: #f44336;
  color: white;
}

.btn-danger:hover {
  background: #d32f2f;
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
  font-size: 14px;
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
</style>
