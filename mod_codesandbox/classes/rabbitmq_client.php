<?php
/**
 * Cliente RabbitMQ para Code Sandbox
 * Usa php-amqplib para comunicación AMQP nativa
 */

namespace mod_codesandbox;

defined('MOODLE_INTERNAL') || die();

$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once($autoloader);
}

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;

class rabbitmq_client {
    
    private $host;
    private $port;
    private $user;
    private $pass;
    private $queue;
    private $connection = null;
    private $channel = null;
    
    /**
     * Constructor - carga config desde settings o usa defaults
     */
    public function __construct() {
        $this->host = get_config('mod_codesandbox', 'rabbitmq_host') ?: 'localhost';
        $this->port = get_config('mod_codesandbox', 'rabbitmq_port') ?: 5672;
        $this->user = get_config('mod_codesandbox', 'rabbitmq_user') ?: 'moodle';
        $this->pass = get_config('mod_codesandbox', 'rabbitmq_pass') ?: 'securepass';
        $this->queue = get_config('mod_codesandbox', 'rabbitmq_queue') ?: 'moodle_code_jobs';
    }
    
    /**
     * Conecta a RabbitMQ
     * 
     * @return bool
     */
    private function connect(): bool {
        if ($this->connection !== null && $this->connection->isConnected()) {
            return true;
        }
        
        try {
            $this->connection = new AMQPStreamConnection(
                $this->host,
                (int)$this->port,
                $this->user,
                $this->pass,
                '/',           // vhost
                false,         // insist
                'AMQPLAIN',    // login_method
                null,          // login_response
                'en_US',       // locale
                3.0,           // connection_timeout
                3.0            // read_write_timeout
            );
            $this->channel = $this->connection->channel();
            
            $this->channel->queue_declare(
                $this->queue,
                false,  // passive
                true,   // durable
                false,  // exclusive
                false   // auto_delete
            );
            
            return true;
        } catch (AMQPIOException | AMQPConnectionClosedException | \Exception $e) {
            debugging('RabbitMQ connection failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
    
    /**
     * Publica un job en la cola de RabbitMQ
     * 
     * @param array $payload Datos del job (job_id, language, code, etc)
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function publish_job(array $payload): array {
        if (!$this->connect()) {
            return ['success' => false, 'error' => 'Could not connect to RabbitMQ'];
        }
        
        try {
            $message = new AMQPMessage(
                json_encode($payload),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
                ]
            );
            
            $this->channel->basic_publish($message, '', $this->queue);
            
            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Verifica si RabbitMQ está disponible
     * 
     * @return bool
     */
    public function is_available(): bool {
        return $this->connect();
    }
    
    /**
     * Cierra la conexión
     */
    public function close(): void {
        try {
            if ($this->channel !== null) {
                $this->channel->close();
            }
            if ($this->connection !== null) {
                $this->connection->close();
            }
        } catch (\Exception $e) {
        
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}
