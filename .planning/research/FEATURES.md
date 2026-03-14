# Feature Landscape

**Domain:** WordPress invoice management plugin  
**Researched:** March 14, 2026  

## Table Stakes

Features users expect. Missing = product feels incomplete.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Plugin activation/deactivation hooks | Standard plugin lifecycle | Low | Required for setup/cleanup |
| Admin menu integration | Access to plugin settings | Low | WordPress standard |
| Settings API usage | Configuration storage | Medium | Secure option handling |
| Custom post types | Invoice data structure | Medium | Extends WordPress content types |
| Meta boxes | Invoice details input | Medium | Standard WordPress UI |
| Nonce verification | Security against CSRF | Low | Mandatory for forms |
| Input sanitization | Data security | Low | Prevents injection attacks |
| Output escaping | XSS prevention | Low | Required for all output |

## Differentiators

Features that set product apart. Not expected, but valued.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| PDF generation | Professional invoice delivery | High | Requires mPDF library |
| Chart visualizations | Data insights | Medium | Uses Chart.js |
| Sequential numbering | Business-ready invoices | Medium | Prevents duplicates |
| Client management | Relationship tracking | Medium | Custom post type for clients |
| Email integration | Automated notifications | High | WordPress mail API |
| Tax calculations | Accurate invoicing | Medium | Configurable rates |

## Anti-Features

Features to explicitly NOT build.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Custom login system | Replicates WordPress functionality | Use WordPress users/roles |
| Direct database queries without $wpdb | Bypasses WordPress security | Always use $wpdb->prepare() |
| Global JavaScript without enqueueing | Conflicts with other plugins | Use wp_enqueue_script() |
| Hardcoded text | Not translatable | Use __() and _e() functions |
| eval() or create_function() | Security risks | Use proper PHP constructs |

## Feature Dependencies

```
Plugin activation → Custom post types (CPT needs activation)
Custom post types → Meta boxes (meta boxes attach to CPTs)
Settings API → Admin menus (settings need menu access)
PDF generation → Invoice data (needs data to generate PDF)
Chart visualizations → Invoice data (needs data to visualize)
```

## MVP Recommendation

Prioritize:
1. Plugin activation/deactivation and basic structure
2. Custom post types for invoices
3. Admin menus and settings
4. Meta boxes for invoice input
5. Basic PDF generation (differentiator)

Defer: Email integration (complex, can be add-on), Advanced tax calculations (business logic)

## Sources

- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/
- Plugin Best Practices: https://developer.wordpress.org/plugins/plugin-basics/best-practices/
- Security Guidelines: https://developer.wordpress.org/plugins/security/