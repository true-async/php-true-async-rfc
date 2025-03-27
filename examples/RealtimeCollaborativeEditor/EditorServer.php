<?php

declare(strict_types=1);

namespace RealtimeCollaborativeEditor;

use Async\Scope;
use function Async\delay;

final class EditorServer
{
    private array $documents = [];
    private array $clients = [];
    private array $documentClients = [];
    private \Socket $server;
    private Scope $serverScope;
    private Scope $documentScope;
    
    public function __construct(public readonly string $host, public readonly int $port)
    {
        $this->serverScope = new Scope();
        $this->documentScope = new Scope();
    }
    
    public function start(): void
    {
        // Setup WebSocket server
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if ($server === false) {
            throw new \RuntimeException('Failed to create socket: ' . socket_strerror(socket_last_error()));
        }
        
        try {
            socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
            
            if(false === socket_bind($server, $this->host, $this->port)) {
                throw new \RuntimeException('Failed to bind socket: ' . socket_strerror(socket_last_error($server)));
            }
            
            if(socket_listen($server, 10)) {
                echo "Collaborative editor server running on {$this->host}:{$this->port}\n";
            } else {
                throw new \RuntimeException('Failed to listen on socket: ' . socket_strerror(socket_last_error($server)));
            }
        } finally {
            socket_close($server);
        }
        
        $this->server = $server;
        
        spawn with $this->serverScope $this->connectionAcceptor();
        spawn with $this->documentScope $this->documentAutoSave();
    }
    
    private function connectionAcceptor(): void
    {
        while (($client = socket_accept($this->server)) !== false) {
            $clientId   = uniqid('client_');
            
            echo "New client connected: $clientId\n";
            
            $clientScope = Scope::inherit();
            
            $this->clients[$clientId] = [
                'socket'        => $client,
                'documentId'    => null,
                'clientScope'   => $clientScope,
            ];
            
            // Handle client in a separate coroutine
            spawn with $clientScope $this->handleClient($clientId, $client, $clientScope);
        }
    }
    
    private function documentAutoSave(): void
    {
        $saveAll = function () {
            foreach ($this->documents as $docId => $document) {
                if ($document['dirty']) {
                    echo "Auto-saving document $docId\n";
                    
                    try {
                        $this->documents[$docId]['blocked'] = true;
                        $this->saveDocument($docId, $document['content']);
                        $this->documents[$docId]['dirty'] = false;
                    } finally {
                        $this->documents[$docId]['blocked'] = false;
                    }
                }
            }
        };

        try {
            while (true) {
                delay(30000); // Every 30 seconds
                $saveAll();
            }
        } finally {
            // Ensure all documents are saved before exiting
            $saveAll();
        }
    }
    
    private function handleClient(string $clientId, \Socket $client, Scope $clientScope): void
    {
        try {
            // Send a welcome message
            $this->sendToClient($client, [
                'type' => 'connected',
                'clientId' => $clientId,
            ]);
            
            while (true) {
                $message = $this->receiveFromClient($client);
                
                if (!$message) {
                    break; // Client disconnected
                }
                
                switch ($message['type']) {
                    case 'open_document':
                        $this->handleOpenDocument($clientId, $message);
                        break;
                    
                    case 'edit':
                        $this->handleEdit($clientId, $message);
                        break;
                    
                    case 'cursor_move':
                        $this->handleCursorMove($clientId, $message);
                        break;
                }
            }
        } catch (\Exception $e) {
            echo "Error handling client $clientId: " . $e->getMessage() . "\n";
        } finally {
            // Clean up on disconnect
            $this->cleanupClient($clientId);
        }
    }
    
    private function sendToClient(\Socket $client, array $message): void
    {
        $jsonMessage = json_encode($message);
        
        if ($jsonMessage === false) {
            throw new \RuntimeException('Failed to encode message: ' . json_last_error_msg());
        }
        
        $length = strlen($jsonMessage);
        $header = pack('C*', 0x81, $length);
        
        socket_write($client, $header . $jsonMessage, strlen($header) + $length);
    }
    
    private function receiveFromClient(\Socket $client): ?array
    {
        $data = socket_read($client, 2048, PHP_NORMAL_READ);
        
        if ($data === false) {
            return null; // Client disconnected
        }
        
        $length = ord($data[1]) & 127;
        
        if ($length > 125) {
            throw new \RuntimeException('Message too long');
        }
        
        $message = substr($data, 2, $length);
        
        return json_decode($message, true);
    }
    
    private function handleOpenDocument(string $clientId, array $message): void
    {
        $documentId = uniqid('document_');
        
        if (!isset($this->documents[$documentId])) {
            $this->documents[$documentId] = [
                'content' => '',
                'dirty' => false,
            ];
        }
        
        $this->clients[$clientId]['documentId'] = $documentId;
        $this->documentClients[$documentId][$clientId] = $this->clients[$clientId];
        
        // Send the document content to the client
        $this->sendToClient($this->clients[$clientId]['socket'], [
            'type' => 'document_content',
            'content' => $this->documents[$documentId]['content'],
            'documentId' => $documentId,
        ]);
    }
    
    private function handleEdit(string $clientId, array $message): void
    {
        $documentId = $this->clients[$clientId]['documentId'];
        
        if (isset($this->documents[$documentId])) {
            $this->documents[$documentId]['content'] = $message['content'];
            $this->documents[$documentId]['dirty'] = true;
            
            // Broadcast the edit to other clients
            foreach ($this->documentClients[$documentId] as $otherClient) {
                if ($otherClient['clientId'] !== $clientId) {
                    spawn $this->sendToClient($otherClient['socket'], [
                        'type' => 'edit',
                        'content' => $message['content'],
                        'clientId' => $clientId,
                    ]);
                }
            }
        }
    }
    
    private function handleCursorMove(string $clientId, array $message): void
    {
        $documentId = $this->clients[$clientId]['documentId'];
        
        if (isset($this->documentClients[$documentId])) {
            // Broadcast the cursor move to other clients
            foreach ($this->documentClients[$documentId] as $otherClient) {
                if ($otherClient['clientId'] !== $clientId) {
                    spawn $this->sendToClient($otherClient['socket'], [
                        'type' => 'cursor_move',
                        'position' => $message['position'],
                        'clientId' => $clientId,
                    ]);
                }
            }
        }
    }
    
    private function saveDocument(string $docId, string $content): void
    {
        $fileName = __DIR__ . "/documents/{$docId}.txt";
        file_put_contents($fileName, $content);
    }

    private function cleanupClient(string $clientId): void
    {
        if (isset($this->clients[$clientId])) {
            $documentId = $this->clients[$clientId]['documentId'];
            
            if ($documentId && isset($this->documentClients[$documentId][$clientId])) {
                unset($this->documentClients[$documentId][$clientId]);
                
                if (empty($this->documentClients[$documentId])) {
                    unset($this->documents[$documentId]);
                }
            }
            
            unset($this->clients[$clientId]);
        }
    }
    
    public function stop(): void
    {
        try {
            $this->serverScope->cancel();
        } finally {
            // Clean up connections
            foreach ($this->clients as $clientData) {
                socket_close($clientData['socket']);
            }
            
            socket_close($this->server);
            
            $this->documentScope->awaitAll(true);
        }
    }
    
    public function __destruct()
    {
        $this->stop();
    }
}