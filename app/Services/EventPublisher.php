<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use Illuminate\Support\Facades\Log;

class EventPublisher
{
    public const USER_REGISTERED_EVENT = 'user_registered_event';
    public const USER_LOGGED_IN_EVENT = 'user_logged_in_event';

    private static ?AMQPStreamConnection $connection = null;
    private static ?AMQPChannel $channel = null;

    private static string $host;
    private static int $port;
    private static string $user;
    private static string $password;
    private static string $exchange;
    private static string $exchangeType;

    public function __construct()
    {
        self::initConfig();
    }

    private static function initConfig(): void
    {
        if (!isset(self::$host)) {
            $config = config('services.rabbitmq');
            self::$host = $config['host'];
            self::$port = $config['port'];
            self::$user = $config['user'];
            self::$password = $config['password'];
            self::$exchange = $config['exchange'];
            self::$exchangeType = $config['exchange_type'];
        }
    }

    /**
     * Get the RabbitMQ connection (singleton pattern)
     */
    private static function getConnection(): AMQPStreamConnection
    {
        if (self::$connection === null || !self::$connection->isConnected()) {
            try {
                self::$connection = new AMQPStreamConnection(self::$host, self::$port, self::$user, self::$password);
            } catch (\Throwable $e) {
                Log::error("RabbitMQ connection error: {$e->getMessage()}");
                throw $e;
            }
        }

        return self::$connection;
    }

    /**
     * Get the RabbitMQ channel (singleton pattern)
     */
    private static function getChannel(): AMQPChannel
    {
        if (self::$channel === null || !self::$channel->is_open()) {
            try {
                $connection = self::getConnection();
                self::$channel = $connection->channel();

                self::$channel->exchange_declare(
                    self::$exchange,
                    self::$exchangeType,
                    false, 
                    true,  
                    false  
                );
                self::$channel->queue_declare(
                    'customer_events', 
                    false,               
                    true,            
                    false,                   
                    false                   
                );

                self::$channel->queue_bind(
                    'customer_events', 
                    self::$exchange,     
                    'customer_events'     
                );
            } catch (\Throwable $e) {
                Log::error("RabbitMQ channel error: {$e->getMessage()}");
                throw $e;
            }
        }

        return self::$channel;
    }

    /**
     * Publish a message to RabbitMQ with retry logic
     */
    private function publish(string $routingKey, array $data): bool
    {
        $attempts = 0;
        $maxAttempts = 3;

        while (true) {
            try {
                $channel = self::getChannel();
                $channel->basic_publish(
                    new AMQPMessage(json_encode($data)),
                    self::$exchange,
                    $routingKey
                );
                return true;
            } catch (\Throwable $e) {
                Log::error("Error On publish AMQP: {$e->getMessage()}");
                self::resetConnection();
            } finally {
                $attempts++;
            }

            if ($attempts > $maxAttempts) {
                throw new \Exception("Max Attempts On Publish Message to RabbitMQ");
            }
        }
    }

    /**
     * Reset the connection and channel
     */
    private static function resetConnection(): void
    {
        try {
            if (self::$channel !== null && self::$channel->is_open()) {
                self::$channel->close();
            }
        } catch (\Throwable $th) {
            Log::error("Error closing RabbitMQ channel: {$th->getMessage()}");
        }

        try {
            if (self::$connection !== null && self::$connection->isConnected()) {
                self::$connection->close();
            }
        } catch (\Throwable $th) {
            Log::error("Error closing RabbitMQ connection: {$th->getMessage()}");
        }

        self::$connection = null;
        self::$channel = null;
    }

    /**
     * Publish user registered event
     */
    public function publishUserRegistered($userId, $email, $name, $phone): bool
    {
        try {
            $data = [
                'userId' => $userId,
                'email' => $email,
                'name' => $name,
                'phone' => $phone,
                'time' => now(),
                'event' => self::USER_REGISTERED_EVENT
            ];
            return $this->publish('customer_events', $data);
        } catch (\Throwable $e) {
            Log::error("Error publishing user_registered event: {$e->getMessage()}");
            return false;
        }
    }
    public function publishUserLoggedIn($userId, $email, $name, $phone , $ip): bool
    {
        try {
            $data = [
                'userId' => $userId,
                'email' => $email,
                'name' => $name,
                'phone' => $phone,
                'ip' => $ip,
                'time' => now(),
                'event' => self::USER_LOGGED_IN_EVENT
            ];
            return $this->publish('customer_events', $data);
        } catch (\Throwable $e) {
            Log::error("Error publishing user_logged_in event: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Publish user logged in event
     */
    // public function publishUserLoggedIn($userId, $email, $ipAddress, $userAgent): bool
    // {

    // }

    /**
     * Clean up connection when object is destroyed
     */
    public function __destruct()
    {
        self::resetConnection();
    }
}
