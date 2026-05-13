# AJAX Script Debugging Checklist

## Before AJAX Request
- [ ] **Validate Controller Data**: Ensure all PHP variables passed to Blade are properly formatted
- [ ] **Check for Special Characters**: Look for HTML, quotes, apostrophes in data
- [ ] **Use Proper JSON Encoding**: Always use `json_encode()` or `@json()` directive
- [ ] **Test Blade Templates Independently**: Render templates without AJAX first

## During AJAX Request
- [ ] **Monitor Network Tab**: Check response content and headers
- [ ] **Validate Response Type**: Ensure response is string, not object/array
- [ ] **Check for HTML in JavaScript**: Look for HTML tags inside script blocks
- [ ] **Verify JSON Structure**: Ensure JSON is properly escaped

## After AJAX Response
- [ ] **Extract Scripts Safely**: Use regex to find `<script>` blocks
- [ ] **Validate JavaScript Syntax**: Test each script before execution
- [ ] **Check for Duplicate Content**: Look for repeated closing braces `};`
- [ ] **Handle Special Characters**: Ensure quotes, HTML entities are properly escaped

## Common Issues & Solutions

### Issue 1: `expected expression, got '>'`
**Cause**: HTML tags inside JavaScript context
```blade
<!-- BROKEN -->
<script>
let data = {!! $data !!}; // If $data contains HTML
</script>
```
**Solution**: Use proper JSON encoding
```blade
<!-- SAFE -->
<script>
let data = @json($data); // Properly escaped JSON
</script>
```

### Issue 2: Unescaped Quotes
**Cause**: JSON contains unescaped quotes
```javascript
// BROKEN
let materials = {"name":"Bio-Diesel","description":"Contains "quotes""};
```
**Solution**: Proper JSON encoding handles this automatically

### Issue 3: HTML in JSON Values
**Cause**: JSON values contain HTML tags
```javascript
// PROBLEMATIC
let data = {"content":"<div class=\"item\">Content</div>"};
```
**Solution**: Use data attributes instead of inline JSON
```blade
<div id="data" data-content="{{ json_encode($content) }}"></div>
<script>
let data = $('#data').data(); // jQuery handles parsing safely
</script>
```

## Best Practices

### 1. Separate HTML and JavaScript
```blade
<!-- HTML Template Only -->
<div class="form-group">
    <input type="text" id="machine_reading">
</div>

<!-- Data via Attributes -->
<div id="dpr-data" 
     data-materials="{{ json_encode($materials) }}"
     data-settings="{{ json_encode($settings) }}">
</div>
```

### 2. External JavaScript Handler
```javascript
// Separate JS file
class DPRHandler {
    initialize(container) {
        this.container = $(container);
        this.loadData();
        this.bindEvents();
    }
    
    loadData() {
        const data = $('#dpr-data').data();
        this.materials = data.materials || {};
    }
}
```

### 3. Event-Driven Initialization
```javascript
// In AJAX success
success: function(data) {
    $('#modal').html(data);
    $(document).trigger('dpr:contentLoaded', ['#modal']);
}
```

## Debugging Commands

### Console Debugging
```javascript
// Check AJAX response
$.ajax({
    url: '/test',
    success: function(data) {
        console.log('Response:', data);
        console.log('Type:', typeof data);
        
        // Extract scripts
        const scripts = data.match(/<script[^>]*>([\s\S]*?)<\/script>/gi);
        console.log('Scripts found:', scripts);
        
        // Test each script
        scripts?.forEach((script, index) => {
            try {
                const content = script.replace(/<\/?script[^>]*>/g, '');
                new Function(content);
                console.log(`Script ${index}: OK`);
            } catch (e) {
                console.error(`Script ${index}: ERROR`, e);
            }
        });
    }
});
```

### Network Tab Analysis
1. Open DevTools → Network
2. Trigger AJAX request
3. Click request → Response tab
4. Look for:
   - Malformed JSON
   - HTML in JavaScript context
   - Unescaped special characters
   - Duplicate script content

## Validation Functions

### Safe JSON Test
```javascript
function isValidJSON(str) {
    try {
        JSON.parse(str);
        return true;
    } catch (e) {
        return false;
    }
}
```

### Script Syntax Test
```javascript
function validateScript(code) {
    try {
        new Function(code);
        return true;
    } catch (e) {
        console.error('Script error:', e.message);
        return false;
    }
}
```

## Quick Fixes

### Immediate Fix: Remove Inline Scripts
```javascript
// Temporary fix - strip all scripts
function safeHTML(html) {
    return html.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');
}
```

### Better Fix: Use Data Attributes
```blade
<!-- Instead of inline scripts -->
<div data-items="{{ json_encode($items) }}"></div>

<!-- In JavaScript -->
const items = $('[data-items]').data('items');
```

### Best Fix: Separate Architecture
- HTML templates only
- External JavaScript files
- Event-driven initialization
- Data attributes for complex data
