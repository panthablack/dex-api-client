#!/usr/bin/env node

import { execSync } from 'child_process';
import { readFileSync } from 'fs';

console.log('🔍 Running simple validation checks...\n');

// Test 1: Check if the page loads
console.log('1. Testing if clients page loads...');
try {
    const html = execSync('curl -s http://localhost:8000/data-exchange/clients', { encoding: 'utf8' });
    if (html.includes('<title>Clients - DSS Data Exchange</title>')) {
        console.log('✅ Page loads successfully');
    } else {
        console.log('❌ Page failed to load or missing title');
    }
} catch (error) {
    console.log('❌ Failed to load page:', error.message);
}

// Test 2: Check Alpine.js integration
console.log('\n2. Testing Alpine.js integration...');
try {
    const html = execSync('curl -s http://localhost:8000/data-exchange/clients', { encoding: 'utf8' });
    
    const checks = [
        { test: 'Alpine.js CDN loaded', pattern: /alpinejs@3\.x\.x/ },
        { test: 'x-data attribute present', pattern: /x-data="resourceTable\(\)"/ },
        { test: 'x-on:click bindings', pattern: /x-on:click="refreshData\(\)"/ },
        { test: 'x-bind:disabled attributes', pattern: /x-bind:disabled="isRefreshing"/ },
        { test: 'x-text bindings', pattern: /x-text="isRefreshing \? 'Refreshing\.\.\.' : 'Refresh'"/ },
        { test: 'x-cloak attributes', pattern: /x-cloak/ },
    ];
    
    checks.forEach(check => {
        if (check.pattern.test(html)) {
            console.log(`✅ ${check.test}`);
        } else {
            console.log(`❌ ${check.test} - MISSING`);
        }
    });
} catch (error) {
    console.log('❌ Failed to check Alpine.js integration:', error.message);
}

// Test 3: Check resource table structure
console.log('\n3. Testing resource table structure...');
try {
    const html = execSync('curl -s http://localhost:8000/data-exchange/clients', { encoding: 'utf8' });
    
    const structureChecks = [
        { test: 'Table element present', pattern: /<table class="table table-striped table-hover">/ },
        { test: 'Table headers present', pattern: /<th>Client ID<\/th>/ },
        { test: 'Action buttons present', pattern: /title="View Details"/ },
        { test: 'Bootstrap classes correct', pattern: /class="btn btn-outline-primary btn-sm"/ },
        { test: 'Data rows present', pattern: /<tbody>[\s\S]*<tr>/ },
    ];
    
    structureChecks.forEach(check => {
        if (check.pattern.test(html)) {
            console.log(`✅ ${check.test}`);
        } else {
            console.log(`❌ ${check.test} - MISSING`);
        }
    });
} catch (error) {
    console.log('❌ Failed to check table structure:', error.message);
}

// Test 4: Check JavaScript function definitions
console.log('\n4. Testing JavaScript function definitions...');
try {
    const html = execSync('curl -s http://localhost:8000/data-exchange/clients', { encoding: 'utf8' });
    
    const jsChecks = [
        { test: 'resourceTable function defined', pattern: /function resourceTable\(\)/ },
        { test: 'viewResource method present', pattern: /viewResource\(resourceType, resourceId\)/ },
        { test: 'showUpdateForm method present', pattern: /showUpdateForm\(resourceType, resourceId\)/ },
        { test: 'confirmDelete method present', pattern: /confirmDelete\(resourceType, resourceId\)/ },
        { test: 'CSRF token handling', pattern: /'X-CSRF-TOKEN': document\.querySelector\('meta\[name="csrf-token"\]'\)/ },
        { test: 'Bootstrap Modal integration', pattern: /bootstrap\.Modal/ },
    ];
    
    jsChecks.forEach(check => {
        if (check.pattern.test(html)) {
            console.log(`✅ ${check.test}`);
        } else {
            console.log(`❌ ${check.test} - MISSING`);
        }
    });
} catch (error) {
    console.log('❌ Failed to check JavaScript functions:', error.message);
}

// Test 5: Check modals structure
console.log('\n5. Testing modal structure...');
try {
    const html = execSync('curl -s http://localhost:8000/data-exchange/clients', { encoding: 'utf8' });
    
    const modalChecks = [
        { test: 'View modal present', pattern: /<div class="modal fade" id="viewModal"/ },
        { test: 'Update modal present', pattern: /<div class="modal fade" id="updateModal"/ },
        { test: 'Delete modal present', pattern: /<div class="modal fade" id="deleteModal"/ },
        { test: 'Modal loading spinners', pattern: /x-show="modalLoading === 'view'"/ },
        { test: 'Delete button loading state', pattern: /x-bind:disabled="modalLoading === 'delete'"/ },
    ];
    
    modalChecks.forEach(check => {
        if (check.pattern.test(html)) {
            console.log(`✅ ${check.test}`);
        } else {
            console.log(`❌ ${check.test} - MISSING`);
        }
    });
} catch (error) {
    console.log('❌ Failed to check modal structure:', error.message);
}

// Test 6: Check for potential issues
console.log('\n6. Checking for potential issues...');
try {
    const html = execSync('curl -s http://localhost:8000/data-exchange/clients', { encoding: 'utf8' });
    
    const issueChecks = [
        { test: 'No PHP errors visible', pattern: /<\?php|Fatal error|Parse error/, shouldMatch: false },
        { test: 'No obvious JavaScript syntax errors', pattern: /SyntaxError|Unexpected token/, shouldMatch: false },
        { test: 'CSS loaded properly', pattern: /bootstrap@5\.3\.0/ },
        { test: 'FontAwesome loaded', pattern: /font-awesome/ },
        { test: 'CSRF token present', pattern: /<meta name="csrf-token" content="[^"]+">/ },
    ];
    
    issueChecks.forEach(check => {
        const matches = check.pattern.test(html);
        const expected = check.shouldMatch !== false;
        
        if (matches === expected) {
            console.log(`✅ ${check.test}`);
        } else {
            console.log(`❌ ${check.test} - ISSUE DETECTED`);
        }
    });
} catch (error) {
    console.log('❌ Failed to check for potential issues:', error.message);
}

console.log('\n🔍 Validation complete!');