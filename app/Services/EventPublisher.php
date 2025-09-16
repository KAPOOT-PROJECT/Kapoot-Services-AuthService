<?php
namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use Illuminate\Support\Facades\Log;

class EventPublisher
{
    public const USER_REGITRED_EVENT = 'user_registered_event';

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
            //TODO error handling
            self::$connection = new AMQPStreamConnection(self::$host, self::$port, self::$user, self::$password);
        }

        return self::$connection;
    }

    /**
     * Get the RabbitMQ channel (singleton pattern)
     */
    private static function getChannel(): AMQPChannel
    {
        if (self::$channel === null || !self::$channel->is_open()) {
            //TODO error handling
            $connection = self::getConnection();
            self::$channel = $connection->channel();
            //TODO declare exchange and binding
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

        while(true) {
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
            //TODO Logging
        }

        try {
            if (self::$connection !== null && self::$connection->isConnected()) {
                self::$connection->close();
            }
        } catch (\Throwable $th) {
            //TODO Logging
        }

        self::$connection = null;
        self::$channel= null;
    }

    /**
     * Publish user registered event
     */
    public function publishUserRegistered($userId, $email, $name, $phone): bool
    {
        //TODO error handling
        $data = [
            'userId' => $userId,
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'time' => date('Y-m-d H:i:s'), // replace with now() or Carbon Facade
            'event' => self::USER_REGITRED_EVENT
        ];

        return $this->publish('user_registered', $data);
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
