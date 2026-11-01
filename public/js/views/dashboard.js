import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * Executive Dashboard View
 * 
 * Recreated pixel-perfect to match vinjacksystem.online/dashboard:
 * 1. Metric Cards (Revenue, Profit [restricted to canViewReports], Sales, Items Sold, Stock Units, SKUs)
 * 2. Revenue Trend Chart Area (visible across all roles with canViewDashboard)
 * 3. Top 5 Selling Products and Top 5 Selling Services
 */
export const DashboardPage = {
    state: {
        range: 'all', // 'today', 'week', 'month', 'all'
        overview: null,
        topSelling: { products: [], services: [] }
    },

    /**
     * Check whether the active user holds higher-level financial reporting privileges.
     * Super Admin bypasses automatically via State.hasPermission().
     */
    canViewFinancials() {
        return State.hasPermission('canViewReports');
    },

    render() {
        const rangeLabels = {
            'today': 'Today',
            'week': 'This Week',
            'month': 'This Month',
            'all': 'All Time'
        };
        const currentTrendLabel = rangeLabels[this.state.range] || 'All Time';
        const showFinancials = this.canViewFinancials();
        const metricsGridClass = showFinancials ? 'grid grid-cols-6 gap-3 mb-4' : 'grid gap-3 mb-4';
        const metricsGridStyle = showFinancials ? '' : 'style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));"';

        return `
            <div class="view-container p-4">
                <!-- Page Header with Date Filter -->
                <div class="page-header flex flex-col md:flex-row md:items-center md:justify-between items-start gap-4 mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold" style="color: #111827; margin-bottom: 2px;">Dashboard</h1>
                        <p class="text-muted text-sm" style="color: #6b7280;">Overview of your business performance</p>
                    </div>
                    <div class="date-range-pills flex flex-wrap w-full md:w-auto">
                        <button class="pill-btn ${this.state.range === 'today' ? 'active' : ''}" data-range="today">Today</button>
                        <button class="pill-btn ${this.state.range === 'week' ? 'active' : ''}" data-range="week">Week</button>
                        <button class="pill-btn ${this.state.range === 'month' ? 'active' : ''}" data-range="month">Month</button>
                        <button class="pill-btn ${this.state.range === 'all' ? 'active' : ''}" data-range="all">All Time</button>
                    </div>
                </div>

                <!-- 1. Top Row: Metric Cards (Net Profit hidden if user lacks canViewReports) -->
                <div class="${metricsGridClass}" ${metricsGridStyle}>
                    <!-- Card 1: TOTAL REVENUE (Blue) -->
                    <div class="metric-card border-blue">
                        <div class="metric-content flex items-center gap-3">
                            <div class="metric-icon-box icon-blue">
                                <span>💵</span>
                            </div>
                            <div>
                                <span class="metric-label">TOTAL REVENUE</span>
                                <div class="metric-value font-mono" id="dash-gross-revenue">₱0</div>
                            </div>
                        </div>
                    </div>

                    ${showFinancials ? `
                    <!-- Card 2: TOTAL PROFIT (Teal / Cyan) - Restricted to canViewReports -->
                    <div class="metric-card border-teal" id="dash-profit-card">
                        <div class="metric-content flex items-center gap-3">
                            <div class="metric-icon-box icon-teal">
                                <span>🪙</span>
                            </div>
                            <div>
                                <span class="metric-label">TOTAL PROFIT</span>
                                <div class="metric-value font-mono" id="dash-net-profit">₱0</div>
                            </div>
                        </div>
                    </div>
                    ` : ''}

                    <!-- Card 3: TOTAL SALES (Green) -->
                    <div class="metric-card border-green">
                        <div class="metric-content flex items-center gap-3">
                            <div class="metric-icon-box icon-green">
                                <span>🛒</span>
                            </div>
                            <div>
                                <span class="metric-label">TOTAL SALES</span>
                                <div class="metric-value font-mono" id="dash-ticket-count">0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 4: ITEMS SOLD (Orange) -->
                    <div class="metric-card border-orange">
                        <div class="metric-content flex items-center gap-3">
                            <div class="metric-icon-box icon-orange">
                                <span>🛍️</span>
                            </div>
                            <div>
                                <span class="metric-label">ITEMS SOLD</span>
                                <div class="metric-value font-mono" id="dash-items-sold">0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 5: STOCK UNITS (Red) -->
                    <div class="metric-card border-red">
                        <div class="metric-content flex items-center gap-3">
                            <div class="metric-icon-box icon-red">
                                <span>🏬</span>
                            </div>
                            <div>
                                <span class="metric-label">STOCK UNITS</span>
                                <div class="metric-value font-mono" id="dash-total-units">0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 6: PRODUCTS (SKUS) (Purple) -->
                    <div class="metric-card border-purple">
                        <div class="metric-content flex items-center gap-3">
                            <div class="metric-icon-box icon-purple">
                                <span>📦</span>
                            </div>
                            <div>
                                <span class="metric-label">PRODUCTS (SKUS)</span>
                                <div class="metric-value font-mono" id="dash-total-skus">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Middle Row: Revenue Trend Card (Visible across all Dashboard roles) -->
                <div class="card p-4 mb-4" id="dash-trend-card">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="trend-badge green-badge">
                            <span>📈</span>
                        </div>
                        <h2 class="text-sm font-bold" style="color: #1f2937;" id="trend-title">
                            Revenue Trend (${currentTrendLabel})
                        </h2>
                    </div>
                    <div id="dash-trend-container" style="min-height: 240px; display: flex; align-items: center; justify-content: center;">
                        <div class="empty-trend-placeholder text-center text-muted py-8">
                            <div class="trend-placeholder-icon mb-2">
                                <svg width="60" height="30" viewBox="0 0 60 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 25L25 15L38 22L55 5" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <p class="text-xs text-muted font-medium" style="color: #9ca3af;">No sales trend data available.</p>
                        </div>
                    </div>
                </div>

                <!-- 3. Bottom Row: Top 5 Selling Products & Top 5 Selling Services -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <!-- Left: Top 5 Selling Products -->
                    <div class="card p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="trend-badge blue-badge">
                                <span>📊</span>
                            </div>
                            <h2 class="text-sm font-bold" style="color: #1f2937;">Top 5 Selling Products</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table text-xs" id="top-products-table">
                                <thead>
                                    <tr>
                                        <th>Product / SKU</th>
                                        <th class="text-center">Units Sold</th>
                                        <th class="text-right">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody id="top-products-tbody">
                                    <tr><td colspan="3" class="text-center py-6 text-muted" style="color: #9ca3af;">No data available.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right: Top 5 Selling Services -->
                    <div class="card p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="trend-badge cyan-badge">
                                <span>🔧</span>
                            </div>
                            <h2 class="text-sm font-bold" style="color: #1f2937;">Top 5 Selling Services</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table text-xs" id="top-services-table">
                                <thead>
                                    <tr>
                                        <th>Service Name</th>
                                        <th class="text-right">Count</th>
                                    </tr>
                                </thead>
                                <tbody id="top-services-tbody">
                                    <tr><td colspan="2" class="text-center py-6 text-muted" style="color: #9ca3af;">No data available.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    async init() {
        this.bindEvents();
        await this.loadDashboardData();
    },

    bindEvents() {
        // Date range buttons
        document.querySelectorAll('.date-range-pills .pill-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.date-range-pills .pill-btn').forEach(b => b.classList.remove('active'));
                e.currentTarget.classList.add('active');
                this.state.range = e.currentTarget.dataset.range;

                const rangeLabels = {
                    'today': 'Today',
                    'week': 'This Week',
                    'month': 'This Month',
                    'all': 'All Time'
                };
                const trendTitleEl = document.getElementById('trend-title');
                if (trendTitleEl) {
                    trendTitleEl.textContent = `Revenue Trend (${rangeLabels[this.state.range] || 'All Time'})`;
                }

                this.loadDashboardData();
            });
        });
    },

    getDateRangeParams() {
        const now = new Date();
        const formatDate = (d) => d.toISOString().split('T')[0];

        switch (this.state.range) {
            case 'today': {
                const dStr = formatDate(now);
                return { start_date: dStr, end_date: dStr };
            }
            case 'week': {
                const past = new Date();
                past.setDate(past.getDate() - 7);
                return { start_date: formatDate(past), end_date: formatDate(now) };
            }
            case 'month': {
                const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                return { start_date: formatDate(firstDay), end_date: formatDate(now) };
            }
            case 'all':
            default:
                return {};
        }
    },

    async loadDashboardData() {
        const params = this.getDateRangeParams();

        try {
            // Concurrent fetch of overview, top-selling, and revenue trend (all permitted via canViewDashboard)
            const [overviewOutcome, topSellingOutcome, salesTrendOutcome] = await Promise.allSettled([
                apiClient.get('reports/dashboard', params),
                apiClient.get('reports/top-selling', { ...params, limit: 5 }),
                apiClient.get('reports/sales', params)
            ]);

            if (overviewOutcome.status === 'fulfilled') {
                const overviewRes = overviewOutcome.value;
                if (overviewRes && overviewRes.success && overviewRes.data) {
                    this.state.overview = overviewRes.data;
                    this.renderOverview(overviewRes.data);
                }
            } else {
                console.error('Failed to load dashboard overview:', overviewOutcome.reason);
                State.setAlert('Failed to refresh dashboard performance metrics', 'danger');
            }

            if (topSellingOutcome.status === 'fulfilled') {
                const topSellingRes = topSellingOutcome.value;
                if (topSellingRes && topSellingRes.success && topSellingRes.data) {
                    this.state.topSelling = topSellingRes.data;
                    this.renderTopSelling(topSellingRes.data);
                }
            } else {
                console.error('Failed to load top-selling metrics:', topSellingOutcome.reason);
            }

            // Render Revenue Trend chart unconditionally across all roles, falling back cleanly if empty
            if (salesTrendOutcome.status === 'fulfilled') {
                const salesRes = salesTrendOutcome.value;
                const records = Array.isArray(salesRes?.data?.records) ? salesRes.data.records : [];
                this.renderRevenueTrendChart(records);
            } else {
                console.warn('Failed to load revenue trend data:', salesTrendOutcome.reason);
                this.renderRevenueTrendChart([]);
            }

        } catch (err) {
            console.error('Failed to load dashboard data:', err);
            this.renderRevenueTrendChart([]);
            State.setAlert('Failed to refresh dashboard performance metrics', 'danger');
        }
    },

    renderOverview(data) {
        // 1. Total Revenue
        const revEl = document.getElementById('dash-gross-revenue');
        if (revEl) {
            revEl.textContent = this.formatCurrency(data.gross_sales || 0);
        }

        // 2. Total Profit (Only rendered when user has canViewReports)
        if (this.canViewFinancials()) {
            const profitEl = document.getElementById('dash-net-profit');
            if (profitEl) {
                profitEl.textContent = this.formatCurrency(data.gross_profit || 0);
            }
        }

        // 3. Total Sales (Transaction Tickets)
        const salesEl = document.getElementById('dash-ticket-count');
        if (salesEl) {
            salesEl.textContent = (data.total_transactions || 0).toLocaleString();
        }

        // 4. Stock Units
        const units = data.stock_summary?.total_physical_units ?? 0;
        const unitsEl = document.getElementById('dash-total-units');
        if (unitsEl) {
            unitsEl.textContent = units.toLocaleString();
        }

        // 5. Products (SKUs)
        const skus = data.stock_summary?.total_skus ?? 0;
        const skusEl = document.getElementById('dash-total-skus');
        if (skusEl) {
            skusEl.textContent = skus.toLocaleString();
        }
    },

    renderTopSelling(data) {
        const products = data.products || [];
        const services = data.services || [];

        // Compute items sold from products
        const totalItemsSold = products.reduce((acc, p) => acc + (parseInt(p.total_units_sold, 10) || 0), 0);
        const itemsSoldEl = document.getElementById('dash-items-sold');
        if (itemsSoldEl) {
            itemsSoldEl.textContent = totalItemsSold.toLocaleString();
        }

        // Render Top Products Table
        const prodTbody = document.getElementById('top-products-tbody');
        if (prodTbody) {
            if (products.length === 0) {
                prodTbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center py-6 text-muted" style="color: #9ca3af;">
                            No data available.
                        </td>
                    </tr>
                `;
            } else {
                prodTbody.innerHTML = products.map(p => `
                    <tr>
                        <td>
                            <div class="font-bold text-sm" style="color: #111827;">${p.product_name}</div>
                            <div class="text-xs text-muted" style="color: #6b7280;">${p.item_code || ''} ${p.brand_name ? '• ' + p.brand_name : ''}</div>
                        </td>
                        <td class="text-center font-mono font-semibold">${p.total_units_sold}</td>
                        <td class="text-right font-mono font-bold" style="color: #1976d2;">${this.formatCurrency(p.total_revenue)}</td>
                    </tr>
                `).join('');
            }
        }

        // Render Top Services Table
        const servTbody = document.getElementById('top-services-tbody');
        if (servTbody) {
            if (services.length === 0) {
                servTbody.innerHTML = `
                    <tr>
                        <td colspan="2" class="text-center py-6 text-muted" style="color: #9ca3af;">
                            No data available.
                        </td>
                    </tr>
                `;
            } else {
                servTbody.innerHTML = services.map(s => `
                    <tr>
                        <td>
                            <div class="font-bold text-sm" style="color: #111827;">${s.service_name}</div>
                        </td>
                        <td class="text-right font-mono font-bold" style="color: #0288d1;">${s.times_rendered}</td>
                    </tr>
                `).join('');
            }
        }
    },

    renderRevenueTrendChart(records) {
        const container = document.getElementById('dash-trend-container');
        if (!container) return;

        // Safely normalize dataset and fall back cleanly if empty or zero revenue
        const safeRecords = Array.isArray(records) ? records : [];
        const hasData = safeRecords.length > 0 && safeRecords.some(r => r && (parseFloat(r.gross_revenue) || 0) > 0);

        if (!hasData) {
            container.innerHTML = `
                <div class="empty-trend-placeholder text-center text-muted py-8">
                    <div class="trend-placeholder-icon mb-2">
                        <svg width="60" height="30" viewBox="0 0 60 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 25L25 15L38 22L55 5" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <p class="text-xs text-muted font-medium" style="color: #9ca3af;">No sales trend data available.</p>
                </div>
            `;
            return;
        }

        // If data exists, draw a crisp pure SVG line & gradient area chart
        const sorted = [...safeRecords].reverse(); // chronological
        const dates = sorted.map(r => {
            const rawDate = String(r?.sale_date || '');
            return rawDate.length >= 5 ? rawDate.substring(5) : (rawDate || 'N/A'); // MM-DD
        });
        const values = sorted.map(r => parseFloat(r?.gross_revenue) || 0);

        const maxVal = Math.max(...values, 100);
        const width = 800;
        const height = 220;
        const padding = { top: 20, right: 30, bottom: 40, left: 50 };
        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;
        const baselineY = padding.top + chartHeight;

        const points = values.map((val, idx) => {
            const x = values.length === 1
                ? padding.left + chartWidth / 2
                : padding.left + (idx / (values.length - 1)) * chartWidth;
            const y = baselineY - (val / maxVal) * chartHeight;
            return { x, y, val, date: dates[idx] };
        });

        const linePath = points.length === 1
            ? `M ${padding.left.toFixed(1)} ${points[0].y.toFixed(1)} L ${(width - padding.right).toFixed(1)} ${points[0].y.toFixed(1)}`
            : points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');

        const areaPath = points.length === 1
            ? `M ${padding.left.toFixed(1)} ${points[0].y.toFixed(1)} L ${(width - padding.right).toFixed(1)} ${points[0].y.toFixed(1)} L ${(width - padding.right).toFixed(1)} ${baselineY} L ${padding.left.toFixed(1)} ${baselineY} Z`
            : `${linePath} L ${points[points.length - 1].x.toFixed(1)} ${baselineY} L ${points[0].x.toFixed(1)} ${baselineY} Z`;

        container.innerHTML = `
            <div style="width: 100%; overflow-x: auto;">
                <svg viewBox="0 0 ${width} ${height}" style="width: 100%; max-height: 240px; display: block;" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="revenueGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2e7d32" stop-opacity="0.35"/>
                            <stop offset="100%" stop-color="#2e7d32" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>

                    <!-- Horizontal Grid lines -->
                    <line x1="${padding.left}" y1="${padding.top}" x2="${width - padding.right}" y2="${padding.top}" stroke="#e5e7eb" stroke-dasharray="3,3" />
                    <line x1="${padding.left}" y1="${padding.top + chartHeight / 2}" x2="${width - padding.right}" y2="${padding.top + chartHeight / 2}" stroke="#e5e7eb" stroke-dasharray="3,3" />
                    <line x1="${padding.left}" y1="${padding.top + chartHeight}" x2="${width - padding.right}" y2="${padding.top + chartHeight}" stroke="#e5e7eb" />

                    <!-- Y-Axis labels -->
                    <text x="${padding.left - 8}" y="${padding.top + 4}" font-size="10" fill="#9ca3af" text-anchor="end" font-family="Inter, sans-serif">₱${Math.round(maxVal).toLocaleString()}</text>
                    <text x="${padding.left - 8}" y="${padding.top + chartHeight / 2 + 4}" font-size="10" fill="#9ca3af" text-anchor="end" font-family="Inter, sans-serif">₱${Math.round(maxVal / 2).toLocaleString()}</text>
                    <text x="${padding.left - 8}" y="${padding.top + chartHeight + 4}" font-size="10" fill="#9ca3af" text-anchor="end" font-family="Inter, sans-serif">₱0</text>

                    <!-- Gradient Area Fill -->
                    <path d="${areaPath}" fill="url(#revenueGrad)" />

                    <!-- Green Line -->
                    <path d="${linePath}" fill="none" stroke="#2e7d32" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                    <!-- Data dots and X labels -->
                    ${points.map(p => `
                        <circle cx="${p.x.toFixed(1)}" cy="${p.y.toFixed(1)}" r="4.5" fill="#ffffff" stroke="#2e7d32" stroke-width="2.5">
                            <title>${p.date}: ₱${p.val.toFixed(2)}</title>
                        </circle>
                        <text x="${p.x.toFixed(1)}" y="${height - 12}" font-size="10" fill="#6b7280" text-anchor="middle" font-family="Inter, sans-serif">${p.date}</text>
                    `).join('')}
                </svg>
            </div>
        `;
    },

    formatCurrency(val) {
        const num = parseFloat(val) || 0;
        return '₱' + Math.round(num).toLocaleString('en-US');
    }
};
