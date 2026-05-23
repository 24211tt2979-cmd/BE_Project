/**
 * Debug Cart & Checkout Issues
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Cart Debug Script Loaded');
    
    // Check if any element is blocking clicks
    const checkBlockingElements = () => {
        const elements = document.querySelectorAll('.quick-view-modal, .search-overlay, .mobile-drawer');
        elements.forEach(el => {
            if (el) {
                const style = window.getComputedStyle(el);
                if (style.display !== 'none' || style.visibility !== 'hidden') {
                    console.warn('⚠️ Potentially blocking element:', el.className, {
                        display: style.display,
                        visibility: style.visibility,
                        opacity: style.opacity,
                        zIndex: style.zIndex
                    });
                }
            }
        });
    };
    
    checkBlockingElements();
    
    // Add click listeners to all "Add to Cart" buttons
    const addToCartLinks = document.querySelectorAll('a[href*="cart.php?add="]');
    console.log(`🛒 Found ${addToCartLinks.length} "Add to Cart" links`);
    
    addToCartLinks.forEach((link, index) => {
        console.log(`Link ${index + 1}:`, link.href);
        
        link.addEventListener('click', function(e) {
            console.log('✅ Add to cart clicked:', this.href);
            console.log('Session should be active');
        });
    });
    
    // Check buttons
    const buttons = document.querySelectorAll('.btn-main, button[type="submit"]');
    console.log(`🔘 Found ${buttons.length} buttons`);
    
    buttons.forEach((btn, index) => {
        const style = window.getComputedStyle(btn);
        const rect = btn.getBoundingClientRect();
        
        if (rect.width === 0 || rect.height === 0) {
            console.warn(`❌ Button ${index} has zero size:`, btn);
        }
        
        if (style.pointerEvents === 'none') {
            console.warn(`❌ Button ${index} has pointer-events: none:`, btn);
        }
        
        btn.addEventListener('click', function(e) {
            console.log('🖱️ Button clicked:', this.textContent?.trim() || this.className);
        });
    });
    
    // Test form submission
    const forms = document.querySelectorAll('form');
    forms.forEach((form, index) => {
        form.addEventListener('submit', function(e) {
            console.log('📝 Form submitted:', index, this.action);
        });
    });
    
    console.log('✅ Debug setup complete. Try clicking buttons and check console.');
});
