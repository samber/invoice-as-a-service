<?php

/*
 * Minimal passive-mode FTP server used by StorageTest.
 *
 * Usage: php fake-ftp-server.php <port> <upload-dir>
 *
 * Accepts USER "user" with PASS "pass" (anything else gets 530), stores each STOR
 * into <upload-dir>, and serves one connection at a time until killed.
 */

[, $port, $uploadDir] = $argv;

$server = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "cannot listen: {$errstr}\n");
    exit(1);
}

$reply = static function ($conn, string $line): void {
    fwrite($conn, $line . "\r\n");
};

while ($conn = stream_socket_accept($server, -1)) {
    $reply($conn, '220 fake ftp ready');
    $user = null;
    $pasv = null;

    while (($line = fgets($conn)) !== false) {
        $line = rtrim($line, "\r\n");
        [$cmd, $arg] = array_pad(explode(' ', $line, 2), 2, '');
        $cmd = strtoupper($cmd);

        switch ($cmd) {
            case 'USER':
                $user = $arg;
                $reply($conn, '331 password required');
                break;
            case 'PASS':
                $reply($conn, ($user === 'user' && $arg === 'pass') ? '230 logged in' : '530 login incorrect');
                break;
            case 'SYST':
                $reply($conn, '215 UNIX Type: L8');
                break;
            case 'FEAT':
                $reply($conn, '211 no features');
                break;
            case 'OPTS':
            case 'TYPE':
            case 'NOOP':
                $reply($conn, '200 ok');
                break;
            case 'PWD':
                $reply($conn, '257 "/" is the current directory');
                break;
            case 'CWD':
                $reply($conn, is_dir($uploadDir) ? '250 directory changed' : '550 no such directory');
                break;
            case 'PASV':
                $pasv = stream_socket_server('tcp://127.0.0.1:0');
                $dataPort = (int) substr(strrchr(stream_socket_get_name($pasv, false), ':'), 1);
                $reply($conn, sprintf('227 Entering Passive Mode (127,0,0,1,%d,%d)', intdiv($dataPort, 256), $dataPort % 256));
                break;
            case 'STOR':
                $reply($conn, '150 ready for data');
                $data = stream_socket_accept($pasv, 5);
                file_put_contents($uploadDir . '/' . basename($arg), stream_get_contents($data));
                fclose($data);
                fclose($pasv);
                $pasv = null;
                $reply($conn, '226 transfer complete');
                break;
            case 'SITE':
                $reply($conn, '200 ok');
                break;
            case 'QUIT':
                $reply($conn, '221 bye');
                break 2;
            default:
                $reply($conn, '502 command not implemented');
        }
    }

    fclose($conn);
}
