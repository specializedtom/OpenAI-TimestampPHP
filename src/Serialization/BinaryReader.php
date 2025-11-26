<?php

namespace OpenTimestamps\Serialization;

use OpenTimestamps\Exception\SerializationException;

class BinaryReader {
    private string $data;
    private int $pos = 0;

    public function __construct(string $data) {
        $this->data = $data;
    }

    public function readByte(): int {
        if ($this->pos >= strlen($this->data)) {
            throw new SerializationException('Unexpected EOF');
        }
        return ord($this->data[$this->pos++]);
    }

    public function readVarInt(): int {
        $first = $this->readByte();
        if ($first < 0xfd) {
            return $first;
        } elseif ($first === 0xfd) {
            $v = substr($this->data, $this->pos, 2);
            $this->pos += 2;
            $arr = unpack('v', $v); // little-endian 16-bit
            return $arr[1];
        } elseif ($first === 0xfe) {
            $v = substr($this->data, $this->pos, 4);
            $this->pos += 4;
            $arr = unpack('V', $v); // little-endian 32-bit
            return $arr[1];
        } else { // 0xff
            $v = substr($this->data, $this->pos, 8);
            $this->pos += 8;
            $arr = unpack('P', $v); // little-endian 64-bit
            return $arr[1];
        }
    }

    public function readVarBytes(): string {
        $length = $this->readVarInt();
        if ($this->pos + $length > strlen($this->data)) {
            throw new SerializationException('Unexpected EOF in readVarBytes');
        }
        $bytes = substr($this->data, $this->pos, $length);
        $this->pos += $length;
        return $bytes;
    }
}

