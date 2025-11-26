<?php

namespace OpenTimestamps\Ops;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

/**
 * Prepend raw bytes to a message.
 * OTS OP CODE: 0x0c
 */
class PrependOp extends Op
{
    public const OPCODE = 0x0c;

    private string $prefix;

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function apply(string $data): string
    {
        return $this->prefix . $data;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeVarInt(self::OPCODE);
        $writer->writeVarBytes($this->prefix);
    }

    public static function deserialize(BinaryReader $reader): self
    {
        $prefix = $reader->readVarBytes();
        return new self($prefix);
    }
}
