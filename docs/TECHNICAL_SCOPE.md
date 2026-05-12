# AI Complaint Doctor — Technical scope (for Technopreneurship report)

This document aligns the **written business report** with what the repository **actually implements**, so your submission stays credible and “VIP professional”.

## What is implemented today (working demo)

| Report theme | Implementation |
|--------------|----------------|
| Multi-tenant SaaS | `companies` + `users.company_id` + role-based APIs |
| Free vs Premium | `companies.subscription` + `EnsurePremiumSubscription` middleware on premium routes |
| Complaint intake | REST API + React complaints page; keyword sentiment + category heuristics |
| Pattern detection | PHP service: tokenisation, vocabulary cap, **TF‑IDF-style** dense vectors, **k-means-style** clustering; persisted as `issue_clusters` + `complaint.issue_cluster_id` |
| Dashboard | Totals, open/resolved, categories, sentiment chart, **7-day trend**, critical-today count; premium adds **top issues** + **customer mood** |
| Diagnosis | `/issues/{id}/diagnosis` — counts, keywords, sample complaints, 7-day mini chart, suggested actions |
| Story timeline | `/issues/{id}/timeline` — narrative events derived from time series |
| Smart alerts | `alerts` table + `/alerts` API; spike / elevation rules after clustering |
| Super admin | `super_admin` role; company list + activate + **set subscription** |
| Self-service signup | Public register creates the tenant on **Free** with **`registration_status` = pending**; tenant **cannot sign in** until a **`super_admin`** approves (choosing **Free** or **Premium**) or rejects. Tenant **Admin** may still request plan changes via approval after activation. |
| Super admin issue view | **Issue patterns** supports optional **organization** filter and shows **organization name** on each cluster when browsing across tenants. |

## Honest “AI” wording for your report

- **Current engine:** classical NLP + clustering (good for prototypes, pilots, coursework).  
- **Not included:** large transformer models, real-time social scraping, CRM connectors, production SOC2 compliance narrative (unless you add it).

## Suggested “Future work” paragraph (copy into report)

> Future versions will add transformer-based embeddings, automated retraining pipelines, streaming ingestion from ticketing and social channels, and enterprise SSO. The current release proves product-market fit, UX, and SaaS packaging for Technopreneurship evaluation.

## Commands reference

- `php artisan migrate` — create schema (MySQL or SQLite).  
- `php artisan db:seed` — international demo companies + complaints + clusters.  
- `php artisan complaints:cluster` — recompute clusters after bulk edits.
