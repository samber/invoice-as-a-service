<?php

namespace Tests\Support;

/**
 * Starts the throwaway HTTP and FTP servers from tests/Support as child processes.
 */
trait LocalServers
{
    private static $processes = [];

    private static function startProcess(array $command, array $env = []): void
    {
        $process = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env + getenv());
        self::assertIsResource($process);
        self::$processes[] = $process;
    }

    private static function stopProcesses(): void
    {
        foreach (self::$processes as $process) {
            proc_terminate($process);
            proc_close($process);
        }
        self::$processes = [];
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    private static function waitForPort(int $port): void
    {
        for ($i = 0; $i < 100; $i++) {
            $conn = @fsockopen('127.0.0.1', $port);
            if ($conn !== false) {
                fclose($conn);
                return;
            }
            usleep(50000);
        }
        self::fail("Nothing is listening on port {$port}");
    }

    /** Returns the base URL of a capture server writing requests to $captureFile. */
    private static function startHttpServer(string $captureFile): string
    {
        $port = self::freePort();
        self::startProcess(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__ . '/http-capture-server.php'],
            ['CAPTURE_FILE' => $captureFile],
        );
        self::waitForPort($port);

        return "http://127.0.0.1:{$port}";
    }

    /** Returns the port of an FTP server storing uploads into $uploadDir. */
    private static function startFtpServer(string $uploadDir): int
    {
        $port = self::freePort();
        self::startProcess([PHP_BINARY, __DIR__ . '/fake-ftp-server.php', (string) $port, $uploadDir]);
        self::waitForPort($port);

        return $port;
    }
}
