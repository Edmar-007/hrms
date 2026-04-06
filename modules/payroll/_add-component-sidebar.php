<?php
/**
 * Add Component Sidebar View
 */
?>
<div class="card">
    <div class="card-header"><h6 class="mb-0">Add Component</h6></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_component">
            <div class="mb-3">
                <label class="form-label">Type</label>
                <select class="form-select" name="component_type" required>
                    <option value="">-- Select --</option>
                    <option value="earning">Earning</option>
                    <option value="deduction">Deduction</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Component Name</label>
                <input type="text" class="form-control" name="name" placeholder="e.g., Basic Salary, SSS" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Value Type</label>
                <select class="form-select" name="type" required>
                    <option value="fixed">Fixed Amount (₱)</option>
                    <option value="percentage">Percentage (%)</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Value</label>
                <input type="number" class="form-control" name="value" step="0.01" placeholder="0.00" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Order (Priority)</label>
                <input type="number" class="form-control" name="order_seq" value="0">
                <small class="text-muted">Lower number = displayed first</small>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-plus-circle me-2"></i>Add Component
            </button>
        </form>
    </div>
</div>

<!-- Template Suggestions -->
<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Common Components</h6></div>
    <div class="card-body text-muted small">
        <strong>Earnings:</strong>
        <ul class="mb-3">
            <li>Basic Salary</li>
            <li>Transport Allowance</li>
            <li>Phone Allowance</li>
            <li>HRA / Housing</li>
        </ul>
        <strong>Deductions:</strong>
        <ul>
            <li>SSS (4.5%)</li>
            <li>PhilHealth (3.5%)</li>
            <li>Pag-IBIG (2%)</li>
            <li>Withholding Tax</li>
            <li>Loans</li>
        </ul>
    </div>
</div>
