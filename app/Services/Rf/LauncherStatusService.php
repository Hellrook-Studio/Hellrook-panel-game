<?php

namespace Pterodactyl\Services\Rf;

/**
 * Menanyakan status server RF 4.55 lewat port launcher, sama seperti "rflauncher -status".
 *
 * Satu koneksi, satu frame permintaan, satu frame balasan. Frame: uint16 little-endian ukuran
 * total (dua byte ini ikut dihitung), byte kategori 0x6C, byte id, lalu payload. Balasan status:
 * ok (u8), protocol (u16), lima string (u16 panjang + byte), players (u16), max sessions (u16).
 */
class LauncherStatusService
{
    private const CATEGORY = 0x6C;
    private const STATUS_REQUEST = 0x01;
    private const STATUS_REPLY = 0x02;
    private const HEADER_SIZE = 4;
    private const MAX_FRAME = 4096;

    /**
     * @return array{online: bool, ready: bool, players: int, max_sessions: int, game: ?string, version: ?string, error: ?string}
     */
    public function query(string $host, int $port, float $timeout = 3.0): array
    {
        $result = [
            'online' => false,
            'ready' => false,
            'players' => 0,
            'max_sessions' => 0,
            'game' => null,
            'version' => null,
            'error' => null,
        ];

        $socket = @stream_socket_client(sprintf('tcp://%s:%d', $host, $port), $errno, $error, $timeout);
        if ($socket === false) {
            return ['error' => $error ?: 'connection failed'] + $result;
        }

        try {
            stream_set_timeout($socket, (int) $timeout, (int) (fmod($timeout, 1) * 1_000_000));
            fwrite($socket, pack('vCC', self::HEADER_SIZE, self::CATEGORY, self::STATUS_REQUEST));

            $header = unpack('vsize/Ccategory/Cid', $this->read($socket, self::HEADER_SIZE));
            if ($header['category'] !== self::CATEGORY || $header['id'] !== self::STATUS_REPLY
                || $header['size'] < self::HEADER_SIZE || $header['size'] > self::MAX_FRAME) {
                return ['error' => 'the port did not answer like an RF launcher port'] + $result;
            }

            $payload = $this->read($socket, $header['size'] - self::HEADER_SIZE);
            $offset = 0;
            $ok = $this->u8($payload, $offset);
            $this->u16($payload, $offset); // protocol
            $strings = [];
            for ($i = 0; $i < 5; ++$i) {
                $strings[] = $this->str($payload, $offset);
            }

            return [
                'online' => true,
                'ready' => $ok !== 0,
                'players' => $this->u16($payload, $offset),
                'max_sessions' => $this->u16($payload, $offset),
                'game' => $strings[1],
                'version' => $strings[2],
            ] + $result;
        } catch (\RuntimeException $exception) {
            return ['error' => $exception->getMessage()] + $result;
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     */
    private function read($socket, int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException(stream_get_meta_data($socket)['timed_out'] ? 'no reply (timeout)' : 'connection closed before the reply was complete');
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function u8(string $payload, int &$offset): int
    {
        $this->need($payload, $offset, 1);

        return ord($payload[$offset++]);
    }

    private function u16(string $payload, int &$offset): int
    {
        $this->need($payload, $offset, 2);
        $value = unpack('v', $payload, $offset)[1];
        $offset += 2;

        return $value;
    }

    private function str(string $payload, int &$offset): string
    {
        $length = $this->u16($payload, $offset);
        $this->need($payload, $offset, $length);
        $value = substr($payload, $offset, $length);
        $offset += $length;

        return $value;
    }

    private function need(string $payload, int $offset, int $length): void
    {
        if (strlen($payload) - $offset < $length) {
            throw new \RuntimeException('malformed status reply');
        }
    }
}
