# PDF Generation Technologies for PHP: mPDF, TCPDF, and DomPDF

**Research Date:** March 14, 2026  
**Focus:** PHP PDF libraries with emphasis on mPDF for InvoiceForge WordPress plugin  
**Confidence Level:** HIGH (based on official documentation and repository analysis)

## Executive Summary

This research evaluates three leading PHP PDF generation libraries: mPDF, TCPDF, and DomPDF. mPDF emerges as the recommended choice for InvoiceForge due to its superior CSS support and template handling capabilities, which are crucial for generating professional invoices with complex layouts. While TCPDF offers better performance, its limited CSS support makes it less suitable for HTML-based templates. DomPDF provides excellent CSS compliance but suffers from performance issues and table rendering limitations.

## Library Overviews

### mPDF
- **Version:** 8.2.6 (latest as of research date)
- **License:** GNU GPL v2
- **GitHub Stars:** 4.7k
- **PHP Support:** 5.6+ (up to 8.5)
- **Description:** Generates PDF files from UTF-8 encoded HTML using CSS for styling
- **Architecture:** Based on FPDF and HTML2FPDF with enhancements

### TCPDF
- **Version:** 6.6.5 (support-only mode)
- **License:** GNU LGPL v3
- **GitHub Stars:** 4.5k
- **PHP Support:** 5.3+ (up to 8.3+)
- **Description:** PHP library for generating PDF documents on-the-fly
- **Architecture:** Self-contained, no external PDF library dependencies
- **Note:** In support-only mode; new version (tc-lib-pdf) under development

### DomPDF
- **Version:** 3.1.5 (latest stable)
- **License:** LGPL-2.1
- **GitHub Stars:** 11.1k
- **PHP Support:** 7.1+
- **Description:** HTML to PDF converter written in PHP
- **Architecture:** CSS 2.1 compliant layout engine with bundled R&OS PDF class

## Capabilities and Features

### Core Features Matrix

| Feature | mPDF | TCPDF | DomPDF |
|---------|------|-------|--------|
| HTML to PDF | ✓ | ✓ | ✓ |
| UTF-8/Unicode | ✓ | ✓ | ✓ |
| Right-to-Left (RTL) | ✓ | ✓ | ✓ |
| Custom Fonts | ✓ | ✓ | ✓ |
| Image Support | ✓ (GIF/PNG/JPEG/SVG) | ✓ (multiple formats) | ✓ (GIF/PNG/JPEG/BMP/SVG) |
| Barcodes | ✓ (multiple types) | ✓ (extensive) | Limited |
| Encryption | ✓ | ✓ | ✓ |
| Digital Signatures | ✓ | ✓ | ✓ |
| Bookmarks/TOC | ✓ | ✓ | ✓ |
| Annotations | ✓ | ✓ | ✓ |
| Layers | ✓ | ✓ | ✓ |
| PDF/A Support | ✗ | ✓ | ✗ |

### Advanced Features

**mPDF:**
- Color management and pre-press features
- Headers/footers with page numbering
- Table of contents generation
- Watermarks and backgrounds
- Form fields and JavaScript support

**TCPDF:**
- Extensive barcode support (40+ types)
- Multiple column layouts
- Text hyphenation and stretching
- Page compression
- XObject templates
- PDF/A-1b compliance

**DomPDF:**
- CSS @import, @media, and @page rules
- Complex table rendering (spans, borders)
- External stylesheet support
- Inline PHP execution
- Basic SVG support

## Performance Characteristics

### Benchmark Results (Approximate, based on community reports)

| Metric | mPDF | TCPDF | DomPDF |
|--------|------|-------|--------|
| Generation Speed | Medium | Fast | Slow |
| Memory Usage | Medium-High | Low | High |
| File Size | Medium | Small | Medium |
| CPU Usage | Medium | Low | High |

### Performance Analysis

**TCPDF Advantages:**
- Fastest generation times (typically 2-3x faster than DomPDF)
- Lowest memory footprint
- Smaller PDF file sizes
- Optimized for high-volume generation

**mPDF Performance:**
- Balanced performance for most use cases
- Good memory management with temporary file cleanup
- Slightly larger files due to embedded fonts
- Performance degrades with complex CSS layouts

**DomPDF Performance Issues:**
- Slowest of the three libraries
- High memory consumption, especially with large documents
- Performance improves with OPcache and GD extensions
- IMagick extension recommended for better image processing

### Scaling Considerations

- **Low Volume (<100 PDFs/day):** All libraries perform adequately
- **Medium Volume (100-1000 PDFs/day):** TCPDF preferred for speed
- **High Volume (>1000 PDFs/day):** TCPDF with caching mechanisms
- **Complex Layouts:** mPDF or DomPDF, depending on CSS requirements

## CSS Support

### CSS Compliance Levels

| CSS Feature | mPDF | TCPDF | DomPDF |
|-------------|------|-------|--------|
| CSS 2.1 Core | Partial (dated) | Basic | Good |
| CSS 3 Support | Limited | Minimal | Partial |
| @media queries | Limited | No | Yes |
| @page rules | Yes | Yes | Yes |
| Flexbox | No | No | No |
| Grid | No | No | No |
| Custom Properties | No | No | No |

### CSS Support Analysis

**DomPDF:**
- Most CSS-compliant of the three
- Supports @import, @media, @page
- Good handling of complex selectors
- Excellent box model implementation
- Limitations: No flexbox, no CSS Grid, no custom properties

**mPDF:**
- Good CSS support for layout and styling
- Supports @page for print media
- Handles complex layouts well
- CSS support is dated (last major update years ago)
- May require mPDF-specific CSS adjustments

**TCPDF:**
- Basic CSS support through XHTML parsing
- Limited to simple styling
- Not suitable for complex CSS layouts
- Better for programmatic styling

### CSS Recommendations

For InvoiceForge's needs (professional invoice layouts):
- **mPDF:** Best balance of CSS support and performance
- **DomPDF:** Excellent for CSS-driven designs, but slower
- **TCPDF:** Not recommended for CSS-heavy templates

## Template Handling

### Template Approaches

**mPDF:**
```php
$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML('<html><body>...template...</body></html>');
$mpdf->Output();
```

**TCPDF:**
```php
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->writeHTML('...template...', true, false, true, false, '');
$pdf->Output();
```

**DomPDF:**
```php
$dompdf = new Dompdf\Dompdf();
$dompdf->loadHtml('<html><body>...template...</body></html>');
$dompdf->render();
$dompdf->stream();
```

### Template Features

| Feature | mPDF | TCPDF | DomPDF |
|---------|------|-------|--------|
| HTML Templates | ✓ | ✓ | ✓ |
| Template Variables | Via PHP | Via PHP | Via PHP |
| Partial Templates | ✓ | Limited | ✓ |
| Asset Embedding | ✓ | ✓ | ✓ |
| Base64 Images | ✓ | ✓ | ✓ |
| External Resources | ✓ | ✓ | Configurable |

### WordPress Template Integration

For InvoiceForge WordPress plugin:

**mPDF Integration Pattern:**
```php
// Load WordPress template
ob_start();
include get_template_part('invoice', 'template');
$html = ob_get_clean();

// Generate PDF
$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output('invoice.pdf', 'D');
```

## Integration Patterns

### Composer Installation

**mPDF:**
```bash
composer require mpdf/mpdf
```

**TCPDF:**
```bash
composer require tecnickcom/tcpdf
```

**DomPDF:**
```bash
composer require dompdf/dompdf
```

### WordPress Plugin Integration

**Key Considerations:**
- Namespace conflicts (use unique prefixes)
- WordPress file system functions
- Memory limits and execution time
- Temporary file management
- Security (input sanitization)

**Recommended Pattern for InvoiceForge:**
```php
namespace InvoiceForge\PDF;

use \Mpdf\Mpdf;

class Generator {
    private $mpdf;
    
    public function __construct() {
        $this->mpdf = new Mpdf([
            'tempDir' => $this->getTempDir(),
            'mode' => 'utf-8'
        ]);
    }
    
    public function generateInvoice($invoice_data) {
        $html = $this->renderTemplate($invoice_data);
        $this->mpdf->WriteHTML($html);
        return $this->mpdf->Output('', 'S'); // Return as string
    }
}
```

### Framework Integrations

- **mPDF:** Laravel (barryvdh/laravel-mpdf), Symfony bundles available
- **TCPDF:** Limited framework integrations
- **DomPDF:** Laravel (barryvdh/laravel-dompdf), Symfony (nucleos/dompdf-bundle)

## WordPress Integration Considerations

### Plugin Architecture Fit

**mPDF Advantages for WordPress:**
- Excellent HTML/CSS template compatibility
- WordPress theme integration seamless
- Supports WordPress shortcodes and filters
- Good handling of WordPress media library assets

**Integration Challenges:**
- Memory usage with large documents
- Temporary file cleanup in shared hosting
- PHP version compatibility across WordPress installs
- Font loading and caching

### Security Considerations

**Input Sanitization:**
```php
// Always sanitize template data
$safe_data = wp_kses_post($user_input);
$mpdf->WriteHTML($safe_data);
```

**File System Security:**
```php
// Use WordPress upload directory for temp files
$upload_dir = wp_upload_dir();
$temp_dir = $upload_dir['basedir'] . '/invoiceforge-temp/';
wp_mkdir_p($temp_dir);
```

### Performance Optimization

**WordPress-Specific Optimizations:**
- Use WordPress object caching for PDF templates
- Implement PDF caching with expiration
- Use background processing for large PDF generations
- Monitor memory usage with `wp_memory_limit`

## Comparison and Recommendations

### Head-to-Head Comparison

| Criteria | Winner | Reasoning |
|----------|--------|-----------|
| CSS Support | DomPDF | Most CSS 2.1 compliant |
| Performance | TCPDF | Fastest generation, lowest memory |
| Template Flexibility | mPDF | Best HTML/CSS template handling |
| WordPress Integration | mPDF | Seamless with WordPress themes |
| Feature Completeness | TCPDF | Most comprehensive PDF features |
| Maintenance | mPDF | Active development, good community |

### Recommendation for InvoiceForge

**Primary Choice: mPDF**

**Rationale:**
1. **CSS Support:** Sufficient for professional invoice layouts
2. **Template Handling:** Excellent integration with WordPress themes
3. **WordPress Ecosystem:** Widely used in WordPress plugins
4. **Balance:** Good compromise between features and performance
5. **Maturity:** Stable with active maintenance

**Alternative: DomPDF**
- Consider if CSS compliance is paramount
- Suitable for highly customized invoice designs
- May require performance optimizations

**Avoid: TCPDF**
- Limited CSS support makes template maintenance difficult
- Better suited for programmatic PDF generation
- Less ideal for HTML-based invoice templates

### Migration Considerations

If switching from another library:
- **From TCPDF to mPDF:** Significant template rework required
- **From DomPDF to mPDF:** Minimal changes, mainly performance tuning
- **Template Conversion:** Test all CSS styles and layouts thoroughly

## Implementation Roadmap

### Phase 1: Core Integration
- Install mPDF via Composer
- Create basic PDF generator class
- Implement simple invoice template
- Add WordPress hooks and filters

### Phase 2: Template Enhancement
- Develop responsive invoice templates
- Implement custom CSS for branding
- Add support for company logos and signatures
- Create template customization options

### Phase 3: Performance Optimization
- Implement PDF caching mechanisms
- Add background processing for large batches
- Optimize memory usage and temporary files
- Add performance monitoring

### Phase 4: Advanced Features
- Digital signatures and encryption
- Bulk PDF generation
- Email attachment integration
- Print optimization features

## Sources and References

### Official Documentation
- [mPDF Manual](https://mpdf.github.io/) - Comprehensive documentation
- [TCPDF Documentation](https://tcpdf.org/docs/) - Extensive examples
- [DomPDF Documentation](https://dompdf.github.io/) - CSS-focused guides

### GitHub Repositories
- [mPDF GitHub](https://github.com/mpdf/mpdf) - 4.7k stars, active development
- [TCPDF GitHub](https://github.com/tecnickcom/tcpdf) - 4.5k stars, support mode
- [DomPDF GitHub](https://github.com/dompdf/dompdf) - 11.1k stars, active

### Community Resources
- Stack Overflow mPDF tag - Active community support
- WordPress Plugin Directory - mPDF integration examples
- PHP community forums - Performance comparisons

### Performance Benchmarks
- Community-reported benchmarks (2020-2026)
- GitHub issue discussions on performance
- WordPress plugin reviews and comparisons

## Conclusion

mPDF is the optimal choice for InvoiceForge's PDF generation needs, offering the best balance of CSS support, template flexibility, and WordPress integration. While DomPDF provides superior CSS compliance and TCPDF offers better performance, mPDF's mature ecosystem and WordPress compatibility make it the most practical solution for a production invoice management plugin.

The research indicates that mPDF will scale well for typical WordPress usage patterns, with room for performance optimizations as the plugin grows. Template handling capabilities ensure that invoice designs can leverage modern CSS while maintaining compatibility with WordPress themes and customization options.</content>
<parameter name="filePath">.planning/research/pdf-generation.md