<?php

declare(strict_types=1);

namespace Pdo;

$database = new DataBase(new ConnectionPool());

// Start several concurrent transactions
foreach (['John Doe', 'Jane2 Doe'] as $user) {
    spawn use ($database) {
        $pdo = $database->getConnection();
    
        $pdo->beginTransaction();
        
        try {
            // Check if the user already exists
            $stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE name = ?', [$user]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                // User already exists, rollback the transaction
                $pdo->rollBack();
                return;
            }
            
            $pdo->query('INSERT INTO users (name) VALUES (?)', [$user]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            // Handle the exception
            echo "Error: " . $exception->getMessage();
            $pdo->rollBack();
        }
    };
}