<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

class AppendOp extends Op {
    public const OPCODE = 0x02;
    private string $bytes;

    public function __construct(string $bytes) {
        $this->bytes = $bytes;
    }

    public function apply(string $input): string {
        return $input . $this->bytes;
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        $writer->writeByte(self::OPCODE);
        $writer->writeVarBytes($this->bytes);
        return $writer->getData();
    }

    public static function fromData(BinaryReader $reader): self {
        $bytes = $reader->readVarBytes();
        return new self($bytes);
    }
}
