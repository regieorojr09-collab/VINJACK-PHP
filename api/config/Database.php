<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

// Maintain backward compatibility for global namespace calls
if (!class_exists('Database', false)) {
    class_alias('Core\Database', 'Database');
}
