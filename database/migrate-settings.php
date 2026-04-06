<?php
/**
 * Migration: Add Settings Tables & Columns
 * Run: php database/migrate-settings.php
 */

require_once __DIR__ . '/../config/db.php';

$cid = 1; // Default company ID
$executed = 0;
$errors = [];

$pdo->exec("SET FOREIGN_KEY_CHECKS=0;");

$schema = [
    // Attendance settings (used in modules/settings/index.php)
    "CREATE TABLE IF NOT EXISTS `attendance_settings` (
      `company_id` int(11) NOT NULL PRIMARY KEY,
      `grace_period_minutes` int(11) DEFAULT 10,
      `duplicate_scan_seconds` int(11) DEFAULT 3,
      `require_action_sequence` tinyint(1) DEFAULT 1,
      `gps_capture_enabled` tinyint(1) DEFAULT 0,
      `out_of_shift_grace_before_minutes` int(11) DEFAULT 60,
      `out_of_shift_grace_after_minutes` int(11) DEFAULT 60,
      INDEX `company_id` (`company_id`)
    ) ENGINE=InnoDB;",

    // Company settings (email, notifications)
    "CREATE TABLE IF NOT EXISTS `company_settings` (
      `company_id` int(11) NOT NULL PRIMARY KEY,
      `smtp_host` varchar(255) DEFAULT NULL,
      `smtp_port` int(11) DEFAULT 587,
      `smtp_user` varchar(255) DEFAULT NULL,
      `smtp_secure` enum('tls','ssl','') DEFAULT 'tls',
      `smtp_from` varchar(255) DEFAULT NULL,
      `webhooks` json DEFAULT NULL,
      INDEX `company_id` (`company_id`)
    ) ENGINE=InnoDB;",

    // Payroll settings
    "CREATE TABLE IF NOT EXISTS `payroll_settings` (
      `company_id` int(11) NOT NULL PRIMARY KEY,
      `period_type` enum('daily','weekly','semi-monthly','monthly') DEFAULT 'semi-monthly',
      `cutoff_date` int(11) DEFAULT 15,
      `tax_method` enum('net','gross','table') DEFAULT 'net',
      `tax_year` year(4) DEFAULT (YEAR(CURDATE())),
      INDEX `company_id` (`company_id`)
    ) ENGINE=InnoDB;",

    "CREATE TABLE IF NOT EXISTS `payroll_deduction_types` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `company_id` int(11) NOT NULL,
      `name` varchar(100) NOT NULL,
      `is_fixed_amount` tinyint(1) DEFAULT 0,
      `rate_percent` decimal(5,2) DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `company_id` (`company_id`)
    ) ENGINE=InnoDB;",

    "CREATE TABLE IF NOT EXISTS `payroll_allowance_types` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `company_id` int(11) NOT NULL,
      `name` varchar(100) NOT NULL,
      `is_fixed_amount` tinyint(1) DEFAULT 1,
      `amount_fixed` decimal(10,2) DEFAULT 0.00,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `company_id` (`company_id`)
    ) ENGINE=InnoDB;",

    "CREATE TABLE IF NOT EXISTS `payroll_tax_settings` (
      `company_id` int(11) NOT NULL PRIMARY KEY,
      `sss_rate_employee` decimal(5,4) DEFAULT 0.0470,
      `sss_rate_employer` decimal(5,4) DEFAULT 0.0940,
      `philhealth_rate` decimal(5,4) DEFAULT 0.0250,
      `pagibig_rate` decimal(5,4) DEFAULT 0.0200,
      `tax_exemption_threshold` decimal(10,2) DEFAULT 0.00,
      `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `company_id` (`company_id`)
    ) ENGINE=InnoDB;"
];

try {
    foreach ($schema as $sql) {
        $pdo->exec($sql);
        $executed++;
        echo "✓ Executed: " . trim(substr($sql, 0, 50)) . "...\n";
    }

    // Add missing columns to existing tables
    $columnUpdates = [
        'companies' => [
            'notification_prefs JSON DEFAULT NULL AFTER nav_settings',
            'api_keys JSON DEFAULT NULL AFTER notification_prefs',
            'webhooks JSON DEFAULT NULL AFTER api_keys'
        ]
    ];

    foreach ($columnUpdates as $table => $columns) {
        foreach ($columns as $colDef) {
            try {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS " . $colDef);
                echo "✓ Added column to $table\n";
            } catch (PDOException $e) {
                echo "⚠ Column may already exist in $table: {$e->getMessage()}\n";
            }
        }
    }

    // Insert defaults
    $pdo->prepare("INSERT IGNORE INTO `attendance_settings` (company_id) VALUES (?)")->execute([$cid]);
    $pdo->prepare("INSERT IGNORE INTO `company_settings` (company_id) VALUES (?)")->execute([$cid]);
    $pdo->prepare("INSERT IGNORE INTO `payroll_settings` (company_id) VALUES (?)")->execute([$cid]);
    $pdo->prepare("INSERT IGNORE INTO `payroll_tax_settings` (company_id) VALUES (?)")->execute([$cid]);

    // Sample deduction/allowance types
    $pdo->prepare("INSERT IGNORE INTO `payroll_deduction_types` (company_id, name, is_fixed_amount) VALUES (?, 'SSS', 0), (?, 'PhilHealth', 0), (?, 'Pag-IBIG', 0)")->execute([$cid, $cid, $cid]);
    $pdo->prepare("INSERT IGNORE INTO `payroll_allowance_types` (company_id, name, is_fixed_amount) VALUES (?, 'Transportation', 1), (?, 'Meal', 1)")->execute([$cid, $cid]);

    echo "\n✅ Migration complete! $executed statements executed.\n";
    echo "💡 Run: php database/migrate-settings.php\n";
    echo "📊 New tables ready. Test settings at /modules/settings/\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
?>

