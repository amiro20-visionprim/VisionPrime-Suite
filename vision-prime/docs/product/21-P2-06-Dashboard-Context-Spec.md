# P2-06 — Real Dashboard Counts & Context UX

## Dashboard decision logic
- clients = 0: CTA add first client
- clients > 0 and projects = 0: CTA create project
- projects > 0 and sites = 0: CTA add first site
- sites > 0 and no connector/GSC: CTA connect data sources
- data ready: CTA view growth opportunities

## Metrics
- total clients: real organization-scoped count
- total projects: real organization-scoped count
- total sites: real organization-scoped count
- connected sites: 0 until Connector domain
- open opportunities: 0 until Intelligence domain

## Activity
Show recent organization AuditLog records with human-readable action labels. Sensitive payloads remain unavailable in dashboard projections.
