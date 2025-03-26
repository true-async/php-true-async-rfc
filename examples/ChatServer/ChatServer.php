<?php

function handleChatServer(string $host, int $port): void
{
    async $serverScope {
        // Store connected clients
        $clients = [];
        
        // Create server socket
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, $host, $port);
        socket_listen($server, 10);
        
        echo "Chat server started on $host:$port\n";
        
        // Accept new connections
        spawn use($server, &$clients, $serverScope) {
            while (true) {
                $client = await spawn socket_accept($server);
                $id = uniqid('client_');
                $clients[$id] = $client;
                
                // Handle each client in a separate coroutine
                spawn with $serverScope use($client, $id, &$clients) {
                    try {
                        // Send welcome message
                        await spawn socket_write($client, "Welcome to the chat room!\n");
                        
                        // Read messages from this client and broadcast them
                        while (true) {
                            $message = await spawn socket_read($client, 1024);
                            if ($message === false || $message === '') {
                                break; // Client disconnected
                            }
                            
                            $broadcastMsg = "Client $id: $message";
                            echo $broadcastMsg;
                            
                            // Broadcast to all other clients
                            foreach ($clients as $cid => $c) {
                                if ($cid !== $id) {
                                    spawn socket_write($c, $broadcastMsg);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        echo "Error handling client $id: " . $e->getMessage() . "\n";
                    } finally {
                        // Clean up when client disconnects
                        socket_close($client);
                        unset($clients[$id]);
                        echo "Client $id disconnected\n";
                    }
                };
            }
        };
        
        // Wait for Ctrl+C
        try {
            await spawn Async\signal(SIGINT);
        } finally {
            echo "Shutting down server...\n";
            foreach ($clients as $client) {
                socket_close($client);
            }
            socket_close($server);
        }
    }
}

// Start the server
handleChatServer('127.0.0.1', 8080);