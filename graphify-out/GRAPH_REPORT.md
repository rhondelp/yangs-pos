# Graph Report - yangs-pos  (2026-11-18)

## Corpus Check
- Corpus is ~2,742 words - fits in a single context window. You may not need a graph.

## Summary
- 14 nodes · 9 edges · 6 communities (2 shown, 4 thin omitted)
- Extraction: 89% EXTRACTED · 11% INFERRED · 0% AMBIGUOUS · INFERRED: 1 edges (avg confidence: 0.9)
- Token cost: 5,204 input · 2,059 output

## Community Hubs (Navigation)
- Core Platform and Stack
- POS and Floor Operations
- Owner Finance
- Audit Trail
- Staff and Payroll
- Frontend Styling

## God Nodes (most connected - your core abstractions)
1. `YANGS Bar Management and POS System` - 4 edges
2. `POS / Cashier Module` - 4 edges
3. `Financial Management` - 2 edges
4. `Laravel` - 1 edges
5. `PostgreSQL` - 1 edges
6. `Role-Based Access Control (RBAC)` - 1 edges
7. `Owner / Administrator Role` - 1 edges
8. `Cashier Role` - 1 edges
9. `Table Management` - 1 edges
10. `Inventory Management` - 1 edges

## Surprising Connections (you probably didn't know these)
- `Financial Management` --shares_data_with--> `POS / Cashier Module`  [EXTRACTED]
  docs/YANGS_SPEC.md → docs/YANGS_SPEC.md  _Bridges community 1 → community 2_

## Hyperedges (group relationships)
- **Core Business Flow** — docs_yangs_spec_pos_module, docs_yangs_spec_inventory_management, docs_yangs_spec_financial_management [EXTRACTED 0.95]
- **Technology Stack** — docs_yangs_spec_laravel, docs_yangs_spec_postgresql, docs_yangs_spec_tailwind_css, docs_yangs_spec_rest_api [EXTRACTED 1.00]

## Communities (6 total, 4 thin omitted)

### Community 0 - "Core Platform and Stack"
Cohesion: 0.40
Nodes (5): Laravel, PostgreSQL, YANGS Bar Management and POS System, Role-Based Access Control (RBAC), RESTful API Architecture

### Community 1 - "POS and Floor Operations"
Cohesion: 0.50
Nodes (4): Cashier Role, Inventory Management, POS / Cashier Module, Table Management

## Knowledge Gaps
- **11 isolated node(s):** `Laravel`, `PostgreSQL`, `Tailwind CSS`, `Role-Based Access Control (RBAC)`, `Owner / Administrator Role` (+6 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 11 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **4 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `POS / Cashier Module` connect `POS and Floor Operations` to `Owner Finance`?**
  _High betweenness centrality (0.115) - this node is a cross-community bridge._
- **What connects `Laravel`, `PostgreSQL`, `Tailwind CSS` to the rest of the system?**
  _11 weakly-connected nodes found - possible missing edges._
- **Why does `Financial Management` connect `Owner Finance` to `POS and Floor Operations`?**
  _High betweenness centrality (0.051) - this node is a cross-community bridge._