# E-Commerce Seller Dashboard Architectural Specification

**Project:** PickSell Marketplace
**Document type:** Frontend + Backend Blueprint
**Status:** Draft for design and implementation review
**Audience:** Product, frontend engineering, backend engineering, QA, and AI-assisted implementation workflows

---

## 1. Executive Summary

This specification defines the functional structure, visual hierarchy, and technical state strategy for the upgraded E-Commerce Seller Dashboard. The dashboard is designed to improve day-to-day selling operations by surfacing the most urgent actions first, consolidating critical performance metrics into a single screen, and helping sellers move from insight to action with minimal friction.

The primary goals are:

- Reduce seller decision time by placing urgent operational tasks at the top of the page.
- Give a clear operational pulse using KPI tiles, conversion signals, and order health indicators.
- Support rapid fulfillment and stock management without forcing sellers into multiple screens.
- Provide a backend contract that is simple, predictable, and AI-readable for implementation and testing.
- Keep the user interface modular enough for iterative improvements and role-scoped content.

---

## 2. Dashboard Objectives

### 2.1 Business Objectives

1. Help sellers understand their current store health within a few seconds.
2. Highlight stock and order bottlenecks that require attention.
3. Present sales momentum and order volume clearly across multiple time ranges.
4. Ensure critical operations are visible without overwhelming the seller.
5. Give direct action paths to order processing, restocking, and product management.

### 2.2 UX Principles

- Clarity over density
- Action before analysis
- Progressive disclosure
- Immediate operational relevance
- Consistent status semantics

---

## 3. Page Layout Blueprint

The dashboard should be arranged as a compact, high-information panel system. The page should prioritize urgency at the top, metrics in the middle, and actionable operational detail along the lower portion.

```text
+-----------------------------------------------------------------------------------+
| GREETING / STORE STATUS BAND                                                       |
| "Good morning, [Store Name]"                                  [ + Add Product ] |
+-----------------------------------------------------------------------------------+

+-----------------------------------------------------------------------------------+
| ACTION REQUIRED PANEL (High priority alerts)                                       |
| • 3 orders need processing                                            [ Process ] |
| • 5 products low in stock                                             [ Restock ] |
+-----------------------------------+-----------------------------------------------+
| KPI GROUP 1: METRICS               | KPI GROUP 2: PIPELINE     | KPI GROUP 3: CATALOG |
| • Total Sales (↑ vs last period)   | • Completed Orders       | • Active Products    |
| • Total Orders (↑ vs last period) | • Pending Orders         | • Low-Stock Items    |
+-----------------------------------+--------------------------+----------------------+

+-----------------------------------+-----------------------------------------------+
| SALES PERFORMANCE CHART           | ORDER STATUS BREAKDOWN                        |
| • Range: [7D] [30D] [6M]          | • Processing (12)                             |
| • Metric: [Sales] [Orders]        | • Shipped (45)                                |
|                                   | • Delivered (120)                             |
+-----------------------------------+-----------------------------------------------+

+-----------------------------------+-----------------------------------------------+
| RECENT ORDERS TABLE               | LOW-STOCK INVENTORY GLANCE                    |
| • Top 5 rows max                  | • Product A (2 left)                          |
| • Direct row click to detail      | • Product B (1 left)                          |
| • Unified status badges           | • [+ N more link to inventory]                |
+-----------------------------------+-----------------------------------------------+
```

---

## 4. Functional Modules

### 4.1 Greeting / Store Status Band

This is the primary header section of the dashboard and acts like the seller’s landing banner.

Required elements:

- Store name with time-based greeting such as “Good morning, [Store Name]”
- Current status summary (e.g., online, active, shipping window, backlog health)
- Primary CTA: “+ Add Product”
- Optional secondary CTA: “View Store” or “Manage Catalog”

Behavior:

- Greeting should update based on local time-of-day.
- Store status should be derived from seller settings or platform state.
- CTA should route to product creation or catalog page.

---

### 4.2 Action Required Panel

This section surfaces tasks requiring immediate attention and should appear above any analytic widgets.

Example alerts:

- 3 orders need processing
- 5 products low in stock

Recommended UX behaviors:

- Each item has a clear priority label or icon.
- Action button should route to the exact relevant workflow.
- Items should be grouped by business urgency, not random order.

Recommended data source:

- Count of orders with status in processing queue
- Count of products below reorder threshold

---

### 4.3 KPI Groups

The dashboard should use three compact KPI groups: Metrics, Pipeline, and Catalog. Each group contains 2–3 high-signal metrics.

#### KPI Group 1: Metrics

- Total Sales
- Total Orders
- Comparison delta vs previous period

#### KPI Group 2: Pipeline

- Completed Orders
- Pending Orders
- In-progress or fulfillment-specific summary

#### KPI Group 3: Catalog

- Active Products
- Low-Stock Items
- Optional: out-of-stock or archived count

Design guidance:

- Use upward/downward arrows and color-coded states to indicate change direction.
- Keep values brief and easy to scan.
- Compare to prior time range for contextual performance.

---

### 4.4 Sales Performance Chart

This section provides a visual trend of performance over time.

Controls:

- Range toggles: 7D, 30D, 6M
- Metric toggles: Sales, Orders

Purpose:

- Give sellers a quick visual read on momentum and seasonality.
- Let them compare revenue or order volume over the chosen time window.

Implementation recommendation:

- Use a small, card-based chart with insight labels.
- Support hover or click detail for exact values.
- Keep the default range at 30D for operational clarity.

---

### 4.5 Order Status Breakdown

This widget visualizes how orders are distributed across fulfillment states.

Example values:

- Processing (12)
- Shipped (45)
- Delivered (120)

Recommended behavior:

- Each row should be clickable and route to filtered order views.
- Status colors should match overall status semantics across the platform.
- Counts should be derived from backend aggregated order state totals.

---

### 4.6 Recent Orders Table

The table should show only the most recent orders and remain compact and scannable.

Rules:

- Show a maximum of 5 rows
- Each row is clickable to open order detail
- Unified status badges should be reused from the order management system
- Use clear sorting by newest first

Columns should minimally include:

- Order #
- Buyer/customer name
- Total amount
- Date/time
- Status

---

### 4.7 Low-Stock Inventory Glance

This panel keeps stock risk visible without opening the full inventory module.

Example:

- Product A (2 left)
- Product B (1 left)
- [+ N more link to inventory]

Recommended rule:

- Only show the top few most critical low-stock products.
- Use threshold-based logic driven by stock quantity and reorder point.
- Link should open the full inventory list filtered to low-stock items.

---

## 5. Technical State Strategy

The dashboard should be implemented as a server-driven summary page with lightweight client-side interactivity.

### 5.1 State Model

The page should maintain the following state:

```json
{
  "sellerId": 123,
  "storeName": "My Store",
  "timeOfDay": "morning",
  "selectedRange": "30D",
  "selectedMetric": "sales",
  "alerts": [
    { "type": "orders_pending", "count": 3, "action": "process" },
    { "type": "low_stock", "count": 5, "action": "restock" }
  ],
  "kpis": {
    "metrics": {
      "totalSales": 32540,
      "totalOrders": 142,
      "salesDelta": 12.4,
      "ordersDelta": 8.1
    },
    "pipeline": {
      "completedOrders": 96,
      "pendingOrders": 18
    },
    "catalog": {
      "activeProducts": 234,
      "lowStockItems": 14
    }
  },
  "chart": {
    "labels": ["W1", "W2", "W3", "W4"],
    "values": [1200, 1700, 1600, 2100]
  },
  "orderBreakdown": {
    "processing": 12,
    "shipped": 45,
    "delivered": 120
  },
  "recentOrders": [
    {
      "id": 10021,
      "customer": "Maria S.",
      "amount": 1450,
      "status": "processing",
      "createdAt": "2026-10-05T10:20:00Z"
    }
  ],
  "lowStock": [
    { "productId": 44, "name": "Product A", "stockLeft": 2 },
    { "productId": 45, "name": "Product B", "stockLeft": 1 }
  ]
}
```

### 5.2 State Source Rules

- Live metrics should be generated server-side using the authenticated seller context.
- UI filter state such as range and metric should be client state only.
- Alerts and low-stock data should be derived from backend queries, not manually maintained in frontend code.
- Data should be fetched from a single dashboard summary endpoint where possible.

---

## 6. Backend Contract

The dashboard should be backed by a summary endpoint that returns all data needed for initial render.

### 6.1 Primary Endpoint

`GET /seller/dashboard`

### 6.2 Endpoint Responsibilities

The endpoint should return:

- greeting/store metadata
- immediate action counts
- KPI totals
- order status distribution
- recent orders
- low-stock inventory preview
- chart data for selected range

### 6.3 Recommended Response Shape

```json
{
  "success": true,
  "data": {
    "store": {
      "id": 123,
      "name": "My Store",
      "status": "online"
    },
    "greeting": "Good morning, My Store",
    "alerts": [
      { "type": "orders_pending", "label": "3 orders need processing", "count": 3 },
      { "type": "low_stock", "label": "5 products low in stock", "count": 5 }
    ],
    "kpis": {
      "sales": { "value": 32540, "delta": 12.4 },
      "orders": { "value": 142, "delta": 8.1 },
      "completedOrders": 96,
      "pendingOrders": 18,
      "activeProducts": 234,
      "lowStockItems": 14
    },
    "chart": {
      "range": "30D",
      "metric": "sales",
      "series": [
        { "label": "W1", "value": 1200 },
        { "label": "W2", "value": 1700 },
        { "label": "W3", "value": 1600 },
        { "label": "W4", "value": 2100 }
      ]
    },
    "orderBreakdown": [
      { "status": "processing", "count": 12 },
      { "status": "shipped", "count": 45 },
      { "status": "delivered", "count": 120 }
    ],
    "recentOrders": [],
    "lowStockProducts": []
  }
}
```

### 6.4 Supporting Endpoints

These can be separate endpoints for filtered or paginated views:

- `GET /seller/dashboard/chart?range=30D&metric=sales`
- `GET /seller/orders?status=pending&limit=5`
- `GET /seller/products?low_stock=true&limit=5`
- `POST /seller/orders/{id}/process`
- `POST /seller/products/reorder-check`

---

## 7. Frontend Architecture

### 7.1 Component Breakdown

Recommended frontend structure:

- `SellerDashboardPage`
  - `StoreHeaderBand`
  - `ActionRequiredPanel`
  - `KpiGroup`
  - `SalesPerformanceChart`
  - `OrderStatusBreakdown`
  - `RecentOrdersTable`
  - `LowStockInventoryPanel`

### 7.2 Rendering Strategy

- Initial page render should be server-rendered or SSR-hydrated where the app supports it.
- Use a fetch pattern for later range changes or live refresh actions.
- Keep each card modular so it can be reused in a mobile or tablet layout.

### 7.3 Interaction Rules

- Card widgets should remain independent and reusable.
- Table row clicks should route to order detail pages with preserved filters.
- Status badges must be consistent across dashboard and full order management.
- Actions should always be tied to a valid business workflow.

---

## 8. Data and Business Rules

### 8.1 Order Conditions

Actions required should be computed from the following criteria:

- orders with status in pending/processing and awaiting seller confirmation
- products with stock less than or equal to reorder threshold

### 8.2 KPI Definitions

- Total Sales: sum of completed order totals for the selected period
- Total Orders: count of orders in the selected period
- Completed Orders: orders with a terminal success state in the pipeline
- Pending Orders: orders awaiting action or shipment confirmation
- Active Products: products currently enabled for storefront sale
- Low-Stock Items: products below threshold or set to auto-restock alert

### 8.3 Time Range Rules

- 7D: short-term activity snapshot
- 30D: default operational view
- 6M: trend and seasonal overview

---

## 9. Accessibility and UX Quality Standards

- Use semantic headings and descriptive labels for all widgets.
- Maintain color contrast for status badges and KPI changes.
- Ensure all action buttons are keyboard reachable.
- Provide visible focus states and clear hover feedback.
- Keep the layout responsive for tablet and desktop first, with mobile support as a secondary priority.

---

## 10. Acceptance Criteria

The seller dashboard implementation is considered complete when all of the following pass:

1. The top banner displays a personalized greeting and a clear Add Product action.
2. The action panel highlights priority operational issues above all other content.
3. Dashboard KPIs summarize sales, order volume, pipeline state, and catalog health in a compact format.
4. Sales chart supports multiple ranges and metric toggles.
5. Order breakdown totals reflect backend order state data accurately.
6. Recent orders show only the most relevant top rows and are clickable.
7. Low-stock inventory preview provides quick access to the full inventory view.
8. The page loads efficiently and uses a single aggregated summary payload for initial render.
9. All route actions are authorization-checked and tied to valid seller-owned data.
10. The screen is visually clear and supports operational decision-making without excessive navigation.

---

## 11. Implementation Recommendation

The most direct and maintainable implementation for this project is:

- Use a dedicated seller dashboard controller and summary query service.
- Build a dashboard summary endpoint that aggregates all required metrics in one call.
- Render the page using existing Laravel Blade conventions or a dedicated dashboard view component layer.
- Use Vite-managed frontend assets for widget logic, chart rendering, and light client interactions.
- Reuse existing order and product status enums or centralized constants wherever possible.

This keeps the implementation aligned with the current Laravel architecture while preserving a clear dashboard schema for future growth.

---

## 12. Summary

The seller dashboard should behave as a compact operational command center: a fast status overview layered with actionable queue items, trend visibility, and direct paths to the parts of the platform that matter most. It should empower sellers to understand their business, act quickly, and stay focused on fulfillment and inventory health instead of navigating across multiple screens.

## 13. Seller Commission & Earnings Visibility

The seller panel provides a separate informational earnings page at `GET /seller/earnings`. It reports only the authenticated seller's completed orders for the selected order-created date range; it does not initiate payouts or provide wallet functionality.

- Summary metrics show gross sales, the recorded commission deduction, and net earnings (`gross sales - commission`).
- Presets include today, the last 7 or 30 days, this month, and last month. Sellers can choose a custom inclusive date range.
- Each order can be expanded to disclose its gross amount, effective recorded commission rate, deduction, net earnings, and completed status.
- Commission calculations use the shared `config('app.platform_commission_rate')` percentage. The Admin commission setting synchronizes that token from its persisted value, and new checkout orders, seller order details, earnings, and seller financial reports use that source.
- Each earnings row links to its seller-owned order detail and includes an accessible disclosure button for the gross, commission, and net calculation.
- `GET /seller/earnings/csv` and `GET /seller/earnings/pdf` export the same seller-scoped, completed-order dataset and date range displayed on screen.
- The Sales & Performance Report includes order-level gross sales, commission deducted, and net earnings. Its PDF and CSV exports use the same date validation and financial calculations as the on-screen report.
- Date parameters are validated, and order queries are scoped to the authenticated seller before rendering or exporting.
