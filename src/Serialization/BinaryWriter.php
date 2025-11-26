<?php

namespace OpenTimestamps\Serialization;

use OpenTimestamps\Exception\SerializationException;

class BinaryWriter {
    private string $data = '';

    public function writeByte(int $byte): void {
        $this->data .= chr($byte);
    }

    public function writeVarInt(int $n): void {
        if ($n < 0xfd) {
            $this->writeByte($n);
        } elseif ($n <= 0xffff) {
            $this->writeByte(0xfd);
            $this->data .= pack('v', $n); // little-endian 16-bit
        } elseif ($n <= 0xffffffff) {
            $this->writeByte(0xfe);
            $this->data .= pack('V', $n); // little-endian 32-bit
        } else {
            $this->writeByte(0xff);
            $this->data .= pack('P', $n); // little-endian 64-bit
        }
    }

    public function writeVarBytes(string $bytes): void {
        $this->writeVarInt(strlen($bytes));
        $this->data .= $bytes;
    }

    public function getData(): string {
        return $this->data;
    }
}
