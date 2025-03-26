<?php

function collaborativeEditorServer(string $host, int $port): void {
    async $serverScope {
        // Data structures
        $documents = [];
        $clients = [];
        $documentClients = [];
        
        // Setup WebSocket server
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, $host, $port);
        socket_listen($server, 10);
        
        echo "Collaborative editor server running on $host:$port\n";
        
        // Document auto-save
        spawn with $serverScope use(&$documents) {
            while (true) {
                await spawn Async\delay(30000); // Every 30 seconds
                
                foreach ($documents as $docId => $document) {
                    if ($document['dirty']) {
                        echo "Auto-saving document $docId\n";
                        await saveDocument($docId, $document['content']);
                        $documents[$docId]['dirty'] = false;
                    }
                }
            }
        };
        
        // Connection acceptor
        spawn with $serverScope use($server, &$clients, &$documentClients, &$documents) {
            while (true) {
                $client = await spawn socket_accept($server);
                $clientId = uniqid('client_');
                
                echo "New client connected: $clientId\n";
                $clients[$clientId] = [
                    'socket' => $client,
                    'documentId' => null
                ];
                
                // Handle client in a separate coroutine
                spawn with $serverScope use($clientId, $client, &$clients, &$documentClients, &$documents) {
                    try {
                        // Send welcome message
                        await sendToClient($client, [
                            'type' => 'connected',
                            'clientId' => $clientId
                        ]);
                        
                        while (true) {
                            $message = await receiveFromClient($client);
                            
                            if (!$message) {
                                break; // Client disconnected
                            }
                            
                            switch ($message['type']) {
                                case 'open_document':
                                    await handleOpenDocument($clientId, $message['documentId'],
                                                          $clients, $documentClients, $documents);
                                    break;
                                    
                                case 'edit':
                                    await handleEdit($clientId, $message,
                                                  $clients, $documentClients, $documents);
                                    break;
                                    
                                case 'cursor_move':
                                    await handleCursorMove($clientId, $message,
                                                        $clients, $documentClients);
                                    break;
                            }
                        }
                    } catch (Exception $e) {
                        echo "Error handling client $clientId: " . $e->getMessage() . "\n";
                    } finally {
                        // Clean up on disconnect
                        cleanupClient($clientId, $clients, $documentClients);
                    }
                };
            }
        };
        
        // Wait for shutdown signal
        try {
            await spawn Async\signal(SIGINT);
        } finally {
            echo "Shutting down editor server...\n";
            
            // Save all dirty documents
            foreach ($documents as $docId => $document) {
                if ($document['dirty']) {
                    await saveDocument($docId, $document['content']);
                }
            }
            
            // Clean up connections
            foreach ($clients as $clientData) {
                socket_close($clientData['socket']);
            }
            
            socket_close($server);
        }
    }
}