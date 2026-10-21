import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * Executive Dashboard View
 * 
 * High-impact business intelligence command center:
 * Revenue/profit metrics, pure CSS inventory health meter, product leaderboards,
 * quick POS/PO action shortcuts, and live audit feed.
 */
export const DashboardPage = {
    state: {
        range: 'all', // 'today', '7days', 'month', 'all'
        overview: null,
        topSelling: { products: [], services: [] }
    },

    render() {
        const user = State.user;

        return `
            <div class="view-container">
                <!-- Executive Header with Date Range Filter -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold">Executive Dashboard</h1>
                        <p class="text-muted text-sm">Welcome back, <strong>${user?.full_name || 'Administrator'}</strong>. Real-time shop performance and inventory valuation.</p>
                    </div>
                    <div class="date-range-pills flex gap-1 p-1 bg-surface rounded border">
                        <button class="pill-btn ${this.state.range === 'today' ? 'active' : ''}" data-range="today">Today</button>
                        <button class="pill-btn ${this.state.range === '7days' ? 'active' : ''}" data-range="7days">Last 7 Days</button>
                        <button class="pill-btn ${this.state.range === 'month' ? 'active' : ''}" data-range="month">This Month</button>
                        <button class="pill-btn ${this.state.range === 'all' ? 'active' : ''}" data-range="all">All Time</button>
                    </div>
                </div>

                <!-- 1. Top-Tier Financial KPIs -->
                <div class="grid grid-cols-4 gap-3 mb-4">
                    <div class="metric-card border-warning">
                        <span class="metric-label">Gross Revenue</span>
                        <div class="metric-value font-mono text-warning" id="dash-gross-revenue">₱0.00</div>
                        <span class="text-xs text-muted mt-1 block" id="dash-ticket-count">0 sales tickets</span>
                    </div>
                    <div class="metric-card border-success">
                        <span class="metric-label">Net Gross Profit</span>
                        <div class="metric-value font-mono text-success" id="dash-net-profit">₱0.00</div>
                        <span class="text-xs text-muted mt-1 block" id="dash-profit-sub">After COGS & refunds</span>
                    </div>
                    <div class="metric-card" style="border-left-color: var(--info);">
                        <span class="metric-label">Cost of Goods Sold (COGS)</span>
                        <div class="metric-value font-mono" style="color: var(--info);" id="dash-total-cogs">₱0.00</div>
                        <span class="text-xs text-muted mt-1 block">Historical acquisition cost</span>
                    </div>
                    <div class="metric-card">
                        <span class="metric-label">Gross Profit Margin</span>
                        <div class="metric-value font-mono text-primary" id="dash-profit-margin">0.0%</div>
                        <span class="text-xs text-muted mt-1 block" id="dash-aov">Avg Order: ₱0.00</span>
                    </div>
                </div>

                <!-- 2. Action Shortcuts Banner -->
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <a href="#/pos" class="card p-3 flex items-center gap-3 hover:border-primary cursor-pointer text-decoration-none">
                        <div class="text-2xl p-2 bg-surface rounded">💳</div>
                        <div>
                            <div class="font-bold text-sm">New POS Sale Terminal</div>
                            <div class="text-xs text-muted">Checkout walk-in customer parts & service ticket</div>
                        </div>
                    </a>
                    <a href="#/purchase-orders" class="card p-3 flex items-center gap-3 hover:border-primary cursor-pointer text-decoration-none">
                        <div class="text-2xl p-2 bg-surface rounded">📋</div>
                        <div>
                            <div class="font-bold text-sm">Issue Purchase Order</div>
                            <div class="text-xs text-muted">Procure inventory from approved vendors & export XML</div>
                        </div>
                    </a>
                    <a href="#/movements" class="card p-3 flex items-center gap-3 hover:border-primary cursor-pointer text-decoration-none">
                        <div class="text-2xl p-2 bg-surface rounded">⚖️</div>
                        <div>
                            <div class="font-bold text-sm">Stock Audit & Adjustment</div>
                            <div class="text-xs text-muted">Reconcile physical inventory and view audit ledger</div>
                        </div>
                    </a>
                </div>

                <!-- 3. Real-Time Stock Health Distribution (Pure CSS/SVG Visualizer) -->
                <div class="card p-4 mb-4">
                    <div class="flex justify-between items-center mb-3">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider">Inventory Health Distribution</h2>
                            <p class="text-xs text-muted">Active product catalog categorized by safety thresholds</p>
                        </div>
                        <div class="text-right text-xs">
                            <span class="font-mono font-bold" id="dash-total-units">0 total units</span>
                            <span class="text-dim"> | </span>
                            <span class="text-primary font-mono font-bold" id="dash-consigned-units">0 consigned</span>
                        </div>
                    </div>

                    <!-- Multi-Segmented Health Progress Bar -->
                    <div class="health-progress-track mb-3" id="dash-health-track">
                        <div class="track-segment segment-healthy" style="width: 0%;" title="Healthy"></div>
                        <div class="track-segment segment-low" style="width: 0%;" title="Low"></div>
                        <div class="track-segment segment-critical" style="width: 0%;" title="Critical"></div>
                        <div class="track-segment segment-out" style="width: 0%;" title="Out of Stock"></div>
                    </div>

                    <!-- Health Legend Breakdown Cards -->
                    <div class="grid grid-cols-4 gap-3 text-center">
                        <div class="p-2 bg-surface rounded border border-subtle">
                            <span class="text-xs text-success font-semibold block">● Healthy (>15)</span>
                            <span class="font-mono font-bold text-lg" id="count-healthy">0</span>
                        </div>
                        <div class="p-2 bg-surface rounded border border-subtle">
                            <span class="text-xs text-warning font-semibold block">● Low (6–15)</span>
                            <span class="font-mono font-bold text-lg text-warning" id="count-low">0</span>
                        </div>
                        <div class="p-2 bg-surface rounded border border-subtle">
                            <span class="text-xs text-danger font-semibold block">● Critical (1–5)</span>
                            <span class="font-mono font-bold text-lg text-danger" id="count-critical">0</span>
                        </div>
                        <div class="p-2 bg-surface rounded border border-subtle">
                            <span class="text-xs text-muted font-semibold block">● Out of Stock (0)</span>
                            <span class="font-mono font-bold text-lg text-muted" id="count-out">0</span>
                        </div>
                    </div>
                </div>

                <!-- 4. Top Selling Leaderboard Grid -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <!-- Top Products -->
                    <div class="card p-0 table-card">
                        <div class="p-3 border-bottom flex justify-between items-center">
                            <h2 class="text-sm font-bold uppercase tracking-wider">🏆 Top Selling Products</h2>
                            <span class="badge badge-secondary text-xs">By Revenue</span>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table text-xs" id="top-products-table">
                                <thead>
                                    <tr>
                                        <th>Product / SKU</th>
                                        <th class="text-center">Units Sold</th>
                                        <th class="text-right">Revenue</th>
                                        <th class="text-right">Gross Profit</th>
                                    </tr>
                                </thead>
                                <tbody id="top-products-tbody">
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Loading top products...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Workshop Services -->
                    <div class="card p-0 table-card">
                        <div class="p-3 border-bottom flex justify-between items-center">
                            <h2 class="text-sm font-bold uppercase tracking-wider">🔧 Top Labor & Shop Services</h2>
                            <span class="badge badge-secondary text-xs">By Revenue</span>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table text-xs" id="top-services-table">
                                <thead>
                                    <tr>
                                        <th>Service Description</th>
                                        <th class="text-center">Times Rendered</th>
                                        <th class="text-right">Total Revenue</th>
                                    </tr>
                                </thead>
                                <tbody id="top-services-tbody">
                                    <tr><td colspan="3" class="text-center py-4 text-muted">Loading top services...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 5. Recent Inventory Audit Trail -->
                <div class="card p-0 table-card">
                    <div class="p-3 border-bottom flex justify-between items-center">
                        <h2 class="text-sm font-bold uppercase tracking-wider">📜 Recent Stock Movement Events</h2>
                        <a href="#/movements" class="text-xs text-primary font-semibold text-decoration-none">View Full Ledger →</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table text-xs" id="dash-activity-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Reference #</th>
                                    <th>Product / SKU</th>
                                    <th class="text-center">Type</th>
                                    <th class="text-center">Stock Delta</th>
                                    <th>Progression</th>
                                    <th>Notes</th>
                                    <th class="text-right">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody id="dash-activity-tbody">
                                <tr><td colspan="8" class="text-center py-4 text-muted">Loading recent ledger records...</td></tr>
                            </tbody>
                        </table>
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
        // Date range pill switching
        document.querySelectorAll('.date-range-pills .pill-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.date-range-pills .pill-btn').forEach(b => b.classList.remove('active'));
                e.currentTarget.classList.add('active');
                this.state.range = e.currentTarget.dataset.range;
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
            case '7days': {
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
            const [overviewRes, topSellingRes] = await Promise.all([
                apiClient.get('reports/dashboard', params),
                apiClient.get('reports/top-selling', { ...params, limit: 5 })
            ]);

            if (overviewRes.success) {
                this.state.overview = overviewRes.data;
                this.renderOverviewMetrics(overviewRes.data);
            }

            if (topSellingRes.success) {
                this.state.topSelling = topSellingRes.data;
                this.renderTopPerformers(topSellingRes.data);
            }
        } catch (err) {
            console.error('Failed to load dashboard data', err);
            State.setAlert('Failed to load dashboard analytics: ' + err.message, 'danger');
        }
    },

    renderOverviewMetrics(data) {
        const grossRev = parseFloat(data.gross_sales || 0);
        const netProfit = parseFloat(data.gross_profit || 0);
        const cogs = parseFloat(data.total_cogs || 0);
        const margin = parseFloat(data.profit_margin_percent || 0);
        const aov = parseFloat(data.average_order_value || 0);
        const txCount = parseInt(data.total_transactions || 0, 10);

        document.getElementById('dash-gross-revenue').textContent = `₱${grossRev.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
        document.getElementById('dash-net-profit').textContent = `₱${netProfit.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
        document.getElementById('dash-total-cogs').textContent = `₱${cogs.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
        document.getElementById('dash-profit-margin').textContent = `${margin.toFixed(1)}%`;

        document.getElementById('dash-ticket-count').textContent = `${txCount} sales transaction${txCount === 1 ? '' : 's'}`;
        document.getElementById('dash-aov').textContent = `Avg Ticket: ₱${aov.toFixed(2)}`;

        // Stock Summary & Health Meter
        const stock = data.stock_summary || {};
        const healthy = parseInt(stock.healthy || 0, 10);
        const low = parseInt(stock.low || 0, 10);
        const critical = parseInt(stock.critical || 0, 10);
        const out = parseInt(stock.out_of_stock || 0, 10);
        const totalSkus = healthy + low + critical + out;

        document.getElementById('count-healthy').textContent = healthy;
        document.getElementById('count-low').textContent = low;
        document.getElementById('count-critical').textContent = critical;
        document.getElementById('count-out').textContent = out;

        document.getElementById('dash-total-units').textContent = `${parseInt(stock.total_physical_units || 0, 10).toLocaleString()} physical units`;
        document.getElementById('dash-consigned-units').textContent = `${parseInt(stock.total_consigned || 0, 10).toLocaleString()} consigned`;

        // Calculate progress percentages
        if (totalSkus > 0) {
            const pctHealthy = (healthy / totalSkus) * 100;
            const pctLow = (low / totalSkus) * 100;
            const pctCritical = (critical / totalSkus) * 100;
            const pctOut = (out / totalSkus) * 100;

            const track = document.getElementById('dash-health-track');
            if (track) {
                track.innerHTML = `
                    <div class="track-segment segment-healthy" style="width: ${pctHealthy}%;" title="Healthy (${healthy})"></div>
                    <div class="track-segment segment-low" style="width: ${pctLow}%;" title="Low (${low})"></div>
                    <div class="track-segment segment-critical" style="width: ${pctCritical}%;" title="Critical (${critical})"></div>
                    <div class="track-segment segment-out" style="width: ${pctOut}%;" title="Out of Stock (${out})"></div>
                `;
            }
        }

        // Recent Activity Feed
        const actTbody = document.getElementById('dash-activity-tbody');
        if (actTbody) {
            const list = data.recent_activity || [];
            if (list.length === 0) {
                actTbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No recent stock movements recorded.</td></tr>`;
            } else {
                actTbody.innerHTML = list.map(m => {
                    const dateStr = m.created_at ? new Date(m.created_at).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', month: 'short', day: 'numeric' }) : '—';
                    const delta = parseInt(m.quantity_change, 10);
                    const deltaHtml = delta > 0 
                        ? `<span class="badge badge-success font-mono font-bold">+${delta}</span>`
                        : (delta < 0 ? `<span class="badge badge-danger font-mono font-bold">${delta}</span>` : `<span class="badge badge-secondary font-mono">0</span>`);

                    let typeClass = 'badge-secondary';
                    if (m.type === 'SALE') typeClass = 'badge-danger';
                    if (m.type.includes('DELIVERY')) typeClass = 'badge-success';
                    if (m.type === 'ADJUSTMENT') typeClass = 'badge-warning';
                    if (m.type === 'RETURN') typeClass = 'badge-info';

                    return `
                        <tr>
                            <td class="font-mono text-muted whitespace-nowrap">${dateStr}</td>
                            <td class="font-mono font-bold text-primary">${m.reference_id || 'N/A'}</td>
                            <td>
                                <span class="font-semibold">${m.product_name}</span>
                                <div class="text-dim font-mono">${m.item_code}</div>
                            </td>
                            <td class="text-center"><span class="badge ${typeClass}">${m.type}</span></td>
                            <td class="text-center">${deltaHtml}</td>
                            <td class="text-center font-mono text-dim">${m.stock_before} ➔ <strong class="text-white">${m.stock_after}</strong></td>
                            <td class="text-muted max-w-xs truncate" title="${m.notes || ''}">${m.notes || '—'}</td>
                            <td class="text-right text-muted">${m.recorded_by_name || 'System'}</td>
                        </tr>
                    `;
                }).join('');
            }
        }
    },

    renderTopPerformers({ products, services }) {
        // Products Leaderboard
        const prodTbody = document.getElementById('top-products-tbody');
        if (prodTbody) {
            if (!products || products.length === 0) {
                prodTbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted">No product sales in selected period.</td></tr>`;
            } else {
                prodTbody.innerHTML = products.map((p, idx) => {
                    const rev = parseFloat(p.total_revenue).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                    const profit = parseFloat(p.gross_profit).toLocaleString('en-PH', { minimumFractionDigits: 2 });

                    return `
                        <tr>
                            <td>
                                <div class="font-semibold text-sm">#${idx + 1} ${p.product_name}</div>
                                <div class="text-dim font-mono">${p.item_code} | ${p.category_name || 'General'}</div>
                            </td>
                            <td class="text-center font-mono font-bold">${p.total_units_sold} ${p.unit || 'pc'}</td>
                            <td class="text-right font-mono font-bold">₱${rev}</td>
                            <td class="text-right font-mono font-bold text-success">₱${profit}</td>
                        </tr>
                    `;
                }).join('');
            }
        }

        // Services Leaderboard
        const svcTbody = document.getElementById('top-services-tbody');
        if (svcTbody) {
            if (!services || services.length === 0) {
                svcTbody.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-muted">No services recorded in selected period.</td></tr>`;
            } else {
                svcTbody.innerHTML = services.map((s, idx) => {
                    const rev = parseFloat(s.total_revenue).toLocaleString('en-PH', { minimumFractionDigits: 2 });

                    return `
                        <tr>
                            <td>
                                <div class="font-semibold text-sm">#${idx + 1} ${s.service_name}</div>
                                <div class="text-dim">Shop Labor Service</div>
                            </td>
                            <td class="text-center font-mono font-bold">${s.times_rendered} tickets</td>
                            <td class="text-right font-mono font-bold text-success">₱${rev}</td>
                        </tr>
                    `;
                }).join('');
            }
        }
    }
};
