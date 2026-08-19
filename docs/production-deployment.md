# Production Deployment

Checklist for deploying RevIt Publisher to a real WordPress site.

1. Backup WordPress (database + files)
2. Deploy plugin files or production ZIP
3. `composer install --no-dev --optimize-autoloader`
4. `npm run build` (if building from source)
5. Activate plugin
6. Flush permalinks (**Settings → Permalinks → Save**)
7. Open **System Health** — run self-test
8. Verify vehicle hub routes
9. Verify WordPress sitemap includes RevIt content
10. Connect Google Search Console (production OAuth credentials)
11. Select Search Console property
12. Run initial GSC sync
13. Run SEO audit
14. Review Needs Attention
15. Review Editorial Queue (reconcile)
16. Verify WP-Cron events (audit, GSC sync)
17. Confirm no conflicting SEO plugin (see Settings notice)
18. Confirm redirects
19. Verify public JSON-LD on sample article/hub
20. Check system event log for errors

See [production-packaging.md](./production-packaging.md), [google-oauth.md](./google-oauth.md).
