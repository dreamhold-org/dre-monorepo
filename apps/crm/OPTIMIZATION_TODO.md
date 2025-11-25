# Performance Optimization Todo List

**Project:** EspoCRM Performance Optimization
**Date Started:** 2025-11-25
**Target:** Reduce page load time from 7.81s to < 1s

## Progress Tracker

- [x] Phase 1: Diagnostic Investigation
  - [x] Run ping tests from Turkey (Result: ~200ms RTT)
  - [x] Run HTTPS timing breakdown (Result: 750ms total, 500ms network)
  - [x] Run metadata endpoint tests (Result: 730-840ms)
  - [x] Check server resource utilization (Result: Healthy, 2GB RAM server)
  - [ ] Complete PostgreSQL statistics query
  - [x] Analyze results and determine strategy

- [ ] Phase 2: Implementation
  - [ ] **Priority 1: CloudFlare CDN Setup** (30 min, 0 downtime)
    - [ ] Sign up for CloudFlare free tier
    - [ ] Add starosten.com to CloudFlare
    - [ ] Update domain nameservers
    - [ ] Configure DNS records (crm → 159.198.64.202)
    - [ ] Set SSL/TLS to "Full (strict)"
    - [ ] Configure caching rules for /api/v1/Metadata
    - [ ] Configure caching rules for /client/* static assets
    - [ ] Enable optimizations (Minify, Brotli, HTTP/3)
    - [ ] Test cache status with curl
    - [ ] Expected result: 750ms → 150-200ms (80% improvement)

  - [ ] **Priority 2: PostgreSQL Optimization** (45 min, 5-10 min downtime)
    - [ ] Create postgres-custom.conf (tuned for 2GB RAM)
    - [ ] Update docker-compose.yml to mount config
    - [ ] Add resource limits to postgres service
    - [ ] Add resource limits to all services
    - [ ] Create manual backup before changes
    - [ ] Apply configuration (down + up)
    - [ ] Verify services are healthy
    - [ ] Validate configuration applied
    - [ ] Monitor performance
    - [ ] Expected result: Server processing 250ms → 100-150ms (40-60% improvement)

  - [ ] **Priority 3: PHP/OPcache Optimization** (30 min, 2-3 min downtime)
    - [ ] Check current PHP configuration
    - [ ] Create php-custom.ini with OPcache settings
    - [ ] Update docker-compose.yml to mount PHP config
    - [ ] Restart espocrm container
    - [ ] Verify OPcache is enabled
    - [ ] Expected result: PHP processing 20-30% faster

  - [ ] **Priority 4: EspoCRM Cache Rebuild** (10 min, 0 downtime)
    - [ ] Clear EspoCRM cache directory
    - [ ] Run rebuild command
    - [ ] Verify cache directory populated
    - [ ] Check EspoCRM admin panel cache settings
    - [ ] Expected result: Optimal cache performance

- [ ] Phase 3: Validation & Monitoring
  - [ ] Test page load from Turkey (run 5 times, average)
  - [ ] Check CloudFlare cache hit ratio
  - [ ] Monitor server resources (should be stable)
  - [ ] Check database cache hit ratio (target > 90%)
  - [ ] Verify no error rate increase
  - [ ] Document final performance metrics
  - [ ] Monitor for 24 hours

- [ ] Phase 4: Documentation
  - [ ] Update PERFORMANCE_INVESTIGATION.md with final results
  - [ ] Document any issues encountered
  - [ ] Create runbook for future optimizations
  - [ ] Update README.md with performance notes

## Configuration Files to Create

### New Files
1. `/Users/mstarostenko/Projectory/DRE/dre-monorepo/apps/crm/postgres-custom.conf`
   - PostgreSQL tuning for 2GB RAM server
   - Memory settings, checkpoint config, query planning

2. `/Users/mstarostenko/Projectory/DRE/dre-monorepo/apps/crm/php-custom.ini`
   - PHP memory limits and execution time
   - OPcache configuration
   - Realpath cache settings

### Files to Modify
1. `/Users/mstarostenko/Projectory/DRE/dre-monorepo/apps/crm/docker-compose.yml`
   - Mount postgres-custom.conf
   - Mount php-custom.ini
   - Add resource limits to all services
   - Add postgres command override

### External Configuration
1. CloudFlare Dashboard
   - DNS records
   - Caching rules
   - SSL/TLS settings
   - Optimization features

## Success Criteria

- [x] Network latency identified: ~200ms RTT (Turkey → America)
- [x] Server processing time identified: ~250ms
- [ ] CloudFlare CDN implemented and working
- [ ] Metadata endpoint < 200ms from Turkey (cached)
- [ ] Database cache hit ratio > 90%
- [ ] Server resources remain healthy
- [ ] No increase in error rates
- [ ] Performance improvement sustained over 24 hours

## Blockers / Issues

- None currently

## Notes

- Server has only 2GB RAM (not 8GB) - configurations adjusted accordingly
- Server is NOT resource-constrained (plenty of headroom)
- Geographic latency is the primary bottleneck (67% of total time)
- CloudFlare free tier is sufficient for our needs
- All optimizations are reversible with documented rollback procedures

## Timeline Estimate

| Phase | Time | Downtime |
|-------|------|----------|
| CloudFlare CDN | 30 min | 0 min |
| PostgreSQL | 45 min | 5-10 min |
| PHP/OPcache | 30 min | 2-3 min |
| Cache Rebuild | 10 min | 0 min |
| Validation | 30 min | 0 min |
| **Total** | **2.5 hours** | **7-13 min** |

---

**Last Updated:** 2025-11-25 15:53 UTC
**Status:** In Progress - Diagnostic phase completed, awaiting DB stats
