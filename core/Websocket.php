<?php

/**
 * WebSocket Client Class
 * 
 * Provides a full-featured WebSocket client implementation supporting both ws:// and wss:// protocols.
 * This class handles connection management, frame encoding/decoding, and message transmission according to RFC 6455.
 * 
 * Features:
 * - Supports secure (wss://) and non-secure (ws://) connections
 * - Automatic handshake handling
 * - Frame masking for client-to-server communication
 * - Ping/Pong frame support for connection keep-alive
 * - JSON encoding/decoding support
 * - Buffered reading for efficient data handling
 * - Event listening with callbacks
 * - Custom header support during handshake
 * 
 * @package WebSocket
 * @author  Your Name
 * @version 1.0.0
 * 
 * @property resource $socket The underlying socket resource
 * @property string $address The WebSocket server address/hostname
 * @property int $port The server port number
 * @property string $path The request path including query string
 * @property bool $connected Connection status flag
 * @property bool $ssl Whether to use SSL/TLS connection
 * @property array $headers Custom headers to send during handshake
 * @property string $buffer Internal buffer for partial frame data
 * @property bool $blocking Whether to use blocking mode (default: false for non-blocking)
 * 
 * @example
 * ```php
 * // Create WebSocket connection
 * $ws = new Websocket('wss://example.com/socket', ['Authorization' => 'Bearer token']);
 * $ws->connect();
 * 
 * // Send message
 * $ws->send(['type' => 'message', 'content' => 'Hello']);
 * 
 * // Receive message
 * $response = $ws->receive();
 * 
 * // Listen for messages
 * $ws->listen(function($message) {
 *     echo "Received: " . json_encode($message) . "\n";
 *     return true; // Continue listening, return false to stop
 * });
 * 
 * $ws->disconnect();
 * ```
 * 
 * @throws Exception When connection fails or communication errors occur
 */
class Websocket
{
    private $socket;
    private $address;
    private $port;
    private $path;
    private $connected = false;
    private $ssl = false;
    private $headers = [];
    private $buffer = '';
    private $blocking = false;

    public function __construct($url, $headers = [], $blocking = false)
    {
        $this->parseUrl($url);
        $this->headers = $headers;
        $this->blocking = $blocking;
    }

    /**
     * Parses a WebSocket URL and extracts its components.
     *
     * This method takes a WebSocket URL string and breaks it down into its constituent
     * parts (scheme, host, port, path, and query string), storing them in the object's
     * properties for later use in establishing a WebSocket connection.
     *
     * @param string $url The WebSocket URL to parse (e.g., 'ws://example.com:8080/path?query=value')
     *                    Supported schemes: 'ws' (WebSocket) and 'wss' (WebSocket Secure)
     *
     * @return void
     *
     * @throws none Silently handles missing URL components by using default values
     *
     * @internal Sets the following properties:
     *           - $this->ssl: boolean, true if scheme is 'wss', false otherwise
     *           - $this->address: string, the hostname from the URL
     *           - $this->port: int, the port number (defaults to 443 for wss, 80 for ws)
     *           - $this->path: string, the path and query string (defaults to '/')
     */
    private function parseUrl($url)
    {
        $parsed = parse_url($url);

        $this->ssl = ($parsed['scheme'] ?? 'ws') === 'wss';
        $this->address = $parsed['host'];
        $this->port = $parsed['port'] ?? ($this->ssl ? 443 : 80);
        $this->path = ($parsed['path'] ?? '/');

        if (isset($parsed['query'])) {
            $this->path .= '?' . $parsed['query'];
        }
    }

    /**
     * Establishes a WebSocket connection to the configured server.
     * 
     * Creates a stream socket client connection using either SSL/TLS or plain TCP
     * based on the ssl property. For SSL connections, certificate verification is
     * disabled to allow self-signed certificates.
     * 
     * @throws Exception If the connection cannot be established, throws an exception
     *                   with the error message and error code.
     * 
     * @return void
     * 
     * @see stream_socket_client()
     * @see sendHandshake()
     */
    public function connect()
    {
        $context = stream_context_create();

        if ($this->ssl) {
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);

            $remote = "ssl://{$this->address}:{$this->port}";
        } else {
            $remote = "tcp://{$this->address}:{$this->port}";
        }

        $this->socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($this->socket === false) {
            throw new Exception("Nelze se připojit: {$errstr} ({$errno})");
        }

        stream_set_blocking($this->socket, $this->blocking ? 1 : 0);
        stream_set_timeout($this->socket, 30);

        $this->sendHandshake();
        $this->connected = true;
    }

    /**
     * Sends a WebSocket handshake request to the server and validates the response.
     *
     * This method performs the WebSocket opening handshake as defined in RFC 6455.
     * It generates a random Sec-WebSocket-Key, constructs the HTTP upgrade request,
     * sends it to the server, and validates the server's response.
     *
     * The handshake includes:
     * - HTTP GET request with WebSocket upgrade headers
     * - Custom headers from $this->headers
     * - Sec-WebSocket-Key for server validation
     * - WebSocket protocol version 13
     *
     * After sending, it reads the server response line by line until the end of
     * headers (empty line). It verifies the response contains "101 Switching Protocols"
     * to confirm successful protocol upgrade.
     *
     * Any extra data received after the handshake headers is stored in the buffer
     * for subsequent frame processing.
     *
     * @throws Exception If writing the handshake fails
     * @throws Exception If reading the handshake response fails
     * @throws Exception If the server does not return "101 Switching Protocols"
     * 
     * @return void
     */
    private function sendHandshake()
    {
        $key = base64_encode(random_bytes(16));

        $handshake = "GET {$this->path} HTTP/1.1\r\n" .
            "Host: {$this->address}\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Key: {$key}\r\n" .
            "Sec-WebSocket-Version: 13\r\n";

        // Přidat custom hlavičky
        foreach ($this->headers as $name => $value) {
            $handshake .= "{$name}: {$value}\r\n";
        }

        $handshake .= "\r\n";

        // Pro handshake vždy použít blocking mode
        stream_set_blocking($this->socket, true);
        $written = fwrite($this->socket, $handshake);
        if ($written === false) {
            throw new Exception("Chyba při odesílání handshake");
        }

        // Čtení odpovědi
        $response = '';
        $headers_end = false;

        while (!$headers_end && !feof($this->socket)) {
            $line = fgets($this->socket);
            if ($line === false) {
                throw new Exception("Chyba při čtení handshake odpovědi");
            }

            $response .= $line;

            // Konec hlaviček (prázdný řádek)
            if ($line === "\r\n") {
                $headers_end = true;
            }
        }

        if (strpos($response, '101 Switching Protocols') === false) {
            throw new Exception("Handshake selhal: " . substr($response, 0, 200));
        }

        // ✅ Vrátit zpět na non-blocking pokud bylo nastaveno
        stream_set_blocking($this->socket, $this->blocking ? 1 : 0);

        // Uložit případná data za handshake do bufferu
        $extra = fread($this->socket, 8192);
        if ($extra !== false && strlen($extra) > 0) {
            $this->buffer = $extra;
        }
    }

    /**
     * Sends a message through the WebSocket connection.
     *
     * This method encodes and sends a message to the connected WebSocket server.
     * If the message is an array or object, it will be automatically converted to JSON format.
     *
     * @param string|array|object $message The message to send. Arrays and objects will be JSON encoded.
     * @param int $opcode The WebSocket opcode. Default is 0x1 (text frame).
     *                    Common opcodes: 0x1 (text), 0x2 (binary), 0x8 (close), 0x9 (ping), 0xA (pong)
     *
     * @throws Exception If not connected to WebSocket (message: "Nejste připojeni k websocketu")
     * @throws Exception If there's an error writing to the socket (message: "Chyba při odesílání zprávy")
     *
     * @return void
     */
    public function send($message, $opcode = 0x1)
    {
        if (!$this->connected) {
            throw new Exception("Nejste připojeni k websocketu");
        }

        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        $frame = $this->encode($message, $opcode);
        $written = fwrite($this->socket, $frame);

        if ($written === false) {
            throw new Exception("Chyba při odesílání zprávy");
        }
    }

    /**
     * Receives data from the WebSocket connection.
     *
     * This method reads a frame from the WebSocket connection and optionally decodes it as JSON.
     * If not connected, an exception is thrown.
     *
     * @param bool $decode_json Whether to attempt JSON decoding of the received data. Default is true.
     * @param int|null $timeout Optional timeout in seconds for the stream operation. Default is null (no timeout).
     *
     * @return mixed|null Returns the received data (decoded as array if JSON and $decode_json is true, 
     *                    or raw string otherwise). Returns null if no data is received or connection fails.
     *
     * @throws Exception If not connected to the WebSocket server.
     */
    public function receive($decode_json = true, $timeout = null)
    {
        if (!$this->connected) {
            throw new Exception("Nejste připojeni k websocketu");
        }

        if ($timeout !== null) {
            stream_set_timeout($this->socket, $timeout);
        }

        // ✅ V non-blocking režimu použít stream_select
        if (!$this->blocking) {
            $read = [$this->socket];
            $write = null;
            $except = null;

            // Čekat maximálně 0 sekund (non-blocking check)
            $result = @stream_select($read, $write, $except, 0, 0);

            if ($result === false) {
                throw new Exception("Chyba při stream_select");
            }

            if ($result === 0) {
                // Žádná data k dispozici
                return null;
            }
        }

        $data = $this->readFrame();

        if ($data === false || $data === null) {
            return null;
        }

        if ($decode_json) {
            $decoded = json_decode($data, true);
            return $decoded !== null ? $decoded : $data;
        }

        return $data;
    }

    /**
     * Listens for incoming WebSocket messages and processes them through a callback function.
     * 
     * This method continuously monitors the WebSocket connection for incoming messages
     * and executes the provided callback function for each received message. The loop
     * continues until the connection is closed, an error occurs, or the callback returns false.
     * 
     * @param callable $callback A callback function that processes received messages.
     *                          The callback receives the message as a parameter and should
     *                          return false to stop listening, or any other value to continue.
     * @param bool $decode_json Whether to automatically decode received messages as JSON.
     *                         Defaults to true.
     * 
     * @throws Exception If not connected to the WebSocket server.
     * 
     * @return void
     * 
     * @example
     * $websocket->listen(function($message) {
     *     echo "Received: " . print_r($message, true);
     *     return true; // Continue listening
     * });
     */
    public function listen(callable $callback, $decode_json = true)
    {
        if (!$this->connected) {
            throw new Exception("Nejste připojeni k websocketu");
        }

        echo "[WebSocket] Zahájení poslechu...\n";

        while ($this->connected && $this->isConnected()) {
            try {
                // Zkontrolovat socket
                if (!is_resource($this->socket)) {
                    echo "[WebSocket] Socket není platný resource\n";
                    break;
                }

                // ✅ V non-blocking režimu použít stream_select
                if (!$this->blocking) {
                    $read = [$this->socket];
                    $write = null;
                    $except = null;

                    // Čekat maximálně 1 sekundu
                    $result = @stream_select($read, $write, $except, 1, 0);

                    if ($result === false) {
                        echo "[WebSocket] Chyba při stream_select\n";
                        break;
                    }

                    if ($result === 0) {
                        // Žádná data - pokračovat
                        continue;
                    }
                }

                $message = $this->receive($decode_json, 1);

                if ($message === null) {
                    // Žádná zpráva - pokračovat
                    continue;
                }

                if ($message === false) {
                    echo "[WebSocket] Receive vrátil false\n";
                    break;
                }

                $result = $callback($message);

                if ($result === false) {
                    echo "[WebSocket] Callback ukončil poslech\n";
                    break;
                }
            } catch (Exception $e) {
                echo "[WebSocket] Chyba v listen: " . $e->getMessage() . "\n";
                echo "Stack trace: " . $e->getTraceAsString() . "\n";

                // Počkat a pokračovat
                usleep(500000); // 500ms
            }
        }

        echo "[WebSocket] Poslech ukončen\n";
    }

    /**
     * Reads and decodes a WebSocket frame from the socket connection.
     *
     * This method implements the WebSocket frame protocol (RFC 6455) by:
     * - Reading frame headers (opcode, mask flag, payload length)
     * - Handling extended payload lengths (16-bit and 64-bit)
     * - Processing control frames (ping/pong, close)
     * - Unmasking payload data if masked
     *
     * The method first attempts to read from an internal buffer before reading
     * from the socket to optimize performance with buffered data.
     *
     * Supported opcodes:
     * - 0x8: Close frame - triggers disconnect
     * - 0x9: Ping frame - automatically responds with pong
     * - Other opcodes: Returns decoded payload
     *
     * @return string|false|null The decoded payload string, false on error/disconnect, 
     *                           or null when handling control frames (ping)
     * @throws Exception If there's an error reading the payload data
     */
    private function readFrame()
    {
        // Nejprve zkus přečíst z bufferu
        if (strlen($this->buffer) >= 2) {
            $header = substr($this->buffer, 0, 2);
            $this->buffer = substr($this->buffer, 2);
        } else {
            $header = fread($this->socket, 2);
            // ✅ V non-blocking režimu může fread vrátit false nebo prázdný string
            if ($header === false || $header === '') {
                return null; // Žádná data k dispozici
            }

            if (strlen($header) < 2) {
                // Neúplná hlavička - vrátit do bufferu a zkusit později
                $this->buffer = $header . $this->buffer;
                return null;
            }
        }

        $byte1 = ord($header[0]);
        $byte2 = ord($header[1]);

        $opcode = $byte1 & 0x0F;
        $masked = ($byte2 & 0x80) !== 0;
        $length = $byte2 & 0x7F;

        // Ping frame
        if ($opcode === 0x9) {
            $this->send('', 0xA); // Pong
            return null;
        }

        // Close frame
        if ($opcode === 0x8) {
            $this->disconnect();
            return false;
        }

        // Čtení délky
        if ($length === 126) {
            $extended = $this->readBytes(2);
            $length = unpack('n', $extended)[1];
        } elseif ($length === 127) {
            $extended = $this->readBytes(8);
            // RFC 6455: 64-bit big-endian, použijeme jen dolní 32 bity
            $unpacked = unpack('N2', $extended);
            $length = $unpacked[2]; // Dolní 32 bity (pro PHP na 32-bit systémech)
        }

        // Čtení masky
        if ($masked) {
            $mask = $this->readBytes(4);
        }

        // Čtení payload
        $payload = $this->readBytes($length);

        if ($payload === false) {
            throw new Exception("Chyba při čtení payload");
        }

        // Demaskování
        if ($masked) {
            for ($i = 0; $i < $length; $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
            }
        }

        return $payload;
    }

    /**
     * Reads a specified number of bytes from the WebSocket connection.
     *
     * This method first attempts to read data from an internal buffer, then continues
     * reading from the socket until the requested number of bytes is obtained.
     *
     * @param int $length The number of bytes to read
     * 
     * @return string The read data
     * 
     * @throws Exception If the connection is terminated during reading
     * @throws Exception If incomplete data is received (actual length doesn't match expected length)
     */
    private function readBytes($length)
    {
        $data = '';
        $remaining = $length;

        // Nejprve vyčerpej buffer
        if (strlen($this->buffer) > 0) {
            $from_buffer = min($remaining, strlen($this->buffer));
            $data .= substr($this->buffer, 0, $from_buffer);
            $this->buffer = substr($this->buffer, $from_buffer);
            $remaining -= $from_buffer;
        }

        // Pak čti ze socketu
        $attempts = 0;
        $max_attempts = !$this->blocking ? 10 : 1000; // ✅ Více pokusů v non-blocking

        while ($remaining > 0 && $attempts < $max_attempts) {
            $chunk = fread($this->socket, $remaining);

            if ($chunk === false || $chunk === '') {
                if (feof($this->socket)) {
                    throw new Exception("Spojení ukončeno během čtení");
                }

                 // ✅ V non-blocking režimu počkat a zkusit znovu
                if (!$this->blocking) {
                    usleep(10000); // 10ms
                    $attempts++;
                    continue;
                }

                break;
            }
            
            $data .= $chunk;
            $remaining -= strlen($chunk);
             $attempts = 0; // Reset attempts po úspěšném čtení
        }

        // ✅ V non-blocking režimu vrátit null pokud nejsou všechna data
        if (strlen($data) !== $length) {
            if (!$this->blocking) {
                // Vrátit data zpět do bufferu
                $this->buffer = $data . $this->buffer;
                return null;
            }
            
            throw new Exception("Neúplná data: očekáváno {$length}, přijato " . strlen($data));
        }

        return $data;
    }

    /**
     * Sets the blocking mode for the websocket connection.
     *
     * This method configures whether the socket should operate in blocking or non-blocking mode.
     * In blocking mode, socket operations will wait until they complete. In non-blocking mode,
     * operations return immediately even if they haven't completed.
     *
     * @param bool $blocking True to enable blocking mode, false for non-blocking mode
     * @return void 
     */
    public function setBlocking($blocking)
    {
        $this->blocking = $blocking;
        if ($this->socket && is_resource($this->socket)) {
            stream_set_blocking($this->socket, $blocking ? 1 : 0);
        }
    }

    /**
     * Checks if there is data available to read from the WebSocket connection.
     *
     * This method uses stream_select to determine if there is data available
     * to read from the socket without blocking. It first checks if there is
     * any data in the internal buffer before checking the socket.
     *
     * @param int $timeout The timeout in seconds for the check (default: 0)
     * @return bool True if data is available, false otherwise
     */
    public function hasData($timeout = 0)
    {
        if (!$this->connected || !$this->socket) {
            return false;
        }

        // Pokud je něco v bufferu, data jsou k dispozici
        if (strlen($this->buffer) > 0) {
            return true;
        }

        $read = [$this->socket];
        $write = null;
        $except = null;
        
        $result = @stream_select($read, $write, $except, $timeout, 0);
        
        return $result > 0;
    }
    /**
     * Encodes a message into a WebSocket frame format according to RFC 6455.
     *
     * This method creates a properly formatted WebSocket frame by adding the frame header,
     * payload length indicators, masking key, and masked payload data. The frame is masked
     * as required by the WebSocket protocol for client-to-server communication.
     *
     * @param string $message The message content to be encoded into a WebSocket frame
     * @param int $opcode The WebSocket opcode (default: 0x1 for text frame)
     *                    Common opcodes: 0x0 (continuation), 0x1 (text), 0x2 (binary),
     *                    0x8 (close), 0x9 (ping), 0xA (pong)
     *
     * @return string The encoded WebSocket frame ready to be sent over the connection
     *
     * Frame structure:
     * - First byte: FIN bit (1) + RSV bits (000) + opcode (4 bits)
     * - Length byte(s): MASK bit (1) + payload length
     *   - If length <= 125: single byte with length
     *   - If length <= 65535: 126 + 2-byte length (16-bit big-endian)
     *   - If length > 65535: 127 + 8-byte length (64-bit big-endian)
     * - 4 bytes: masking key (random)
     * - Payload: masked message data (XOR with masking key)
     */
    private function encode($message, $opcode = 0x1)
    {
        $length = strlen($message);
        $frame = chr(0x80 | $opcode);

        // Délka payload
        if ($length <= 125) {
            $frame .= chr($length | 0x80);
        } elseif ($length <= 65535) {
            $frame .= chr(126 | 0x80) . pack('n', $length);
        } else {
            // RFC 6455: 64-bit big-endian (horní 32 bity + dolní 32 bity)
            $frame .= chr(127 | 0x80) . pack('NN', 0, $length);
        }

        // Maska (klient MUSÍ maskovat)
        $mask = pack('N', mt_rand(0, 0xFFFFFFFF));
        $frame .= $mask;

        // Maskování payload
        for ($i = 0; $i < $length; $i++) {
            $frame .= chr(ord($message[$i]) ^ ord($mask[$i % 4]));
        }

        return $frame;
    }

    /**
     * Sends a ping frame to the WebSocket client.
     *
     * This method sends a WebSocket ping frame (opcode 0x9) with an empty payload.
     * Ping frames are used to check if the connection is still alive and to keep
     * the connection active. The client should respond with a pong frame.
     *
     * @return void
     */
    public function sendPing()
    {
        $this->send('', 0x9);
    }

    /**
     * Checks if the WebSocket connection is currently active and valid.
     *
     * This method verifies three conditions to determine if the connection is established:
     * - The connected flag is set to true
     * - A valid socket resource exists
     * - The socket has not reached end-of-file (EOF)
     *
     * @return bool True if the connection is active and valid, false otherwise
     */
    public function isConnected()
    {
        return $this->connected && $this->socket && !feof($this->socket);
    }

    /**
     * Disconnects the WebSocket connection.
     * 
     * Sends a close frame (opcode 0x8) to the server and closes the socket connection.
     * If the socket is already disconnected or an error occurs during the close frame
     * transmission, it will be ignored and the socket will be closed anyway.
     * 
     * @return void
     * @throws Exception Any exception during close frame sending is caught and ignored
     */
    public function disconnect()
    {
        if ($this->socket && $this->connected) {
            try {
                $this->send('', 0x8); // Close frame
            } catch (Exception $e) {
                // Ignorovat chyby při zavírání
            }

            fclose($this->socket);
            $this->connected = false;
        }
    }

    /**
     * Destructor method that ensures proper cleanup when the Websocket object is destroyed.
     * 
     * This method is automatically called when the object is no longer referenced or when
     * the script execution ends. It ensures that the websocket connection is properly closed
     * and any resources are released by calling the disconnect() method.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}