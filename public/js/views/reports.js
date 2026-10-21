import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * Reports & Business Analytics View
 * 
 * Provides dual financial and operational reporting:
 * 1. Sales & Profitability (daily revenue, COGS, refunds, net profit, margin %)
 * 2. Inventory Valuation (retail value, cost basis, owned vs consigned, category & brand breakdowns)
 * 
 * Includes native client-side CSV export and print-friendly A4 reports.
 */
export const ReportsPage = {
    state: {
        activeTab: 'sales', // 'sales' | 'valuation'
        startDate: '',
        endDate: '',
        categoryId: '',
        categories: [],
        salesReport: null,
        valuationReport: null,
        isLoading: false
    },

    render() {
        return `
            <div class="view-container">
                <!-- Header with Action Buttons -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold">Reports & Financial Analytics</h1>
                        <p class="text-muted text-sm">Comprehensive sales profitability, historical cost of goods sold, and live inventory asset valuations.</p>
                    </div>
                    <div class="flex gap-2 no-print">
                        <button id="export-csv-btn" class="btn btn-secondary flex items-center gap-2">
                            <span>📥</span> Export to CSV
                        </button>
                        <button id="print-report-btn" class="btn btn-primary flex items-center gap-2">
                            <span>🖨️</span> Print Report
                        </button>
                    </div>
                </div>

                <!-- Tab Navigation Strip -->
                <div class="tabs-nav flex gap-2 border-b mb-4 no-print" style="border-color: var(--border);">
                    <button class="tab-btn ${this.state.activeTab === 'sales' ? 'active' : ''}" data-tab="sales">
                        📊 Sales & Profitability
                    </button>
                    <button class="tab-btn ${this.state.activeTab === 'valuation' ? 'active' : ''}" data-tab="valuation">
                        📦 Inventory Valuation
                    </button>
                </div>

                <!-- Printable Report Area Container -->
                <div class="printable-report-area">
                    <!-- Print Only Header -->
                    <div class="print-only-header mb-4" style="display: none;">
                        <div class="flex justify-between items-center pb-2 border-b" style="border-color: #ccc;">
                            <div>
                                <h1 style="font-size: 1.5rem; font-weight: bold; margin: 0; color: #111;">VINJACK MOTORWORKS</h1>
                                <p style="font-size: 0.85rem; color: #555; margin: 2px 0 0 0;">Official Executive Management Financial Report</p>
                            </div>
                            <div style="text-align: right; font-size: 0.8rem; color: #555;">
                                <div>Generated: <strong>${new Date().toLocaleString()}</strong></div>
                                <div>Report Scope: <strong id="print-scope-text">All Data</strong></div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 1: Sales & Profitability -->
                    <div id="tab-sales" class="tab-content ${this.state.activeTab === 'sales' ? '' : 'hidden'}">
                        <!-- Filter Bar -->
                        <div class="card p-3 mb-4 no-print">
                            <form id="sales-filter-form" class="flex flex-wrap items-end gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Start Date</label>
                                    <input type="date" id="filter-start-date" class="form-input text-sm py-1 px-2" value="${this.state.startDate}">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">End Date</label>
                                    <input type="date" id="filter-end-date" class="form-input text-sm py-1 px-2" value="${this.state.endDate}">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Category</label>
                                    <select id="filter-category-id" class="form-input text-sm py-1 px-2">
                                        <option value="">All Categories</option>
                                        ${this.renderCategoryOptions()}
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="btn btn-primary text-sm py-1 px-3">
                                        Apply Filter
                                    </button>
                                    <button type="button" id="reset-sales-filter-btn" class="btn btn-secondary text-sm py-1 px-3">
                                        Reset
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Financial KPI Cards -->
                        <div class="grid grid-cols-4 gap-3 mb-4">
                            <div class="metric-card border-warning">
                                <span class="metric-label">Gross Sales Revenue</span>
                                <div class="metric-value font-mono text-warning" id="sales-rep-gross">₱0.00</div>
                                <span class="text-xs text-muted mt-1 block" id="sales-rep-transactions">0 transactions</span>
                            </div>
                            <div class="metric-card" style="border-left-color: var(--info);">
                                <span class="metric-label">Cost of Goods Sold (COGS)</span>
                                <div class="metric-value font-mono" style="color: var(--info);" id="sales-rep-cogs">₱0.00</div>
                                <span class="text-xs text-muted mt-1 block">Acquisition cost basis</span>
                            </div>
                            <div class="metric-card border-danger">
                                <span class="metric-label">Customer Refunds</span>
                                <div class="metric-value font-mono text-danger" id="sales-rep-refunds">₱0.00</div>
                                <span class="text-xs text-muted mt-1 block">Processed warranty/returns</span>
                            </div>
                            <div class="metric-card border-success">
                                <span class="metric-label">Net Gross Profit & Margin</span>
                                <div class="metric-value font-mono text-success" id="sales-rep-net-profit">₱0.00</div>
                                <span class="text-xs text-muted mt-1 block font-mono" id="sales-rep-margin">Margin: 0.0%</span>
                            </div>
                        </div>

                        <!-- Tabular Daily Financial Summary -->
                        <div class="card p-0 mb-4">
                            <div class="p-3 border-b flex justify-between items-center" style="border-color: var(--border);">
                                <h2 class="text-sm font-bold uppercase tracking-wider">Daily Financial Performance Breakdown</h2>
                                <span class="text-xs text-muted" id="sales-record-count">0 days recorded</span>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table" id="sales-report-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th class="text-center">Tickets</th>
                                            <th class="text-center">Units Sold</th>
                                            <th class="text-right">Parts Sales</th>
                                            <th class="text-right">Services</th>
                                            <th class="text-right">Gross Revenue</th>
                                            <th class="text-right">COGS</th>
                                            <th class="text-right">Refunds</th>
                                            <th class="text-right">Net Revenue</th>
                                            <th class="text-right">Net Profit</th>
                                            <th class="text-right">Margin %</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sales-report-tbody">
                                        <tr>
                                            <td colspan="11" class="text-center py-4 text-muted">Loading sales report records...</td>
                                        </tr>
                                    </tbody>
                                    <tfoot id="sales-report-tfoot" class="totals-tfoot">
                                        <!-- Grand totals row -->
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: Inventory Valuation -->
                    <div id="tab-valuation" class="tab-content ${this.state.activeTab === 'valuation' ? '' : 'hidden'}">
                        <!-- Valuation Summary KPIs -->
                        <div class="grid grid-cols-4 gap-3 mb-4">
                            <div class="metric-card border-warning">
                                <span class="metric-label">Total Retail Value</span>
                                <div class="metric-value font-mono text-warning" id="val-retail-value">₱0.00</div>
                                <span class="text-xs text-muted mt-1 block" id="val-total-units">0 total units in warehouse</span>
                            </div>
                            <div class="metric-card" style="border-left-color: var(--info);">
                                <span class="metric-label">Total Inventory Cost Basis</span>
                                <div class="metric-value font-mono" style="color: var(--info);" id="val-cost-basis">₱0.00</div>
                                <span class="text-xs text-muted mt-1 block" id="val-skus-count">0 active SKUs</span>
                            </div>
                            <div class="metric-card border-success">
                                <span class="metric-label">Potential Gross Profit</span>
                                <div class="metric-value font-mono text-success" id="val-potential-profit">₱0.00</div>
                                <span class="text-xs text-muted mt-1 block" id="val-potential-margin">Projected margin: 0.0%</span>
                            </div>
                            <div class="metric-card">
                                <span class="metric-label">Owned vs Consigned Split</span>
                                <div class="metric-value font-mono text-sm mt-1" id="val-split-owned" style="color: #60a5fa;">Owned: ₱0.00</div>
                                <div class="text-xs font-mono mt-1" id="val-split-consigned" style="color: var(--primary);">Consigned: ₱0.00</div>
                            </div>
                        </div>

                        <!-- Category Breakdown Table -->
                        <div class="card p-0 mb-4">
                            <div class="p-3 border-b flex justify-between items-center" style="border-color: var(--border);">
                                <h2 class="text-sm font-bold uppercase tracking-wider">Asset Valuation by Category</h2>
                                <span class="text-xs text-muted">Stock value grouped by product taxonomy</span>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table" id="valuation-cat-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th class="text-center">Active SKUs</th>
                                            <th class="text-center">Units in Stock</th>
                                            <th class="text-right">Cost Basis</th>
                                            <th class="text-right">Retail Value</th>
                                            <th class="text-right">Potential Profit</th>
                                            <th class="text-right">Projected Margin</th>
                                        </tr>
                                    </thead>
                                    <tbody id="valuation-cat-tbody">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">Loading valuation by category...</td>
                                        </tr>
                                    </tbody>
                                    <tfoot id="valuation-cat-tfoot" class="totals-tfoot">
                                        <!-- Category grand totals -->
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Brand Breakdown Table -->
                        <div class="card p-0 mb-4">
                            <div class="p-3 border-b flex justify-between items-center" style="border-color: var(--border);">
                                <h2 class="text-sm font-bold uppercase tracking-wider">Asset Valuation by Brand / Manufacturer</h2>
                                <span class="text-xs text-muted">Stock investment distribution across brands</span>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table" id="valuation-brand-table">
                                    <thead>
                                        <tr>
                                            <th>Brand / Manufacturer</th>
                                            <th class="text-center">Active SKUs</th>
                                            <th class="text-center">Units in Stock</th>
                                            <th class="text-right">Cost Basis</th>
                                            <th class="text-right">Retail Value</th>
                                            <th class="text-right">Potential Profit</th>
                                            <th class="text-right">Projected Margin</th>
                                        </tr>
                                    </thead>
                                    <tbody id="valuation-brand-tbody">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">Loading valuation by brand...</td>
                                        </tr>
                                    </tbody>
                                    <tfoot id="valuation-brand-tfoot" class="totals-tfoot">
                                        <!-- Brand grand totals -->
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    renderCategoryOptions() {
        return this.state.categories.map(cat => `
            <option value="${cat.id}" ${this.state.categoryId == cat.id ? 'selected' : ''}>
                ${cat.name}
            </option>
        `).join('');
    },

    async init() {
        this.setupEventListeners();
        await this.loadCategories();
        await this.loadReports();
    },

    setupEventListeners() {
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.currentTarget.getAttribute('data-tab');
                this.switchTab(tab);
            });
        });

        // Sales Filter Form
        const filterForm = document.getElementById('sales-filter-form');
        filterForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.state.startDate = document.getElementById('filter-start-date').value;
            this.state.endDate = document.getElementById('filter-end-date').value;
            this.state.categoryId = document.getElementById('filter-category-id').value;
            this.loadSalesReport();
        });

        // Reset Filter
        document.getElementById('reset-sales-filter-btn')?.addEventListener('click', () => {
            this.state.startDate = '';
            this.state.endDate = '';
            this.state.categoryId = '';
            document.getElementById('filter-start-date').value = '';
            document.getElementById('filter-end-date').value = '';
            document.getElementById('filter-category-id').value = '';
            this.loadSalesReport();
        });

        // Export CSV button
        document.getElementById('export-csv-btn')?.addEventListener('click', () => {
            this.handleExportCSV();
        });

        // Print Report button
        document.getElementById('print-report-btn')?.addEventListener('click', () => {
            this.handlePrintReport();
        });
    },

    switchTab(tab) {
        this.state.activeTab = tab;

        document.querySelectorAll('.tab-btn').forEach(btn => {
            if (btn.getAttribute('data-tab') === tab) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        const salesTab = document.getElementById('tab-sales');
        const valTab = document.getElementById('tab-valuation');

        if (tab === 'sales') {
            salesTab?.classList.remove('hidden');
            valTab?.classList.add('hidden');
            if (!this.state.salesReport) {
                this.loadSalesReport();
            }
        } else {
            salesTab?.classList.add('hidden');
            valTab?.classList.remove('hidden');
            if (!this.state.valuationReport) {
                this.loadValuationReport();
            }
        }
    },

    async loadCategories() {
        try {
            const res = await apiClient.get('categories');
            if (res.success && Array.isArray(res.data)) {
                this.state.categories = res.data;
                const select = document.getElementById('filter-category-id');
                if (select) {
                    select.innerHTML = `<option value="">All Categories</option>` + this.renderCategoryOptions();
                }
            }
        } catch (err) {
            console.error('Failed to load categories', err);
        }
    },

    async loadReports() {
        if (this.state.activeTab === 'sales') {
            await this.loadSalesReport();
            // preload valuation in background
            this.loadValuationReport();
        } else {
            await this.loadValuationReport();
            this.loadSalesReport();
        }
    },

    async loadSalesReport() {
        const tbody = document.getElementById('sales-report-tbody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted">Refreshing sales report...</td></tr>`;
        }

        try {
            const params = {};
            if (this.state.startDate) params.start_date = this.state.startDate;
            if (this.state.endDate) params.end_date = this.state.endDate;
            if (this.state.categoryId) params.category_id = this.state.categoryId;

            const res = await apiClient.get('reports/sales', params);
            if (res.success && res.data) {
                this.state.salesReport = res.data;
                this.renderSalesData(res.data);
            }
        } catch (err) {
            console.error('Failed to load sales report', err);
            State.setAlert('Failed to load sales report data', 'danger');
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger font-bold">Failed to load sales data: ${err.message}</td></tr>`;
            }
        }
    },

    renderSalesData(data) {
        const records = data.records || [];
        const totals = data.totals || {};

        // 1. Update KPI Cards
        const grossEl = document.getElementById('sales-rep-gross');
        const cogsEl = document.getElementById('sales-rep-cogs');
        const refEl = document.getElementById('sales-rep-refunds');
        const netEl = document.getElementById('sales-rep-net-profit');
        const marginEl = document.getElementById('sales-rep-margin');
        const txEl = document.getElementById('sales-rep-transactions');
        const countEl = document.getElementById('sales-record-count');

        if (grossEl) grossEl.textContent = this.formatCurrency(totals.gross_revenue || 0);
        if (cogsEl) cogsEl.textContent = this.formatCurrency(totals.total_cogs || 0);
        if (refEl) refEl.textContent = this.formatCurrency(totals.total_refunds || 0);
        if (netEl) netEl.textContent = this.formatCurrency(totals.gross_profit || 0);
        if (marginEl) marginEl.textContent = `Overall Margin: ${(totals.profit_margin_percent || 0).toFixed(1)}%`;
        if (txEl) txEl.textContent = `${totals.transaction_count || 0} tickets | ${totals.total_units_sold || 0} items`;
        if (countEl) countEl.textContent = `${records.length} date entries`;

        // 2. Render Table Body
        const tbody = document.getElementById('sales-report-tbody');
        if (!tbody) return;

        if (records.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-6 text-muted">
                        No sales transactions found for the specified period and filters.
                    </td>
                </tr>
            `;
        } else {
            tbody.innerHTML = records.map(r => `
                <tr class="hover:bg-surface">
                    <td class="font-mono text-sm font-semibold">${r.sale_date}</td>
                    <td class="text-center font-mono">${r.transaction_count}</td>
                    <td class="text-center font-mono">${r.total_units_sold}</td>
                    <td class="text-right font-mono">${this.formatCurrency(r.items_revenue)}</td>
                    <td class="text-right font-mono">${this.formatCurrency(r.services_revenue)}</td>
                    <td class="text-right font-mono font-bold text-warning">${this.formatCurrency(r.gross_revenue)}</td>
                    <td class="text-right font-mono" style="color: var(--info);">${this.formatCurrency(r.total_cogs)}</td>
                    <td class="text-right font-mono text-danger">${r.total_refunds > 0 ? '-' + this.formatCurrency(r.total_refunds) : '₱0.00'}</td>
                    <td class="text-right font-mono">${this.formatCurrency(r.net_revenue)}</td>
                    <td class="text-right font-mono font-bold ${r.gross_profit >= 0 ? 'text-success' : 'text-danger'}">
                        ${this.formatCurrency(r.gross_profit)}
                    </td>
                    <td class="text-right font-mono font-semibold">
                        ${r.profit_margin_percent.toFixed(1)}%
                    </td>
                </tr>
            `).join('');
        }

        // 3. Render Grand Totals in Footer
        const tfoot = document.getElementById('sales-report-tfoot');
        if (tfoot && records.length > 0) {
            tfoot.innerHTML = `
                <tr class="font-bold border-t-2" style="background-color: var(--bg-surface); border-color: var(--border);">
                    <td class="uppercase tracking-wider font-bold">Grand Totals</td>
                    <td class="text-center font-mono">${totals.transaction_count || 0}</td>
                    <td class="text-center font-mono">${totals.total_units_sold || 0}</td>
                    <td class="text-right font-mono">-</td>
                    <td class="text-right font-mono">-</td>
                    <td class="text-right font-mono text-warning">${this.formatCurrency(totals.gross_revenue || 0)}</td>
                    <td class="text-right font-mono" style="color: var(--info);">${this.formatCurrency(totals.total_cogs || 0)}</td>
                    <td class="text-right font-mono text-danger">${(totals.total_refunds || 0) > 0 ? '-' + this.formatCurrency(totals.total_refunds || 0) : '₱0.00'}</td>
                    <td class="text-right font-mono">${this.formatCurrency(totals.net_revenue || 0)}</td>
                    <td class="text-right font-mono text-success">${this.formatCurrency(totals.gross_profit || 0)}</td>
                    <td class="text-right font-mono">${(totals.profit_margin_percent || 0).toFixed(1)}%</td>
                </tr>
            `;
        } else if (tfoot) {
            tfoot.innerHTML = '';
        }
    },

    async loadValuationReport() {
        const catTbody = document.getElementById('valuation-cat-tbody');
        const brandTbody = document.getElementById('valuation-brand-tbody');

        if (catTbody) catTbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">Calculating inventory valuation...</td></tr>`;
        if (brandTbody) brandTbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">Calculating inventory valuation...</td></tr>`;

        try {
            const res = await apiClient.get('reports/inventory-valuation');
            if (res.success && res.data) {
                this.state.valuationReport = res.data;
                this.renderValuationData(res.data);
            }
        } catch (err) {
            console.error('Failed to load valuation report', err);
            State.setAlert('Failed to load inventory valuation', 'danger');
        }
    },

    renderValuationData(data) {
        const summary = data.summary || {};
        const categories = data.categories || [];
        const brands = data.brands || [];

        // 1. KPI Cards
        const retEl = document.getElementById('val-retail-value');
        const costEl = document.getElementById('val-cost-basis');
        const profitEl = document.getElementById('val-potential-profit');
        const marginEl = document.getElementById('val-potential-margin');
        const unitsEl = document.getElementById('val-total-units');
        const skusEl = document.getElementById('val-skus-count');
        const ownedEl = document.getElementById('val-split-owned');
        const consignedEl = document.getElementById('val-split-consigned');

        if (retEl) retEl.textContent = this.formatCurrency(summary.total_retail_value || 0);
        if (costEl) costEl.textContent = this.formatCurrency(summary.total_cost_basis || 0);
        if (profitEl) profitEl.textContent = this.formatCurrency(summary.potential_gross_profit || 0);
        if (marginEl) marginEl.textContent = `Projected Margin: ${(summary.potential_margin || 0).toFixed(1)}%`;
        if (unitsEl) unitsEl.textContent = `${(summary.total_units || 0).toLocaleString()} warehouse physical units`;
        if (skusEl) skusEl.textContent = `${summary.total_skus || 0} active product lines`;
        if (ownedEl) ownedEl.textContent = `Owned Stock Cost: ${this.formatCurrency(summary.owned_cost_basis || 0)}`;
        if (consignedEl) consignedEl.textContent = `Consigned Cost: ${this.formatCurrency(summary.consigned_cost_basis || 0)} (${(summary.consigned_units || 0).toLocaleString()} units)`;

        // 2. Category Table
        const catTbody = document.getElementById('valuation-cat-tbody');
        if (catTbody) {
            if (categories.length === 0) {
                catTbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">No inventory category assets found.</td></tr>`;
            } else {
                catTbody.innerHTML = categories.map(c => {
                    const margin = c.retail_value > 0 ? ((c.potential_profit / c.retail_value) * 100).toFixed(1) : '0.0';
                    return `
                        <tr class="hover:bg-surface">
                            <td class="font-bold text-sm">${c.category_name}</td>
                            <td class="text-center font-mono">${c.sku_count}</td>
                            <td class="text-center font-mono">${c.total_units.toLocaleString()}</td>
                            <td class="text-right font-mono" style="color: var(--info);">${this.formatCurrency(c.cost_basis)}</td>
                            <td class="text-right font-mono font-bold text-warning">${this.formatCurrency(c.retail_value)}</td>
                            <td class="text-right font-mono font-bold text-success">${this.formatCurrency(c.potential_profit)}</td>
                            <td class="text-right font-mono font-semibold">${margin}%</td>
                        </tr>
                    `;
                }).join('');
            }
        }

        // Category Totals
        const catTfoot = document.getElementById('valuation-cat-tfoot');
        if (catTfoot && categories.length > 0) {
            catTfoot.innerHTML = `
                <tr class="font-bold border-t-2" style="background-color: var(--bg-surface); border-color: var(--border);">
                    <td class="uppercase tracking-wider font-bold">Category Totals</td>
                    <td class="text-center font-mono">${summary.total_skus || 0}</td>
                    <td class="text-center font-mono">${(summary.total_units || 0).toLocaleString()}</td>
                    <td class="text-right font-mono" style="color: var(--info);">${this.formatCurrency(summary.total_cost_basis || 0)}</td>
                    <td class="text-right font-mono text-warning">${this.formatCurrency(summary.total_retail_value || 0)}</td>
                    <td class="text-right font-mono text-success">${this.formatCurrency(summary.potential_gross_profit || 0)}</td>
                    <td class="text-right font-mono">${(summary.potential_margin || 0).toFixed(1)}%</td>
                </tr>
            `;
        }

        // 3. Brand Table
        const brandTbody = document.getElementById('valuation-brand-tbody');
        if (brandTbody) {
            if (brands.length === 0) {
                brandTbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">No inventory brand assets found.</td></tr>`;
            } else {
                brandTbody.innerHTML = brands.map(b => {
                    const margin = b.retail_value > 0 ? ((b.potential_profit / b.retail_value) * 100).toFixed(1) : '0.0';
                    return `
                        <tr class="hover:bg-surface">
                            <td class="font-bold text-sm">${b.brand_name || 'Generic / Unbranded'}</td>
                            <td class="text-center font-mono">${b.sku_count}</td>
                            <td class="text-center font-mono">${b.total_units.toLocaleString()}</td>
                            <td class="text-right font-mono" style="color: var(--info);">${this.formatCurrency(b.cost_basis)}</td>
                            <td class="text-right font-mono font-bold text-warning">${this.formatCurrency(b.retail_value)}</td>
                            <td class="text-right font-mono font-bold text-success">${this.formatCurrency(b.potential_profit)}</td>
                            <td class="text-right font-mono font-semibold">${margin}%</td>
                        </tr>
                    `;
                }).join('');
            }
        }

        // Brand Totals
        const brandTfoot = document.getElementById('valuation-brand-tfoot');
        if (brandTfoot && brands.length > 0) {
            brandTfoot.innerHTML = `
                <tr class="font-bold border-t-2" style="background-color: var(--bg-surface); border-color: var(--border);">
                    <td class="uppercase tracking-wider font-bold">Brand Totals</td>
                    <td class="text-center font-mono">${summary.total_skus || 0}</td>
                    <td class="text-center font-mono">${(summary.total_units || 0).toLocaleString()}</td>
                    <td class="text-right font-mono" style="color: var(--info);">${this.formatCurrency(summary.total_cost_basis || 0)}</td>
                    <td class="text-right font-mono text-warning">${this.formatCurrency(summary.total_retail_value || 0)}</td>
                    <td class="text-right font-mono text-success">${this.formatCurrency(summary.potential_gross_profit || 0)}</td>
                    <td class="text-right font-mono">${(summary.potential_margin || 0).toFixed(1)}%</td>
                </tr>
            `;
        }
    },

    /**
     * Native Client-Side CSV Export (RFC-compliant with UTF-8 BOM)
     */
    handleExportCSV() {
        const today = new Date().toISOString().split('T')[0];

        if (this.state.activeTab === 'sales') {
            if (!this.state.salesReport || !this.state.salesReport.records) {
                State.setAlert('No sales report data available to export', 'warning');
                return;
            }

            const records = this.state.salesReport.records;
            const totals = this.state.salesReport.totals || {};

            const csvRows = [
                ['VINJACK MOTORWORKS - SALES & PROFITABILITY REPORT'],
                ['Exported Date', new Date().toLocaleString()],
                ['Date Range', `${this.state.startDate || 'All Past'} to ${this.state.endDate || 'Today'}`],
                [],
                [
                    'Sale Date',
                    'Transactions',
                    'Units Sold',
                    'Parts Revenue (PHP)',
                    'Services Revenue (PHP)',
                    'Gross Revenue (PHP)',
                    'COGS (PHP)',
                    'Refunds (PHP)',
                    'Net Revenue (PHP)',
                    'Net Profit (PHP)',
                    'Margin (%)'
                ]
            ];

            records.forEach(r => {
                csvRows.push([
                    r.sale_date,
                    r.transaction_count,
                    r.total_units_sold,
                    r.items_revenue.toFixed(2),
                    r.services_revenue.toFixed(2),
                    r.gross_revenue.toFixed(2),
                    r.total_cogs.toFixed(2),
                    r.total_refunds.toFixed(2),
                    r.net_revenue.toFixed(2),
                    r.gross_profit.toFixed(2),
                    r.profit_margin_percent.toFixed(2)
                ]);
            });

            // Grand totals
            csvRows.push([]);
            csvRows.push([
                'GRAND TOTALS',
                totals.transaction_count || 0,
                totals.total_units_sold || 0,
                '',
                '',
                (totals.gross_revenue || 0).toFixed(2),
                (totals.total_cogs || 0).toFixed(2),
                (totals.total_refunds || 0).toFixed(2),
                (totals.net_revenue || 0).toFixed(2),
                (totals.gross_profit || 0).toFixed(2),
                (totals.profit_margin_percent || 0).toFixed(2)
            ]);

            this.downloadCSV(`vinjack_sales_report_${today}.csv`, csvRows);
            State.setAlert('Sales report exported to CSV successfully', 'success');

        } else {
            // Inventory Valuation Export
            if (!this.state.valuationReport) {
                State.setAlert('No inventory valuation data available to export', 'warning');
                return;
            }

            const categories = this.state.valuationReport.categories || [];
            const summary = this.state.valuationReport.summary || {};

            const csvRows = [
                ['VINJACK MOTORWORKS - INVENTORY VALUATION REPORT'],
                ['Exported Date', new Date().toLocaleString()],
                ['Total Warehoused Units', summary.total_units || 0],
                ['Total Cost Basis (PHP)', (summary.total_cost_basis || 0).toFixed(2)],
                ['Total Retail Value (PHP)', (summary.total_retail_value || 0).toFixed(2)],
                ['Potential Gross Profit (PHP)', (summary.potential_gross_profit || 0).toFixed(2)],
                ['Projected Margin (%)', (summary.potential_margin || 0).toFixed(2)],
                ['Owned Stock Cost (PHP)', (summary.owned_cost_basis || 0).toFixed(2)],
                ['Consigned Stock Cost (PHP)', (summary.consigned_cost_basis || 0).toFixed(2)],
                [],
                [
                    'Category',
                    'Active SKUs',
                    'Units In Stock',
                    'Cost Basis (PHP)',
                    'Retail Value (PHP)',
                    'Potential Profit (PHP)',
                    'Projected Margin (%)'
                ]
            ];

            categories.forEach(c => {
                const margin = c.retail_value > 0 ? ((c.potential_profit / c.retail_value) * 100).toFixed(2) : '0.00';
                csvRows.push([
                    c.category_name,
                    c.sku_count,
                    c.total_units,
                    c.cost_basis.toFixed(2),
                    c.retail_value.toFixed(2),
                    c.potential_profit.toFixed(2),
                    margin
                ]);
            });

            csvRows.push([]);
            csvRows.push([
                'TOTALS',
                summary.total_skus || 0,
                summary.total_units || 0,
                (summary.total_cost_basis || 0).toFixed(2),
                (summary.total_retail_value || 0).toFixed(2),
                (summary.potential_gross_profit || 0).toFixed(2),
                (summary.potential_margin || 0).toFixed(2)
            ]);

            this.downloadCSV(`vinjack_inventory_valuation_${today}.csv`, csvRows);
            State.setAlert('Inventory valuation exported to CSV successfully', 'success');
        }
    },

    downloadCSV(filename, rows) {
        const csvContent = rows.map(e => e.map(val => {
            let text = (val === null || val === undefined) ? '' : String(val);
            text = text.replace(/"/g, '""');
            if (text.search(/("|,|\n)/g) >= 0) {
                text = `"${text}"`;
            }
            return text;
        }).join(',')).join('\r\n');

        const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    },

    /**
     * Browser Native Clean Print Report
     */
    handlePrintReport() {
        const scopeText = document.getElementById('print-scope-text');
        if (scopeText) {
            if (this.state.activeTab === 'sales') {
                const start = this.state.startDate || 'Beginning';
                const end = this.state.endDate || 'Present';
                scopeText.textContent = `Sales & Profitability (${start} to ${end})`;
            } else {
                scopeText.textContent = 'Inventory Valuation & Asset Summary';
            }
        }

        window.print();
    },

    formatCurrency(val) {
        const num = parseFloat(val) || 0;
        return '₱' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
};
