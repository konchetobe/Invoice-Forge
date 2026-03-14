# Domain Pitfalls

**Domain:** WordPress invoice management plugin  
**Researched:** March 14, 2026  

## Critical Pitfalls

Mistakes that cause rewrites or major issues.

### Pitfall 1: Insufficient Input Validation
**What goes wrong:** User input processed without proper sanitization  
**Why it happens:** Developers assume WordPress handles all security  
**Consequences:** SQL injection, XSS attacks, data corruption  
**Prevention:** Always use sanitize_* functions and validate input types  
**Detection:** Security audit tools, PHPCS warnings  

### Pitfall 2: Direct File Access Vulnerabilities
**What goes wrong:** Plugin files accessible directly via URL  
**Why it happens:** Forgetting ABSPATH checks in PHP files  
**Consequences:** Information disclosure, code execution  
**Prevention:** Add `if (!defined('ABSPATH')) exit;` to all PHP files  
**Detection:** Manual code review, security scanners  

### Pitfall 3: Naming Collisions
**What goes wrong:** Plugin conflicts with others due to same function/class names  
**Why it happens:** Using common prefixes or no prefixing  
**Consequences:** Fatal errors, broken functionality  
**Prevention:** Use unique 4-5 character prefixes everywhere  
**Detection:** Testing with multiple plugins active  

## Moderate Pitfalls

### Pitfall 1: Improper Hook Usage
**What goes wrong:** Hooks called at wrong time or priority  
**Why it happens:** Misunderstanding WordPress load order  
**Consequences:** Features not working, conflicts with other plugins  
**Prevention:** Study hook reference, use appropriate priorities  
**Detection:** Functional testing across different scenarios  

### Pitfall 2: Database Table Creation Issues
**What goes wrong:** Tables created without proper charset/collate or upgrade routines  
**Why it happens:** Skipping dbDelta or not handling upgrades  
**Consequences:** Data loss, compatibility issues  
**Prevention:** Use dbDelta, include upgrade functions  
**Detection:** Database schema checks  

## Minor Pitfalls

### Pitfall 1: Hardcoded Text
**What goes wrong:** Strings not translatable  
**Why it happens:** Forgetting internationalization  
**Consequences:** Plugin not usable in other languages  
**Prevention:** Use __() and _e() functions  
**Detection:** i18n tools, manual review  

### Pitfall 2: Missing Capability Checks
**What goes wrong:** Unauthorized users access restricted features  
**Why it happens:** Assuming admin context  
**Consequences:** Security breaches  
**Prevention:** Always check current_user_can()  
**Detection:** User role testing  

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Custom Post Types | Incorrect registration timing | Register during init hook |
| PDF Generation | Memory exhaustion with large PDFs | Implement streaming or chunking |
| AJAX Handlers | Missing nonce verification | Always verify nonces in AJAX |
| Settings API | Improper validation callbacks | Use sanitize_callback parameter |
| Chart Rendering | JavaScript conflicts | Use wp_enqueue_script properly |

## Sources

- WordPress Security Guidelines: https://developer.wordpress.org/plugins/security/
- Plugin Best Practices: https://developer.wordpress.org/plugins/plugin-basics/best-practices/
- Common Issues Discussion: WordPress support forums and GitHub issues