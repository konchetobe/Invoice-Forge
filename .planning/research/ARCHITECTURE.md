# Architecture Patterns

**Domain:** WordPress invoice management plugin  
**Researched:** March 14, 2026  

## Recommended Architecture

Plugin follows object-oriented architecture with PSR-4 namespaces, dependency injection container, and separation of concerns. Main plugin file loads autoloader and initializes core Plugin class. Admin and public functionality separated via conditional loading.

### Component Boundaries

| Component | Responsibility | Communicates With |
|-----------|---------------|-------------------|
| Plugin.php | Orchestrates plugin initialization | All components |
| Container.php | Dependency injection management | Service classes |
| PostTypes/ | Custom post type registration | WordPress core |
| Admin/ | Admin interface and menus | WordPress admin APIs |
| Services/ | Business logic (numbering, PDF) | Repositories, external libraries |
| Repositories/ | Data access layer | WordPress $wpdb |
| Security/ | Input validation and sanitization | All input sources |

### Data Flow

User action → Controller/Handler → Service → Repository → Database  
Response flows back: Database → Repository → Service → Template → User  

## Patterns to Follow

### Pattern 1: Singleton Plugin Class
**What:** Single Plugin instance manages all initialization  
**When:** Standard WordPress plugin pattern  
**Example:**
```php
class Plugin {
    private static ?Plugin $instance = null;
    
    public static function getInstance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### Pattern 2: Repository Pattern
**What:** Abstracts data access behind interfaces  
**When:** For database operations  
**Example:**
```php
interface InvoiceRepositoryInterface {
    public function find(int $id): ?Invoice;
    public function save(Invoice $invoice): int;
}
```

### Pattern 3: Service Layer
**What:** Contains business logic separate from data access  
**When:** For complex operations like PDF generation  

### Pattern 4: Hooks Integration
**What:** Use actions/filters for extensibility  
**When:** Always for plugin functionality  

## Anti-Patterns to Avoid

### Anti-Pattern 1: God Classes
**What:** Single class handling too many responsibilities  
**Why bad:** Hard to maintain, test, and extend  
**Instead:** Split into focused classes with single responsibility  

### Anti-Pattern 2: Direct Database Queries
**What:** Using raw SQL without WordPress APIs  
**Why bad:** Security vulnerabilities, compatibility issues  
**Instead:** Use $wpdb->prepare() or custom tables with proper abstraction  

### Anti-Pattern 3: Global Functions
**What:** Defining functions in global namespace  
**Why bad:** Naming conflicts, hard to test  
**Instead:** Use class methods or namespaced functions  

## Scalability Considerations

| Concern | At 100 users | At 10K users | At 1M users |
|---------|--------------|--------------|-------------|
| Database queries | $wpdb prepare | Query optimization, caching | Database sharding |
| File operations | Direct filesystem | CDN integration | Distributed storage |
| Memory usage | Standard PHP | Opcode caching | Memory-efficient algorithms |

## Sources

- WordPress Plugin Boilerplate: https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate
- Plugin Best Practices: https://developer.wordpress.org/plugins/plugin-basics/best-practices/
- PHP Coding Standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/